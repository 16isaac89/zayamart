<?php

namespace App\Listeners;

use App\Events\OrderStatusEvent;
use App\Jobs\DispatchVendorNotificationJob;
use App\Models\Order;
use App\Models\VendorNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * "Order Status Changed" (and, for the 'confirmed'/paid-adjacent keys,
 * effectively "Payment Received") — fires alongside the existing
 * OrderStatusListener (native app push, synchronous) without touching it.
 *
 * See NotifyVendorOfOrderListener's docblock for why handle() is wrapped
 * in its own try/catch (QUEUE_CONNECTION=sync propagation risk).
 */
class NotifyVendorOfOrderStatusListener implements ShouldQueue
{
    public function handle(OrderStatusEvent $event): void
    {
        try {
            $this->notify($event);
        } catch (\Throwable $exception) {
            Log::warning('NotifyVendorOfOrderStatusListener failed', ['error' => $exception->getMessage()]);
        }
    }

    private function notify(OrderStatusEvent $event): void
    {
        if ($event->type !== 'seller') {
            return;
        }

        $order = $event->order;
        if (!($order instanceof Order)) {
            return;
        }

        $type = $event->key === 'confirmed'
            ? VendorNotification::TYPE_PAYMENT_RECEIVED
            : VendorNotification::TYPE_ORDER_STATUS_CHANGED;

        DispatchVendorNotificationJob::dispatch(
            $order->seller_id,
            $type,
            'Order #' . $order->id . ' — ' . ucfirst(str_replace('_', ' ', $order->order_status)),
            "Order #{$order->id} is now {$order->order_status}.",
            'order',
            $order->id,
            route('vendor.orders.details', ['id' => $order->id]),
            ['order_id' => $order->id],
        );
    }
}
