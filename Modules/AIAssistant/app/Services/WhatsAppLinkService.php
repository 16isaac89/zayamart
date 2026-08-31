<?php

namespace Modules\AIAssistant\app\Services;

use App\Models\Currency;
use App\Models\Order;
use App\Services\WhatsAppService;
use Illuminate\Support\Collection;

/**
 * Builds "click to chat" wa.me links — https://faq.whatsapp.com/general/chats/how-to-use-click-to-chat
 * — so the customer sends the message themselves from their own WhatsApp
 * app or web client. Deliberately bypasses WhatsAppService's Meta Cloud API
 * push entirely: that path needs the vendor to connect Cloud API
 * credentials (VendorWhatsAppSetting) and to opt in per-event
 * (VendorNotificationSetting), while this only needs the vendor's phone
 * number, which is already on file for every seller.
 */
class WhatsAppLinkService
{
    public function __construct(private readonly WhatsAppService $whatsAppService)
    {
    }

    /**
     * @param Collection<int, Order> $orders one or more orders sharing an order_group_id, all for the same seller
     */
    public function checkoutLink(Collection $orders, string $customerName): ?string
    {
        if ($orders->isEmpty()) {
            return null;
        }

        $phone = $this->whatsAppService->resolveVendorPhoneBySellerId($orders->first()->seller_id);
        if (!$phone) {
            return null;
        }

        return $this->buildLink($phone, $this->checkoutMessage($orders, $customerName));
    }

    public function inquiryLink(int $sellerId, ?string $topic): ?string
    {
        $phone = $this->whatsAppService->resolveVendorPhoneBySellerId($sellerId);
        if (!$phone) {
            return null;
        }

        $text = $topic
            ? "Hi! I have a question: {$topic}"
            : 'Hi! I have a question about your store.';

        return $this->buildLink($phone, $text);
    }

    private function checkoutMessage(Collection $orders, string $customerName): string
    {
        $orders->each(fn (Order $order) => $order->loadMissing('details'));

        $itemLines = $orders->flatMap(fn (Order $order) => $order->details)
            ->map(function ($detail) {
                $snapshot = json_decode((string)$detail->product_details, true);
                $name = $snapshot['name'] ?? ('Product #' . $detail->product_id);
                return "- {$name} x{$detail->qty}";
            })
            ->implode("\n");

        $orderIds = $orders->pluck('id')->implode(', #');
        $total = $orders->sum('order_amount');

        return <<<TEXT
            Hi! I'd like to confirm the order I just placed via chat (order #{$orderIds}):

            {$itemLines}

            Total: {$this->currencySymbol()}{$total}
            Name: {$customerName}
            TEXT;
    }

    private function buildLink(string $phone, string $text): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (!$digits) {
            return null;
        }

        return 'https://wa.me/' . $digits . '?text=' . rawurlencode($text);
    }

    private function currencySymbol(): string
    {
        return Currency::find(getWebConfig(name: 'system_default_currency'))?->symbol ?? '';
    }
}
