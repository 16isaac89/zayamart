<?php

namespace Modules\AIAssistant\app\Contracts;

use Modules\AIAssistant\app\DataTransfer\AIToolResult;
use Modules\AIAssistant\app\DataTransfer\ToolExecutionContext;

/**
 * One application-level tool per marketplace capability (SearchProducts,
 * CreateOrder, ...). Provider adapters translate parameterSchema() into
 * whatever shape that provider's SDK wants — there is exactly one
 * implementation per capability, never one per provider. See architecture
 * doc Part II §4.
 */
interface AIToolInterface
{
    /**
     * snake_case, stable — sent to every provider verbatim (e.g. 'search_products').
     */
    public function name(): string;

    /**
     * Sent to the LLM verbatim as the tool's description.
     */
    public function description(): string;

    /**
     * Provider-neutral JSON Schema for the tool's arguments.
     */
    public function parameterSchema(): array;

    /**
     * $arguments is untrusted LLM output — validate it here. Authorization
     * (which seller, which customer) comes from $context, never from
     * $arguments — see architecture doc Part II §11.
     */
    public function execute(array $arguments, ToolExecutionContext $context): AIToolResult;
}
