<?php

namespace Modules\AIAssistant\app\Tools;

use App\Models\Cart;
use App\Utils\CartManager;
use Illuminate\Http\Request;
use Modules\AIAssistant\app\Contracts\AIToolInterface;
use Modules\AIAssistant\app\DataTransfer\AIToolResult;
use Modules\AIAssistant\app\DataTransfer\ToolExecutionContext;

class UpdateCartTool implements AIToolInterface
{
    public function name(): string
    {
        return 'update_cart';
    }

    public function description(): string
    {
        return 'Change the quantity of an item already in the cart.';
    }

    public function parameterSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'cart_item_id' => ['type' => 'integer', 'description' => 'From get_cart'],
                'quantity' => ['type' => 'integer', 'minimum' => 1],
            ],
            'required' => ['cart_item_id', 'quantity'],
        ];
    }

    public function execute(array $arguments, ToolExecutionContext $context): AIToolResult
    {
        // CartManager::update_cart_qty() only checks customer ownership, not
        // seller — so this tool enforces the seller scope itself before
        // delegating, exactly as the architecture doc's security model
        // requires (Part II §11).
        $owned = GetCartTool::sellerCartQuery($context)
            ->where('id', (int)($arguments['cart_item_id'] ?? 0))
            ->exists();

        if (!$owned) {
            return AIToolResult::fail('Cart item not found for this vendor.');
        }

        $result = CartManager::update_cart_qty(new Request([
            'key' => (int)$arguments['cart_item_id'],
            'quantity' => max(1, (int)($arguments['quantity'] ?? 1)),
            'buy_now' => 0,
            'guest_id' => $context->guestId,
        ]));

        if (($result['status'] ?? 0) != 1) {
            return AIToolResult::fail($result['message'] ?? 'Could not update cart.');
        }

        return AIToolResult::ok(['quantity' => $result['qty']]);
    }
}
