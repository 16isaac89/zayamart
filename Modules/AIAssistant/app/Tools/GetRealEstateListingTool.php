<?php

namespace Modules\AIAssistant\app\Tools;

use Modules\AIAssistant\app\Contracts\AIToolInterface;
use Modules\AIAssistant\app\DataTransfer\AIToolResult;
use Modules\AIAssistant\app\DataTransfer\ToolExecutionContext;
use Modules\RealEstate\app\Models\RealEstateListing;

class GetRealEstateListingTool implements AIToolInterface
{
    public function name(): string
    {
        return 'get_real_estate_listing';
    }

    public function description(): string
    {
        return 'Get full details for one of this vendor\'s real estate listings by ID, including its description, address, and amenities.';
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

        return AIToolResult::ok([
            'id' => $listing->id,
            'slug' => $listing->slug,
            'title' => $listing->title,
            'description' => strip_tags((string)$listing->description),
            'listing_type' => $listing->listing_type,
            'purpose' => $listing->purpose,
            'price' => (float)$listing->price,
            'price_period' => $listing->price_period,
            'address' => $listing->address,
            'city' => $listing->city,
            'state' => $listing->state,
            'country' => $listing->country,
            'area_size' => $listing->area_size,
            'area_unit' => $listing->area_unit,
            'bedrooms' => $listing->bedrooms,
            'bathrooms' => $listing->bathrooms,
            'floors' => $listing->floors,
            'year_built' => $listing->year_built,
            'parking_spaces' => $listing->parking_spaces,
            'furnished' => $listing->furnished,
            'amenities' => $listing->amenities,
        ]);
    }
}
