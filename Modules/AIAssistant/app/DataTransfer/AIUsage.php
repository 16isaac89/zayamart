<?php

namespace Modules\AIAssistant\app\DataTransfer;

final class AIUsage
{
    public function __construct(
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly int $cachedTokens = 0,
        public readonly bool $estimated = false,
    ) {
    }
}
