<?php

namespace App\Listeners;

use App\Events\OrderPlacedEvent;
use App\Models\Order;
use App\Models\VendorNotification;
use App\Models\VendorNotificationSetting;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Fires for every order — placed through the storefront, the mobile app, or
 * the AI assistant alike — because it hooks the existing OrderPlacedEvent
 * that OrderManager::generateOrder() already dispatches, unmodified. See
 * architecture doc Part II §8/§9.
 *
 * Unlike the existing listeners on this event (OrderPlacedListener, sync),
 * this one is queued — an external WhatsApp API call has no business
 * running inside the request that just placed the order. See architecture
 * doc Part II §13.
 *
 * WhatsApp is now an OPTIONAL secondary channel (notification architecture
 * report, §10/§36): a vendor must explicitly enable it per event via
 * VendorNotificationSetting — defaulting OFF (config('notifications.default_preferences'))
 * — on top of already having connected credentials. Neither this gate nor
 * a WhatsApp send failure can affect the order, which was already created
 * before this queued listener even runs.
 *
 * handle() is wrapped in its own try/catch: this project's default
 * QUEUE_CONNECTION is 'sync', under which a ShouldQueue listener runs
 * in-process and an uncaught exception propagates synchronously all the
 * way back to whatever called event() — which, for an AI-originated order,
 * is AICheckoutService::confirmOrder() running inside a DB::transaction().
 * WhatsAppService already catches its own internal failures and records
 * them as a failed WhatsAppNotification row, but this outer guard covers
 * anything unexpected (e.g. a DB error while resolving settings) too —
 * see the notification architecture report, "important failure behavior".
 */
class WhatsAppOrderNotificationListener implements ShouldQueue
{
    public string $connection;

    public function __construct(private readonly WhatsAppService $whatsAppService)
    {
        $this->connection = config('aiassistant.queue_connection', config('queue.default'));
    }

    public function handle(OrderPlacedEvent $event): void
    {
        try {
            $this->notify($event);
        } catch (\Throwable $exception) {
            Log::warning('WhatsAppOrderNotificationListener failed', ['error' => $exception->getMessage()]);
        }
    }

    private function notify(OrderPlacedEvent $event): void
    {
        // OrderPlacedEvent is dispatched multiple times per order — once
        // per notification recipient (seller/customer/promoter) and again
        // for mail — see OrderManager::generateOrder(). Only the
        // seller-facing "new order" notification is a WhatsApp trigger.
        if (!$event->notification || ($event->notification->type ?? null) !== 'seller') {
            return;
        }

        $order = $event->notification->order;
        if (!($order instanceof Order)) {
            return;
        }

        $settings = VendorNotificationSetting::where('seller_id', $order->seller_id)->first();
        $enabled = $settings
            ? $settings->isEnabled(VendorNotification::TYPE_NEW_ORDER, 'whatsapp')
            : (bool)data_get(config('notifications.default_preferences', []), 'new_order.whatsapp', false);

        if (!$enabled) {
            return;
        }

        $this->whatsAppService->sendOrderNotification($order);
    }
}
