<?php

namespace Modules\RealEstate\app\Mail;

use App\Models\Currency;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Modules\RealEstate\app\Models\RealEstateListing;

/**
 * Email counterpart to the in-app/PWA "real_estate_inquiry" vendor
 * notification — same event, same per-vendor opt-in/out via
 * VendorNotificationSetting, just a different channel. See
 * App\Mail\VendorOrderNotificationMail for the equivalent on orders.
 */
class RealEstateInquiryReceived extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param Collection<int, RealEstateListing> $listings
     */
    public function __construct(
        private readonly string $guestName,
        private readonly string $guestPhone,
        private readonly ?string $guestEmail,
        private readonly string $inquiryMessage,
        private readonly Collection $listings,
    ) {
    }

    public function build(): self
    {
        $listings = $this->listings->map(fn (RealEstateListing $listing) => [
            'title' => $listing->title,
            'price' => (float)$listing->price,
            'url' => route('real-estate.show', $listing->slug),
        ])->all();

        return $this->subject(translate('New_real_estate_inquiry'))
            ->view('email-templates.vendor-real-estate-inquiry', [
                'guestName' => $this->guestName,
                'guestPhone' => $this->guestPhone,
                'guestEmail' => $this->guestEmail,
                'inquiryMessage' => $this->inquiryMessage,
                'listings' => $listings,
                'currencySymbol' => Currency::find(getWebConfig(name: 'system_default_currency'))?->symbol ?? '',
            ]);
    }
}
