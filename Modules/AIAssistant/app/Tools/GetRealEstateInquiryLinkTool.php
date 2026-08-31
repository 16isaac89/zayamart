<?php

namespace Modules\AIAssistant\app\Tools;

use Modules\AIAssistant\app\Contracts\AIToolInterface;
use Modules\AIAssistant\app\DataTransfer\AIToolResult;
use Modules\AIAssistant\app\DataTransfer\ToolExecutionContext;
use Modules\RealEstate\app\Models\RealEstateListing;
use Modules\RealEstate\app\Services\RealEstateWhatsAppLinkService;

/**
 * Same "click to chat" approach as WhatsAppInquiryTool, but pre-fills the
 * message with the specific listing so the vendor/broker knows exactly
 * which property the customer means.
 */
class GetRealEstateInquiryLinkTool implements AIToolInterface
{
    public function __construct(private readonly RealEstateWhatsAppLinkService $whatsAppLinkService)
    {
    }

    public function name(): string
    {
        return 'get_real_estate_inquiry_link';
    }

    public function description(): string
    {
        return "Generate a WhatsApp link the customer can click to ask this vendor/broker about a specific real estate listing. Opens the customer's own WhatsApp app or web with a message pre-filled — they still have to tap send themselves.";
    }

    public function parameterSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'listing_id' => ['type' => 'integer'],
            ],
            'required' => ['listing_id'],
        ];
    }

    public function execute(array $arguments, ToolExecutionContext $context): AIToolResult
    {
        $listing = RealEstateListing::publiclyVisible()
            ->where('seller_id', $context->sellerId)
            ->where('id', (int)($arguments['listing_id'] ?? 0))
            ->first();

        if (!$listing) {
            return AIToolResult::fail('Listing not found for this vendor.');
        }

        $link = $this->whatsAppLinkService->inquiryLink($listing, route('real-estate.show', $listing->slug));

        if (!$link) {
            return AIToolResult::fail('This vendor has no phone number on file — ask the customer to use "Talk to a human" instead.');
        }

        return AIToolResult::ok(['whatsapp_link' => $link]);
    }
}
