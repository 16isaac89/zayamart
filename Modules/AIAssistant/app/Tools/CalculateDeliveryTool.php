<?php

namespace Modules\AIAssistant\app\Tools;

use App\Utils\CartManager;
use Modules\AIAssistant\app\Contracts\AIToolInterface;
use Modules\AIAssistant\app\DataTransfer\AIToolResult;
use Modules\AIAssistant\app\DataTransfer\ToolExecutionContext;

class CalculateDeliveryTool implements AIToolInterface
{
    public function name(): string
    {
        return 'calculate_delivery';
    }

    public function description(): string
    {
        return 'Get the real, current delivery/shipping cost for everything currently in the cart for this vendor. Never estimate a delivery fee yourself.';
    }

    public function parameterSchema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass()];
    }

    public function execute(array $arguments, ToolExecutionContext $context): AIToolResult
    {
        $cartGroupId = GetCartTool::sellerCartQuery($context)->value('cart_group_id');

        if (!$cartGroupId) {
            return AIToolResult::fail('Cart is empty for this vendor — nothing to calculate delivery for.');
        }

        $cost = CartManager::get_shipping_cost($cartGroupId, 'checked');

        return AIToolResult::ok(['delivery_cost' => (float)$cost]);
    }
}
