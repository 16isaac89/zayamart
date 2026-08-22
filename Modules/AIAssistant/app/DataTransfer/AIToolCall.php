<?php

namespace Modules\AIAssistant\app\DataTransfer;

/**
 * Normalized tool call, regardless of which provider requested it — an
 * OpenAI-shaped tool_calls[].function, an Anthropic tool_use content block,
 * and a Gemini functionCall all map into this same shape. See architecture
 * doc Part II §3/§6.
 */
final class AIToolCall
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array $arguments,
        public readonly array $providerMetadata = [],
    ) {
    }
}
