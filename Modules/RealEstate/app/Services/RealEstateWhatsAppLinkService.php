<?php

namespace Modules\RealEstate\app\Services;

use App\Models\Currency;
use App\Services\WhatsAppService;
use Modules\RealEstate\app\Models\RealEstateListing;

/**
 * "Click to chat" wa.me link so a customer messages the broker directly
 * from their own WhatsApp — same approach as
 * Modules\AIAssistant\app\Services\WhatsAppLinkService, but this module
 * depends only on the core WhatsAppService (resolveVendorPhoneBySellerId),
 * not on the AIAssistant module.
 */
class RealEstateWhatsAppLinkService
{
    public function __construct(private readonly WhatsAppService $whatsAppService)
    {
    }

    public function inquiryLink(RealEstateListing $listing, string $listingUrl): ?string
    {
        $phone = $this->whatsAppService->resolveVendorPhoneBySellerId($listing->seller_id);
        if (!$phone) {
            return null;
        }

        $text = "Hi! I'm interested in this listing: {$listing->title} ({$this->currencySymbol()}{$listing->price}).\n{$listingUrl}";

        return $this->buildLink($phone, $text);
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
