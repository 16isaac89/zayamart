<?php

namespace Modules\AIAssistant\app\Tools;

use App\Models\Order;
use Modules\AIAssistant\app\Contracts\AIToolInterface;
use Modules\AIAssistant\app\DataTransfer\AIToolResult;
use Modules\AIAssistant\app\DataTransfer\ToolExecutionContext;

class GetOrderStatusTool implements AIToolInterface
{
    public function name(): string
    {
        return 'get_order_status';
    }

    public function description(): string
    {
        return "Look up the real status of one of the customer's previous orders with this vendor.";
    }

    public function parameterSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'order_id' => ['type' => 'integer'],
            ],
            'required' => ['order_id'],
        ];
    }

    public function execute(array $arguments, ToolExecutionContext $context): AIToolResult
    {
        $ownerId = $context->isGuest ? $context->guestId : $context->customerId;

        $order = Order::where('id', (int)($arguments['order_id'] ?? 0))
            ->where('seller_id', $context->sellerId)
            ->where('customer_id', $ownerId)
            ->where('is_guest', $context->isGuest ? 1 : 0)
            ->first();

        if (!$order) {
            return AIToolResult::fail('Order not found for this vendor and customer.');
        }

        return AIToolResult::ok([
            'order_id' => $order->id,
            'order_status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'order_amount' => (float)$order->order_amount,
            'expected_delivery_date' => optional($order->expected_delivery_date)->toDateString(),
        ]);
    }
}
