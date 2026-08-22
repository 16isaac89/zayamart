<?php

namespace Modules\AIAssistant\AIProviders\Support;

use Illuminate\Support\Facades\Http;
use Modules\AIAssistant\app\Contracts\AIProviderInterface;
use Modules\AIAssistant\app\DataTransfer\AIChatRequest;
use Modules\AIAssistant\app\DataTransfer\AIProviderCredentials;
use Modules\AIAssistant\app\DataTransfer\AIResponse;
use Modules\AIAssistant\app\DataTransfer\AIToolCall;
use Modules\AIAssistant\app\DataTransfer\AIUsage;
use Modules\AIAssistant\app\DataTransfer\ChatMessage;
use Modules\AIAssistant\app\Exceptions\AIProviderException;

/**
 * Shared request/response mapping for providers that speak the OpenAI
 * "chat completions + tool calling" wire format — DeepSeek is explicitly
 * OpenAI-API-compatible, and OpenAI itself obviously is. Anthropic's format
 * differs enough (top-level `system`, `tool_use`/`tool_result` content
 * blocks instead of a `tool_calls` array) that it is not built on this base
 * — see AIProviders\AnthropicProvider.
 *
 * A future provider that is *not* OpenAI-compatible does not extend this;
 * it implements AIProviderInterface directly, exactly like Anthropic does.
 */
abstract class AbstractOpenAICompatibleProvider implements AIProviderInterface
{
    protected ?AIProviderCredentials $credentials = null;

    abstract protected function defaultBaseUrl(): string;

    public function setCredentials(AIProviderCredentials $credentials): void
    {
        $this->credentials = $credentials;
    }

    public function chat(AIChatRequest $request): AIResponse
    {
        if (!$this->credentials?->apiKey) {
            throw new AIProviderException($this->getName() . ' has no API key configured.');
        }

        $baseUrl = rtrim($this->credentials->baseUrl ?: $this->defaultBaseUrl(), '/');

        $payload = [
            'model' => $request->model,
            'messages' => $this->mapMessages($request),
            'temperature' => $request->temperature,
        ];

        if ($request->maxTokens) {
            $payload['max_tokens'] = $request->maxTokens;
        }

        if (!empty($request->tools)) {
            $payload['tools'] = array_map(fn ($tool) => [
                'type' => 'function',
                'function' => [
                    'name' => $tool->name,
                    'description' => $tool->description,
                    'parameters' => $tool->parameterSchema,
                ],
            ], $request->tools);

            if ($request->toolChoice) {
                $payload['tool_choice'] = $request->toolChoice;
            }
        }

        $response = Http::withToken($this->credentials->apiKey)
            ->timeout(60)
            ->post("{$baseUrl}/chat/completions", $payload);

        if ($response->failed()) {
            throw new AIProviderException(
                "{$this->getName()} request failed ({$response->status()}): " . $response->body()
            );
        }

        return $this->mapResponse($response->json());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mapMessages(AIChatRequest $request): array
    {
        $messages = [];

        if ($request->systemInstructions) {
            $messages[] = ['role' => 'system', 'content' => $request->systemInstructions];
        }

        foreach ($request->messages as $message) {
            /** @var ChatMessage $message */
            $messages[] = match ($message->role) {
                ChatMessage::ROLE_TOOL => [
                    'role' => 'tool',
                    'tool_call_id' => $message->toolCallId,
                    'content' => (string)$message->content,
                ],
                ChatMessage::ROLE_ASSISTANT => array_filter([
                    'role' => 'assistant',
                    'content' => $message->content,
                    'tool_calls' => empty($message->toolCalls) ? null : array_map(fn (AIToolCall $call) => [
                        'id' => $call->id,
                        'type' => 'function',
                        'function' => [
                            'name' => $call->name,
                            'arguments' => json_encode($call->arguments),
                        ],
                    ], $message->toolCalls),
                ], fn ($value) => !is_null($value)),
                default => ['role' => 'user', 'content' => (string)$message->content],
            };
        }

        return $messages;
    }

    private function mapResponse(array $body): AIResponse
    {
        $choice = $body['choices'][0] ?? [];
        $message = $choice['message'] ?? [];

        $toolCalls = array_map(function (array $call) {
            return new AIToolCall(
                id: $call['id'],
                name: $call['function']['name'],
                arguments: json_decode($call['function']['arguments'] ?? '{}', true) ?: [],
                providerMetadata: $call,
            );
        }, $message['tool_calls'] ?? []);

        $finishReason = match ($choice['finish_reason'] ?? 'stop') {
            'tool_calls' => AIResponse::FINISH_TOOL_CALLS,
            'length' => AIResponse::FINISH_LENGTH,
            'content_filter' => AIResponse::FINISH_CONTENT_FILTER,
            default => AIResponse::FINISH_STOP,
        };

        $usage = null;
        if (isset($body['usage'])) {
            $rawUsage = $body['usage'];
            $usage = new AIUsage(
                inputTokens: (int)($rawUsage['prompt_tokens'] ?? 0),
                outputTokens: (int)($rawUsage['completion_tokens'] ?? 0),
                // OpenAI: usage.prompt_tokens_details.cached_tokens.
                // DeepSeek: usage.prompt_cache_hit_tokens. Both checked;
                // absence of either just means 0 cached tokens, not an error.
                cachedTokens: (int)(
                    $rawUsage['prompt_tokens_details']['cached_tokens']
                    ?? $rawUsage['prompt_cache_hit_tokens']
                    ?? 0
                ),
            );
        }

        return new AIResponse(
            content: $message['content'] ?? null,
            toolCalls: $toolCalls,
            finishReason: $finishReason,
            usage: $usage,
            provider: $this->getName(),
            model: $body['model'] ?? '',
            metadata: $body,
        );
    }
}
