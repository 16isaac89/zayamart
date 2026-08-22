<?php

namespace Modules\AIAssistant\app\Tools;

use App\Models\Cart;
use Modules\AIAssistant\app\Contracts\AIToolInterface;
use Modules\AIAssistant\app\DataTransfer\AIToolResult;
use Modules\AIAssistant\app\DataTransfer\ToolExecutionContext;

class GetCartTool implements AIToolInterface
{
    public function name(): string
    {
        return 'get_cart';
    }

    public function description(): string
    {
        return "Get the customer's current cart items for this vendor, with real prices and line totals.";
    }

    public function parameterSchema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass()];
    }

    public function execute(array $arguments, ToolExecutionContext $context): AIToolResult
    {
        $items = $this->sellerCartQuery($context)->get();

        return AIToolResult::ok([
            'items' => $items->map(fn (Cart $item) => [
                'cart_item_id' => $item->id,
                'product_id' => $item->product_id,
                'name' => $item->name,
                'variant' => $item->variant,
                'quantity' => $item->quantity,
                'unit_price' => (float)$item->price,
                'line_total' => (float)$item->price * $item->quantity,
            ])->values()->toArray(),
            'item_count' => $items->count(),
        ]);
    }

    public static function sellerCartQuery(ToolExecutionContext $context)
    {
        return Cart::where('seller_id', $context->sellerId)
            ->where('customer_id', $context->isGuest ? $context->guestId : $context->customerId)
            ->where('is_guest', $context->isGuest ? 1 : 0);
    }
}
