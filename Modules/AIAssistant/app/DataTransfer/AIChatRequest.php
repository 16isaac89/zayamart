<?php

namespace Modules\AIAssistant\app\DataTransfer;

final class AIChatRequest
{
    /**
     * @param ChatMessage[] $messages
     * @param AIToolDefinition[] $tools
     */
    public function __construct(
        public readonly string $model,
        public readonly ?string $systemInstructions,
        public readonly array $messages,
        public readonly array $tools = [],
        public readonly ?string $toolChoice = null,
        public readonly float $temperature = 0.3,
        public readonly ?int $maxTokens = null,
    ) {
    }
}
