<?php

namespace App\Services;

use App\Contracts\WhatsAppProviderInterface;
use App\DataTransfer\WhatsAppCredentials;
use App\DataTransfer\WhatsAppOrderMessage;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Seller;
use App\Models\VendorWhatsAppSetting;
use App\Models\WhatsAppNotification;

/**
 * The only thing OrderPlacedEvent's listener talks to. It formats what
 * OrderManager already computed (architecture doc Part II §8) — it never
 * recomputes a price, and it never touches a provider SDK directly.
 */
class WhatsAppService
{
    public function __construct(private readonly WhatsAppProviderInterface $provider)
    {
    }

    public function sendOrderNotification(Order $order): void
    {
        // Unique (order_id, seller_id) row is the idempotency guard — see
        // architecture doc Part II §12. firstOrCreate is itself safe under
        // the table's unique index even if two workers race this call.
        $notification = WhatsAppNotification::firstOrCreate(
            ['order_id' => $order->id, 'seller_id' => $order->seller_id],
            ['whatsapp_provider' => 'meta_cloud', 'status' => WhatsAppNotification::STATUS_PENDING],
        );

        if ($notification->status === WhatsAppNotification::STATUS_SENT) {
            return;
        }

        $vendorPhone = $this->resolveVendorPhone($order);
        if (!$vendorPhone) {
            $notification->update(['status' => WhatsAppNotification::STATUS_FAILED, 'last_error' => 'No vendor WhatsApp number on file.']);
            return;
        }

        $message = $this->buildMessage($order);
        $credentials = $this->resolveVendorCredentials($order->seller_id);

        $notification->increment('attempts');
        $result = $this->provider->sendOrderNotification($vendorPhone, $message, $credentials);

        $notification->update($result->success
            ? ['status' => WhatsAppNotification::STATUS_SENT, 'provider_message_id' => $result->providerMessageId, 'last_error' => null]
            : ['status' => WhatsAppNotification::STATUS_FAILED, 'last_error' => $result->errorMessage]);
    }

    /**
     * A vendor with their own connected WhatsApp Cloud API credentials
     * (brief §21) is used instead of the platform's — vendor isolation
     * here means Seller A's order is never sent through Seller B's
     * credentials, and never through the platform's if the vendor has
     * their own configured.
     */
    private function resolveVendorCredentials(int $sellerId): ?WhatsAppCredentials
    {
        $settings = VendorWhatsAppSetting::where('seller_id', $sellerId)->where('status', 'connected')->first();

        if (!$settings || !$settings->access_token || !$settings->phone_number_id) {
            return null;
        }

        return new WhatsAppCredentials(accessToken: $settings->access_token, phoneNumberId: $settings->phone_number_id);
    }

    private function resolveVendorPhone(Order $order): ?string
    {
        return $this->resolveVendorPhoneBySellerId($order->seller_id);
    }

    /**
     * Public — also used by Modules\AIAssistant\app\Services\WhatsAppLinkService
     * to build a "click to chat" wa.me link, which needs only this phone
     * number and no Cloud API credentials at all.
     */
    public function resolveVendorPhoneBySellerId(int $sellerId): ?string
    {
        $seller = Seller::with('shop')->find($sellerId);

        return $seller?->shop?->contact ?: $seller?->phone;
    }

    private function buildMessage(Order $order): WhatsAppOrderMessage
    {
        $order->loadMissing('details');
        $address = (object)($order->shipping_address_data ?? []);

        $items = $order->details->map(function ($detail) {
            $snapshot = json_decode((string)$detail->product_details, true);
            return [
                'name' => $snapshot['name'] ?? ('Product #' . $detail->product_id),
                'quantity' => (int)$detail->qty,
            ];
        })->all();

        return new WhatsAppOrderMessage(
            orderId: $order->id,
            customerName: $address->contact_person_name ?? $order->customer?->name ?? 'Customer',
            customerPhone: $address->phone ?? $order->customer?->phone ?? '',
            deliveryAddress: $address->address ?? '',
            items: $items,
            total: (float)$order->order_amount,
            currencySymbol: $this->currencySymbol(),
            status: $order->order_status,
        );
    }

    /**
     * Deliberately not the session-bound currency_symbol() helper
     * (app/Utils/Helpers.php) — this method runs inside a queued listener
     * with no bound session (architecture doc Part II §10/§13), where that
     * helper would silently read an empty session instead of throwing.
     * getWebConfig()/Currency are cache/DB-backed and safe in a worker.
     */
    private function currencySymbol(): string
    {
        return Currency::find(getWebConfig(name: 'system_default_currency'))?->symbol ?? '';
    }
}
