<?php

namespace Tests\Unit\AIAssistant;

use Modules\AIAssistant\AIProviders\AnthropicProvider;
use Modules\AIAssistant\AIProviders\DeepSeekProvider;
use Modules\AIAssistant\app\DataTransfer\ChatMessage;
use Modules\AIAssistant\app\Exceptions\AIProviderException;
use Modules\AIAssistant\app\Models\AIProvider;
use Modules\AIAssistant\app\Models\AIProviderConfig;
use Modules\AIAssistant\app\Models\AIProviderModel;
use Modules\AIAssistant\app\Services\AIProviderManager;
use Tests\TestCase;

/**
 * The one class ConversationService is allowed to depend on (architecture
 * doc Part II §1/§5). Relations are wired with setRelation() rather than
 * persisted, so this runs without a database connection.
 */
class AIProviderManagerTest extends TestCase
{
    private function config(string $providerKey, string $status, array $modelCapabilities = null): AIProviderConfig
    {
        $provider = new AIProvider(['key' => $providerKey, 'display_name' => $providerKey, 'status' => $status]);
        $provider->id = 1;

        $model = new AIProviderModel([
            'model_name' => "{$providerKey}-model",
            'capabilities' => $modelCapabilities,
            'input_price' => 1.0,
            'output_price' => 2.0,
        ]);
        $model->id = 1;

        $config = new AIProviderConfig(['temperature' => 0.5, 'is_platform_default' => true]);
        $config->setRelation('provider', $provider);
        $config->setRelation('model', $model);

        return $config;
    }

    public function test_resolve_provider_switches_purely_on_the_config_row(): void
    {
        $manager = new AIProviderManager([new DeepSeekProvider(), new AnthropicProvider()]);

        $deepseekProvider = $manager->resolveProvider($this->config('deepseek', 'connected'));
        $this->assertSame('deepseek', $deepseekProvider->getName());

        $anthropicProvider = $manager->resolveProvider($this->config('anthropic', 'connected'));
        $this->assertSame('anthropic', $anthropicProvider->getName());

        // Same manager instance, same code path — only the config row changed.
        // This is the literal proof of "switch providers through
        // configuration, not a rewrite" (architecture doc Part II, intro).
    }

    public function test_resolve_provider_rejects_a_disconnected_provider(): void
    {
        $manager = new AIProviderManager([new DeepSeekProvider()]);

        $this->expectException(AIProviderException::class);
        $manager->resolveProvider($this->config('deepseek', 'disabled'));
    }

    public function test_resolve_provider_throws_for_an_unregistered_provider_key(): void
    {
        $manager = new AIProviderManager([new DeepSeekProvider()]);

        $this->expectException(AIProviderException::class);
        $manager->resolveProvider($this->config('some-future-provider', 'connected'));
    }

    public function test_a_model_level_capability_override_narrows_what_gets_sent(): void
    {
        $manager = new AIProviderManager([new DeepSeekProvider()]);

        // DeepSeekProvider::getCapabilities() advertises tool_calling=true,
        // but this specific model row is configured without it.
        $config = $this->config('deepseek', 'connected', ['chat' => true, 'tool_calling' => false]);

        $capabilities = $manager->getCapabilities($config);
        $this->assertFalse($capabilities->toolCalling);

        $request = $manager->createConversationRequest($config, null, [ChatMessage::user('hi')], [
            new \Modules\AIAssistant\app\DataTransfer\AIToolDefinition('search_products', '...', []),
        ]);

        // Tools are withheld from the wire request entirely when the
        // resolved capabilities say the model can't call them.
        $this->assertSame([], $request->tools);
    }

    public function test_tools_are_included_when_the_model_supports_tool_calling(): void
    {
        $manager = new AIProviderManager([new DeepSeekProvider()]);
        $config = $this->config('deepseek', 'connected');

        $request = $manager->createConversationRequest($config, null, [ChatMessage::user('hi')], [
            new \Modules\AIAssistant\app\DataTransfer\AIToolDefinition('search_products', '...', []),
        ]);

        $this->assertCount(1, $request->tools);
    }
}
