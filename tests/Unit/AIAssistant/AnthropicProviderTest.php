<?php

namespace Tests\Unit\AIAssistant;

use Illuminate\Support\Facades\Http;
use Modules\AIAssistant\AIProviders\AnthropicProvider;
use Modules\AIAssistant\app\DataTransfer\AIChatRequest;
use Modules\AIAssistant\app\DataTransfer\AIProviderCredentials;
use Modules\AIAssistant\app\DataTransfer\AIResponse;
use Modules\AIAssistant\app\DataTransfer\AIToolCall;
use Modules\AIAssistant\app\DataTransfer\ChatMessage;
use Tests\TestCase;

/**
 * Anthropic's wire format (top-level `system`, `tool_use`/`tool_result`
 * content blocks) is deliberately different from the OpenAI-compatible
 * providers — this test is the proof that the abstraction actually absorbs
 * that heterogeneity rather than assuming one shape. See architecture doc
 * Part II §1/§3.
 */
class AnthropicProviderTest extends TestCase
{
    public function test_maps_text_and_tool_use_blocks_into_normalized_response(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'model' => 'claude-x',
                'content' => [
                    ['type' => 'text', 'text' => 'Let me check that for you.'],
                    ['type' => 'tool_use', 'id' => 'toolu_01', 'name' => 'check_stock', 'input' => ['product_id' => 42]],
                ],
                'stop_reason' => 'tool_use',
                'usage' => ['input_tokens' => 20, 'output_tokens' => 8, 'cache_read_input_tokens' => 3],
            ], 200),
        ]);

        $provider = new AnthropicProvider();
        $provider->setCredentials(new AIProviderCredentials(apiKey: 'test-key'));

        $response = $provider->chat(new AIChatRequest(
            model: 'claude-x',
            systemInstructions: 'You are a shopping assistant.',
            messages: [ChatMessage::user('Is this in stock?')],
        ));

        $this->assertSame('Let me check that for you.', $response->content);
        $this->assertSame(AIResponse::FINISH_TOOL_CALLS, $response->finishReason);
        $this->assertTrue($response->hasToolCalls());
        $this->assertSame('check_stock', $response->toolCalls[0]->name);
        $this->assertSame(['product_id' => 42], $response->toolCalls[0]->arguments);
        $this->assertSame(20, $response->usage->inputTokens);
        $this->assertSame(3, $response->usage->cachedTokens);
    }

    public function test_system_instructions_go_in_the_top_level_system_field_not_a_message(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'model' => 'claude-x',
                'content' => [['type' => 'text', 'text' => 'ok']],
                'stop_reason' => 'end_turn',
            ], 200),
        ]);

        $provider = new AnthropicProvider();
        $provider->setCredentials(new AIProviderCredentials(apiKey: 'test-key'));

        $provider->chat(new AIChatRequest(
            model: 'claude-x',
            systemInstructions: 'Only discuss this vendor\'s products.',
            messages: [ChatMessage::user('hi')],
        ));

        Http::assertSent(function ($request) {
            $data = $request->data();
            $roles = array_column($data['messages'], 'role');
            return $data['system'] === 'Only discuss this vendor\'s products.'
                && !in_array('system', $roles, true);
        });
    }

    public function test_a_tool_result_is_sent_as_a_user_message_with_a_tool_result_block(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'model' => 'claude-x',
                'content' => [['type' => 'text', 'text' => 'ok']],
                'stop_reason' => 'end_turn',
            ], 200),
        ]);

        $provider = new AnthropicProvider();
        $provider->setCredentials(new AIProviderCredentials(apiKey: 'test-key'));

        $provider->chat(new AIChatRequest(
            model: 'claude-x',
            systemInstructions: null,
            messages: [
                ChatMessage::user('Is it in stock?'),
                ChatMessage::assistant(null, [new AIToolCall('toolu_01', 'check_stock', ['product_id' => 42])]),
                ChatMessage::toolResult('toolu_01', '{"in_stock":true}'),
            ],
        ));

        Http::assertSent(function ($request) {
            $data = $request->data();
            $last = end($data['messages']);
            return $last['role'] === 'user'
                && $last['content'][0]['type'] === 'tool_result'
                && $last['content'][0]['tool_use_id'] === 'toolu_01';
        });
    }
}
