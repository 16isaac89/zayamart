<?php

namespace App\Listeners;

use App\Events\OrderPlacedEvent;
use App\Jobs\DispatchVendorNotificationJob;
use App\Models\Currency;
use App\Models\Order;
use App\Models\VendorNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * "New Order" — fires alongside the existing OrderPlacedListener (native
 * app push, synchronous) without touching it, exactly like
 * WhatsAppOrderNotificationListener does. Queued: an order must never wait
 * on in-app/PWA notification delivery — see the notification architecture
 * report.
 *
 * handle() is wrapped in its own try/catch deliberately: this project's
 * default QUEUE_CONNECTION is 'sync', under which a ShouldQueue listener
 * runs in-process and an uncaught exception propagates synchronously all
 * the way back to whatever called event() — which, for an AI-originated
 * order, is AICheckoutService::confirmOrder() running inside a
 * DB::transaction(). An unguarded exception here would roll back an
 * already-successful order. See the notification architecture report,
 * "important failure behavior".
 */
class NotifyVendorOfOrderListener implements ShouldQueue
{
    public function handle(OrderPlacedEvent $event): void
    {
        try {
            $this->notify($event);
        } catch (\Throwable $exception) {
            Log::warning('NotifyVendorOfOrderListener failed', ['error' => $exception->getMessage()]);
        }
    }

    private function notify(OrderPlacedEvent $event): void
    {
        // Same filter WhatsAppOrderNotificationListener uses — this event
        // fires once per notification recipient (seller/customer/promoter)
        // per vendor order; only the seller-facing one is ours.
        if (!$event->notification || ($event->notification->type ?? null) !== 'seller') {
            return;
        }

        $order = $event->notification->order;
        if (!($order instanceof Order)) {
            return;
        }

        DispatchVendorNotificationJob::dispatch(
            $order->seller_id,
            VendorNotification::TYPE_NEW_ORDER,
            'New Order #' . $order->id,
            $this->summaryLine($order),
            'order',
            $order->id,
            route('vendor.orders.details', ['id' => $order->id]),
            ['order_id' => $order->id, 'order_amount' => (float)$order->order_amount],
        );
    }

    private function summaryLine(Order $order): string
    {
        $order->loadMissing(['customer', 'details']);
        $customerName = trim((string)($order->customer?->f_name . ' ' . $order->customer?->l_name)) ?: 'A customer';
        $firstItem = $order->details->first();
        $itemName = $firstItem ? (json_decode((string)$firstItem->product_details, true)['name'] ?? 'an item') : 'an item';
        $extra = $order->details->count() > 1 ? ' and ' . ($order->details->count() - 1) . ' more' : '';

        return "{$customerName} ordered {$itemName}{$extra} — " . $this->formatAmount((float)$order->order_amount);
    }

    /**
     * Deliberately not webCurrencyConverter() — that helper reads
     * session('currency_exchange_rate')/session('usd'), and this listener
     * runs in a queued job with no bound session (same class of issue
     * fixed in WhatsAppService::currencySymbol() — see the Part II/III
     * architecture report). getWebConfig()/Currency are cache/DB-backed
     * and safe in a worker.
     */
    private function formatAmount(float $amount): string
    {
        $symbol = Currency::find(getWebConfig(name: 'system_default_currency'))?->symbol ?? '';

        return $symbol . number_format($amount, 2);
    }
}
