<?php

namespace Modules\RealEstate\app\Services;

use App\Jobs\DispatchVendorNotificationJob;
use App\Models\Seller;
use App\Models\VendorNotification;
use App\Models\VendorNotificationSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\RealEstate\app\Mail\RealEstateInquiryReceived;
use Modules\RealEstate\app\Models\RealEstateInquiry;

/**
 * Single entrypoint for notifying a vendor about a new real estate
 * inquiry, used by both the public web form (ListingController) and the AI
 * chat's submit_real_estate_inquiry tool, so the two surfaces never drift
 * — one dispatches the queued in-app/PWA job (same as any other vendor
 * notification), the other sends the email counterpart directly, gated by
 * the same per-vendor VendorNotificationSetting the order flow uses.
 */
class RealEstateInquiryNotifier
{
    /**
     * @param Collection<int, RealEstateInquiry> $inquiries all sharing the
     *        same seller_id/guest_name/guest_phone/guest_email/message —
     *        one row per listing, from a single inquiry submission.
     */
    public function notify(Collection $inquiries): void
    {
        if ($inquiries->isEmpty()) {
            return;
        }

        $first = $inquiries->first();
        $first->loadMissing('listing');
        $sellerId = $first->seller_id;

        $titles = $inquiries->map(fn (RealEstateInquiry $inquiry) => $inquiry->loadMissing('listing')->listing?->title)
            ->filter()
            ->implode(', ');

        DispatchVendorNotificationJob::dispatch(
            $sellerId,
            VendorNotification::TYPE_REAL_ESTATE_INQUIRY,
            'New real estate inquiry',
            "{$first->guest_name} is interested in \"{$titles}\".",
            'real_estate_inquiry',
            $first->id,
            route('vendor.real-estate.inquiries.show', $first->id),
            ['listing_ids' => $inquiries->pluck('listing_id')->all(), 'inquiry_ids' => $inquiries->pluck('id')->all()],
        );

        $this->sendEmail($inquiries, $sellerId);
    }

    private function sendEmail(Collection $inquiries, int $sellerId): void
    {
        $settings = VendorNotificationSetting::where('seller_id', $sellerId)->first();
        $enabled = $settings
            ? $settings->isEnabled(VendorNotification::TYPE_REAL_ESTATE_INQUIRY, 'email')
            : (bool)data_get(config('notifications.default_preferences', []), 'real_estate_inquiry.email', true);

        if (!$enabled) {
            return;
        }

        $sellerEmail = Seller::where('id', $sellerId)->value('email');
        if (!$sellerEmail) {
            return;
        }

        $first = $inquiries->first();
        $listings = $inquiries->map(fn (RealEstateInquiry $inquiry) => $inquiry->loadMissing('listing')->listing)->filter();

        try {
            Mail::to($sellerEmail)->send(new RealEstateInquiryReceived(
                $first->guest_name,
                $first->guest_phone,
                $first->guest_email,
                $first->message,
                $listings,
            ));
        } catch (\Throwable $exception) {
            Log::warning('RealEstateInquiryNotifier email failed', ['seller_id' => $sellerId, 'error' => $exception->getMessage()]);
        }
    }
}
