<?php

namespace Modules\AIAssistant\app\Tools;

use Modules\AIAssistant\app\Contracts\AIToolInterface;
use Modules\AIAssistant\app\DataTransfer\AIToolResult;
use Modules\AIAssistant\app\DataTransfer\ToolExecutionContext;

class RemoveFromCartTool implements AIToolInterface
{
    public function name(): string
    {
        return 'remove_from_cart';
    }

    public function description(): string
    {
        return 'Remove an item from the cart.';
    }

    public function parameterSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'cart_item_id' => ['type' => 'integer', 'description' => 'From get_cart'],
            ],
            'required' => ['cart_item_id'],
        ];
    }

    public function execute(array $arguments, ToolExecutionContext $context): AIToolResult
    {
        $deleted = GetCartTool::sellerCartQuery($context)
            ->where('id', (int)($arguments['cart_item_id'] ?? 0))
            ->delete();

        if (!$deleted) {
            return AIToolResult::fail('Cart item not found for this vendor.');
        }

        return AIToolResult::ok(['removed' => true]);
    }
}
