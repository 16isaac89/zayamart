<?php

namespace App\Listeners;

use App\Events\OrderPlacedEvent;
use App\Mail\VendorOrderNotificationMail;
use App\Models\Order;
use App\Models\VendorNotification;
use App\Models\VendorNotificationSetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Email counterpart to WhatsAppOrderNotificationListener — same trigger
 * (OrderPlacedEvent, seller-facing "new order" notification only), same
 * per-vendor opt-in/out via VendorNotificationSetting, just a different
 * channel. Unlike WhatsApp, email has no vendor-owned credentials to
 * configure: it goes out through the platform's own mail configuration
 * (see MailConfigServiceProvider) to whatever address is on the seller's
 * account, which is why it defaults ON in config('notifications.default_preferences')
 * while WhatsApp defaults OFF.
 */
class EmailOrderNotificationListener implements ShouldQueue
{
    public string $connection;

    public function __construct()
    {
        $this->connection = config('aiassistant.queue_connection', config('queue.default'));
    }

    public function handle(OrderPlacedEvent $event): void
    {
        try {
            $this->notify($event);
        } catch (\Throwable $exception) {
            Log::warning('EmailOrderNotificationListener failed', ['error' => $exception->getMessage()]);
        }
    }

    private function notify(OrderPlacedEvent $event): void
    {
        if (!$event->notification || ($event->notification->type ?? null) !== 'seller') {
            return;
        }

        $order = $event->notification->order;
        if (!($order instanceof Order)) {
            return;
        }

        $settings = VendorNotificationSetting::where('seller_id', $order->seller_id)->first();
        $enabled = $settings
            ? $settings->isEnabled(VendorNotification::TYPE_NEW_ORDER, 'email')
            : (bool)data_get(config('notifications.default_preferences', []), 'new_order.email', true);

        if (!$enabled) {
            return;
        }

        $sellerEmail = $order->seller()->value('email');
        if (!$sellerEmail) {
            return;
        }

        Mail::to($sellerEmail)->send(new VendorOrderNotificationMail($order));
    }
}
