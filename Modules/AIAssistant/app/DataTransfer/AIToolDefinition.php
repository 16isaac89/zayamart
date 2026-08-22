<?php

namespace Modules\AIAssistant\app\DataTransfer;

/**
 * Provider-neutral shape of one tool, built by ToolRegistry from the
 * registered AIToolInterface instances. Each provider adapter converts this
 * into its own function/tool-calling format inside chat() — see
 * architecture doc Part II §4.
 */
final class AIToolDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $parameterSchema,
    ) {
    }
}
