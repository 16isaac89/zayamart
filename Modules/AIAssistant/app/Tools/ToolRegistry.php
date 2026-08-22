<?php

namespace Modules\AIAssistant\app\Tools;

use Modules\AIAssistant\app\Contracts\AIToolInterface;
use Modules\AIAssistant\app\DataTransfer\AIToolDefinition;
use Modules\AIAssistant\app\DataTransfer\AIToolResult;
use Modules\AIAssistant\app\DataTransfer\ToolExecutionContext;

/**
 * Holds every registered AIToolInterface. ConversationService asks this for
 * provider-neutral AIToolDefinitions to send with a chat request, and hands
 * back any AIToolCall the provider returned for execution. See architecture
 * doc Part II §4.
 */
class ToolRegistry
{
    /** @var AIToolInterface[] keyed by name() */
    private array $tools = [];

    /**
     * @param AIToolInterface[] $tools
     */
    public function __construct(array $tools)
    {
        foreach ($tools as $tool) {
            $this->tools[$tool->name()] = $tool;
        }
    }

    /**
     * @return AIToolDefinition[]
     */
    public function definitions(): array
    {
        return array_map(
            fn (AIToolInterface $tool) => new AIToolDefinition($tool->name(), $tool->description(), $tool->parameterSchema()),
            array_values($this->tools),
        );
    }

    public function execute(string $toolName, array $arguments, ToolExecutionContext $context): AIToolResult
    {
        $tool = $this->tools[$toolName] ?? null;

        if (!$tool) {
            return AIToolResult::fail("Unknown tool: {$toolName}");
        }

        return $tool->execute($arguments, $context);
    }

    public function has(string $toolName): bool
    {
        return isset($this->tools[$toolName]);
    }
}
