<?php

namespace Tests\Unit\AIAssistant;

use Illuminate\Support\Facades\Http;
use Modules\AIAssistant\AIProviders\DeepSeekProvider;
use Modules\AIAssistant\app\DataTransfer\AIChatRequest;
use Modules\AIAssistant\app\DataTransfer\AIProviderCredentials;
use Modules\AIAssistant\app\DataTransfer\AIResponse;
use Modules\AIAssistant\app\DataTransfer\AIToolDefinition;
use Modules\AIAssistant\app\DataTransfer\ChatMessage;
use Tests\TestCase;

/**
 * Exercises the OpenAI-compatible request/response mapping shared by
 * DeepSeek and OpenAI (AIProviders\Support\AbstractOpenAICompatibleProvider)
 * via the DeepSeek adapter. Uses Http::fake() — no database needed, so this
 * runs even in an environment with no configured DB connection.
 */
class DeepSeekProviderTest extends TestCase
{
    public function test_maps_a_plain_text_reply_into_a_normalized_response(): void
    {
        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'model' => 'deepseek-chat',
                'choices' => [[
                    'message' => ['role' => 'assistant', 'content' => 'Hello there!'],
                    'finish_reason' => 'stop',
                ]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'prompt_cache_hit_tokens' => 2],
            ], 200),
        ]);

        $provider = new DeepSeekProvider();
        $provider->setCredentials(new AIProviderCredentials(apiKey: 'test-key'));

        $response = $provider->chat(new AIChatRequest(
            model: 'deepseek-chat',
            systemInstructions: 'You are a helpful assistant.',
            messages: [ChatMessage::user('Hi')],
        ));

        $this->assertSame('Hello there!', $response->content);
        $this->assertSame(AIResponse::FINISH_STOP, $response->finishReason);
        $this->assertSame('deepseek', $response->provider);
        $this->assertFalse($response->hasToolCalls());
        $this->assertSame(10, $response->usage->inputTokens);
        $this->assertSame(5, $response->usage->outputTokens);
        $this->assertSame(2, $response->usage->cachedTokens);
    }

    public function test_maps_a_tool_call_into_a_normalized_ai_tool_call(): void
    {
        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'model' => 'deepseek-chat',
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_123',
                            'type' => 'function',
                            'function' => ['name' => 'search_products', 'arguments' => '{"query":"black dress"}'],
                        ]],
                    ],
                    'finish_reason' => 'tool_calls',
                ]],
            ], 200),
        ]);

        $provider = new DeepSeekProvider();
        $provider->setCredentials(new AIProviderCredentials(apiKey: 'test-key'));

        $response = $provider->chat(new AIChatRequest(
            model: 'deepseek-chat',
            systemInstructions: null,
            messages: [ChatMessage::user('I want a black dress')],
            tools: [new AIToolDefinition('search_products', 'Search products', ['type' => 'object'])],
        ));

        $this->assertTrue($response->hasToolCalls());
        $this->assertCount(1, $response->toolCalls);
        $this->assertSame('search_products', $response->toolCalls[0]->name);
        $this->assertSame(['query' => 'black dress'], $response->toolCalls[0]->arguments);
    }

    public function test_sends_tools_in_openai_function_calling_shape(): void
    {
        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'model' => 'deepseek-chat',
                'choices' => [['message' => ['content' => 'ok'], 'finish_reason' => 'stop']],
            ], 200),
        ]);

        $provider = new DeepSeekProvider();
        $provider->setCredentials(new AIProviderCredentials(apiKey: 'test-key'));

        $provider->chat(new AIChatRequest(
            model: 'deepseek-chat',
            systemInstructions: null,
            messages: [ChatMessage::user('hi')],
            tools: [new AIToolDefinition('check_stock', 'Check stock', ['type' => 'object', 'properties' => ['product_id' => ['type' => 'integer']]])],
        ));

        Http::assertSent(function ($request) {
            $tools = $request->data()['tools'] ?? null;
            return $tools
                && $tools[0]['type'] === 'function'
                && $tools[0]['function']['name'] === 'check_stock';
        });
    }
}
