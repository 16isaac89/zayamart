<?php

namespace Modules\AIAssistant\app\DataTransfer;

/**
 * What a given provider actually supports. ConversationService checks this
 * before assuming tool calling / streaming / usage reporting are available
 * — see architecture doc Part II §2 ("graceful capability degradation").
 */
final class AIProviderCapabilities
{
    public function __construct(
        public readonly bool $chat = true,
        public readonly bool $systemInstructions = true,
        public readonly bool $toolCalling = false,
        public readonly bool $structuredOutput = false,
        public readonly bool $streaming = false,
        public readonly bool $usageReporting = false,
    ) {
    }

    public function toArray(): array
    {
        return [
            'chat' => $this->chat,
            'system_instructions' => $this->systemInstructions,
            'tool_calling' => $this->toolCalling,
            'structured_output' => $this->structuredOutput,
            'streaming' => $this->streaming,
            'usage_reporting' => $this->usageReporting,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            chat: (bool)($data['chat'] ?? true),
            systemInstructions: (bool)($data['system_instructions'] ?? true),
            toolCalling: (bool)($data['tool_calling'] ?? false),
            structuredOutput: (bool)($data['structured_output'] ?? false),
            streaming: (bool)($data['streaming'] ?? false),
            usageReporting: (bool)($data['usage_reporting'] ?? false),
        );
    }
}
