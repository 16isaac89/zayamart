<?php

namespace Modules\AIAssistant\app\DataTransfer;

final class AIResponse
{
    public const FINISH_STOP = 'stop';
    public const FINISH_TOOL_CALLS = 'tool_calls';
    public const FINISH_LENGTH = 'length';
    public const FINISH_CONTENT_FILTER = 'content_filter';

    /**
     * @param AIToolCall[] $toolCalls
     */
    public function __construct(
        public readonly ?string $content,
        public readonly array $toolCalls,
        public readonly string $finishReason,
        public readonly ?AIUsage $usage,
        public readonly string $provider,
        public readonly string $model,
        public readonly array $metadata = [],
    ) {
    }

    public function hasToolCalls(): bool
    {
        return $this->finishReason === self::FINISH_TOOL_CALLS && count($this->toolCalls) > 0;
    }
}
