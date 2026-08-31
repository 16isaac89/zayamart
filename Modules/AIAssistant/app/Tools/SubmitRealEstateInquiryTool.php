<?php

namespace Modules\AIAssistant\app\Tools;

use Modules\AIAssistant\app\Contracts\AIToolInterface;
use Modules\AIAssistant\app\DataTransfer\AIToolResult;
use Modules\AIAssistant\app\DataTransfer\ToolExecutionContext;
use Modules\RealEstate\app\Models\RealEstateInquiry;
use Modules\RealEstate\app\Models\RealEstateListing;
use Modules\RealEstate\app\Services\RealEstateInquiryNotifier;
use Modules\RealEstate\app\Services\RealEstateWhatsAppLinkService;

/**
 * Real-estate counterpart to CreateOrderTool: saves the inquiry (one row
 * per listing, same convention as the public web form — see
 * ListingController::storeInquiry), notifies the vendor the same way that
 * form does (RealEstateInquiryNotifier: in-app/PWA + email), and returns a
 * whatsapp_link the customer can click to also reach the vendor directly —
 * mirroring create_order's whatsapp_link so both flows read the same way
 * to the model.
 */
class SubmitRealEstateInquiryTool implements AIToolInterface
{
    public function __construct(
        private readonly RealEstateInquiryNotifier $notifier,
        private readonly RealEstateWhatsAppLinkService $whatsAppLinkService,
    ) {
    }

    public function name(): string
    {
        return 'submit_real_estate_inquiry';
    }

    public function description(): string
    {
        return 'Send the customer\'s inquiry about one or more of this vendor\'s real estate listings to the vendor. Call this only after the customer has given their name, phone number, and what they want to ask, and has confirmed they want it sent.';
    }

    public function parameterSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'listing_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'One or more listing IDs the customer is asking about.',
                ],
                'name' => ['type' => 'string'],
                'phone' => ['type' => 'string'],
                'email' => ['type' => 'string', 'description' => 'Optional.'],
                'message' => ['type' => 'string', 'description' => "What the customer wants to ask or say, e.g. \"Is this still available? Can I view it this weekend?\""],
            ],
            'required' => ['listing_ids', 'name', 'phone', 'message'],
        ];
    }

    public function execute(array $arguments, ToolExecutionContext $context): AIToolResult
    {
        $name = trim((string)($arguments['name'] ?? ''));
        $phone = trim((string)($arguments['phone'] ?? ''));
        $message = trim((string)($arguments['message'] ?? ''));
        $listingIds = array_unique(array_map('intval', (array)($arguments['listing_ids'] ?? [])));

        if ($name === '' || $phone === '' || $message === '') {
            return AIToolResult::fail('name, phone, and message are all required.');
        }

        if (empty($listingIds)) {
            return AIToolResult::fail('listing_ids is required.');
        }

        $listings = RealEstateListing::publiclyVisible()
            ->where('seller_id', $context->sellerId)
            ->whereIn('id', $listingIds)
            ->get();

        if ($listings->isEmpty()) {
            return AIToolResult::fail('None of those listings were found for this vendor.');
        }

        $email = !empty($arguments['email']) ? trim((string)$arguments['email']) : null;
        $customerId = !$context->isGuest ? $context->customerId : null;

        $inquiries = $listings->map(fn (RealEstateListing $listing) => RealEstateInquiry::create([
            'listing_id' => $listing->id,
            'seller_id' => $context->sellerId,
            'customer_id' => $customerId,
            'guest_name' => $name,
            'guest_phone' => $phone,
            'guest_email' => $email,
            'message' => $message,
        ])->setRelation('listing', $listing));

        $this->notifier->notify($inquiries);

        $listingUrls = $listings->mapWithKeys(fn (RealEstateListing $listing) => [$listing->id => route('real-estate.show', $listing->slug)])->all();

        $whatsappLink = $listings->count() === 1
            ? $this->whatsAppLinkService->inquiryLink($listings->first(), $listingUrls[$listings->first()->id])
            : $this->whatsAppLinkService->inquiryLinkForMany($listings, $listingUrls);

        return AIToolResult::ok([
            'inquiry_ids' => $inquiries->pluck('id')->all(),
            'listings_notified' => $listings->pluck('title')->all(),
            'whatsapp_link' => $whatsappLink,
        ]);
    }
}
