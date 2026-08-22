<?php

namespace Modules\AIAssistant\AIProviders;

use Illuminate\Support\Facades\Http;
use Modules\AIAssistant\app\Contracts\AIProviderInterface;
use Modules\AIAssistant\app\DataTransfer\AIChatRequest;
use Modules\AIAssistant\app\DataTransfer\AIProviderCapabilities;
use Modules\AIAssistant\app\DataTransfer\AIProviderCredentials;
use Modules\AIAssistant\app\DataTransfer\AIResponse;
use Modules\AIAssistant\app\DataTransfer\AIToolCall;
use Modules\AIAssistant\app\DataTransfer\AIUsage;
use Modules\AIAssistant\app\DataTransfer\ChatMessage;
use Modules\AIAssistant\app\Exceptions\AIProviderException;

/**
 * Anthropic's Messages API is deliberately NOT built on
 * AIProviders\Support\AbstractOpenAICompatibleProvider — system
 * instructions are a top-level field rather than a message, tool calls are
 * `tool_use` content blocks rather than a `tool_calls` array, and tool
 * results are sent back as a user-role `tool_result` content block rather
 * than a `role: tool` message. This is exactly the heterogeneity the
 * provider-agnostic architecture exists to absorb — everything below maps
 * into the same AIResponse/AIToolCall/AIUsage shape the OpenAI-compatible
 * providers produce, and ConversationService never sees the difference.
 */
class AnthropicProvider implements AIProviderInterface
{
    private const DEFAULT_BASE_URL = 'https://api.anthropic.com/v1';
    private const ANTHROPIC_VERSION = '2023-06-01';
    private const DEFAULT_MAX_TOKENS = 1024;

    protected ?AIProviderCredentials $credentials = null;

    public function getName(): string
    {
        return 'anthropic';
    }

    public function getCapabilities(): AIProviderCapabilities
    {
        return new AIProviderCapabilities(
            chat: true,
            systemInstructions: true,
            toolCalling: true,
            structuredOutput: true,
            streaming: true,
            usageReporting: true,
        );
    }

    public function setCredentials(AIProviderCredentials $credentials): void
    {
        $this->credentials = $credentials;
    }

    public function chat(AIChatRequest $request): AIResponse
    {
        if (!$this->credentials?->apiKey) {
            throw new AIProviderException('anthropic has no API key configured.');
        }

        $baseUrl = rtrim($this->credentials->baseUrl ?: self::DEFAULT_BASE_URL, '/');

        $payload = array_filter([
            'model' => $request->model,
            'system' => $request->systemInstructions,
            'messages' => $this->mapMessages($request),
            'temperature' => $request->temperature,
            'max_tokens' => $request->maxTokens ?? self::DEFAULT_MAX_TOKENS,
        ], fn ($value) => !is_null($value));

        if (!empty($request->tools)) {
            $payload['tools'] = array_map(fn ($tool) => [
                'name' => $tool->name,
                'description' => $tool->description,
                'input_schema' => $tool->parameterSchema,
            ], $request->tools);
        }

        $response = Http::withHeaders([
            'x-api-key' => $this->credentials->apiKey,
            'anthropic-version' => self::ANTHROPIC_VERSION,
        ])->timeout(60)->post("{$baseUrl}/messages", $payload);

        if ($response->failed()) {
            throw new AIProviderException(
                "anthropic request failed ({$response->status()}): " . $response->body()
            );
        }

        return $this->mapResponse($response->json());
    }

    private function mapMessages(AIChatRequest $request): array
    {
        $messages = [];

        foreach ($request->messages as $message) {
            /** @var ChatMessage $message */
            $messages[] = match ($message->role) {
                ChatMessage::ROLE_TOOL => [
                    'role' => 'user',
                    'content' => [[
                        'type' => 'tool_result',
                        'tool_use_id' => $message->toolCallId,
                        'content' => (string)$message->content,
                    ]],
                ],
                ChatMessage::ROLE_ASSISTANT => [
                    'role' => 'assistant',
                    'content' => $this->assistantContentBlocks($message),
                ],
                default => ['role' => 'user', 'content' => (string)$message->content],
            };
        }

        return $messages;
    }

    private function assistantContentBlocks(ChatMessage $message): array
    {
        $blocks = [];

        if ($message->content) {
            $blocks[] = ['type' => 'text', 'text' => $message->content];
        }

        foreach ($message->toolCalls as $call) {
            /** @var AIToolCall $call */
            $blocks[] = [
                'type' => 'tool_use',
                'id' => $call->id,
                'name' => $call->name,
                'input' => $call->arguments,
            ];
        }

        return $blocks;
    }

    private function mapResponse(array $body): AIResponse
    {
        $content = null;
        $toolCalls = [];

        foreach ($body['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'text') {
                $content = ($content ?? '') . $block['text'];
            } elseif (($block['type'] ?? null) === 'tool_use') {
                $toolCalls[] = new AIToolCall(
                    id: $block['id'],
                    name: $block['name'],
                    arguments: $block['input'] ?? [],
                    providerMetadata: $block,
                );
            }
        }

        $finishReason = match ($body['stop_reason'] ?? 'end_turn') {
            'tool_use' => AIResponse::FINISH_TOOL_CALLS,
            'max_tokens' => AIResponse::FINISH_LENGTH,
            default => AIResponse::FINISH_STOP,
        };

        $usage = null;
        if (isset($body['usage'])) {
            $rawUsage = $body['usage'];
            $usage = new AIUsage(
                inputTokens: (int)($rawUsage['input_tokens'] ?? 0),
                outputTokens: (int)($rawUsage['output_tokens'] ?? 0),
                cachedTokens: (int)($rawUsage['cache_read_input_tokens'] ?? 0),
            );
        }

        return new AIResponse(
            content: $content,
            toolCalls: $toolCalls,
            finishReason: $finishReason,
            usage: $usage,
            provider: $this->getName(),
            model: $body['model'] ?? '',
            metadata: $body,
        );
    }
}
