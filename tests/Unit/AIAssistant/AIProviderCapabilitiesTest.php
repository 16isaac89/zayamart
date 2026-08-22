<?php

namespace Tests\Unit\AIAssistant;

use Modules\AIAssistant\app\DataTransfer\AIProviderCapabilities;
use PHPUnit\Framework\TestCase;

/**
 * Pure PHP — no Laravel container needed, so this runs even where the app
 * itself can't fully boot (this sandbox has no configured database).
 */
class AIProviderCapabilitiesTest extends TestCase
{
    public function test_defaults_are_conservative(): void
    {
        $capabilities = new AIProviderCapabilities();

        $this->assertTrue($capabilities->chat);
        $this->assertTrue($capabilities->systemInstructions);
        $this->assertFalse($capabilities->toolCalling);
        $this->assertFalse($capabilities->structuredOutput);
        $this->assertFalse($capabilities->streaming);
        $this->assertFalse($capabilities->usageReporting);
    }

    public function test_from_array_round_trips_through_to_array(): void
    {
        $original = new AIProviderCapabilities(
            chat: true,
            systemInstructions: true,
            toolCalling: true,
            structuredOutput: false,
            streaming: true,
            usageReporting: true,
        );

        $rebuilt = AIProviderCapabilities::fromArray($original->toArray());

        $this->assertEquals($original, $rebuilt);
    }

    public function test_from_array_defaults_missing_keys_to_false_except_chat_and_system(): void
    {
        $capabilities = AIProviderCapabilities::fromArray([]);

        $this->assertTrue($capabilities->chat);
        $this->assertTrue($capabilities->systemInstructions);
        $this->assertFalse($capabilities->toolCalling);
    }

    public function test_a_model_level_override_can_narrow_capabilities(): void
    {
        // e.g. a cheaper model under the same provider that can't call tools —
        // architecture doc Part II §2/§5's "model-level capability override".
        $narrowed = AIProviderCapabilities::fromArray(['tool_calling' => false, 'chat' => true]);

        $this->assertTrue($narrowed->chat);
        $this->assertFalse($narrowed->toolCalling);
    }
}
