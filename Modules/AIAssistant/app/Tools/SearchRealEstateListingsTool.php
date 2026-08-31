<?php

namespace Modules\AIAssistant\app\Tools;

use Modules\AIAssistant\app\Contracts\AIToolInterface;
use Modules\AIAssistant\app\DataTransfer\AIToolResult;
use Modules\AIAssistant\app\DataTransfer\ToolExecutionContext;
use Modules\RealEstate\app\Models\RealEstateListing;

/**
 * Only meaningful for vendors acting as brokers (RealEstate module) — most
 * vendors have zero rows here and the tool just returns an empty list.
 * Scoped to this conversation's seller_id the same way SearchProductsTool
 * scopes to product ownership: never by an argument the model supplied.
 */
class SearchRealEstateListingsTool implements AIToolInterface
{
    public function name(): string
    {
        return 'search_real_estate_listings';
    }

    public function description(): string
    {
        return "Search this vendor's real estate listings (houses or land, for sale or rent). Returns real prices/availability — never state a price or status the tool did not return. Only useful if this vendor is a real estate broker; an empty result means they have none.";
    }

    public function parameterSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'listing_type' => ['type' => 'string', 'enum' => ['house', 'land'], 'description' => 'Optional filter.'],
                'purpose' => ['type' => 'string', 'enum' => ['sale', 'rent'], 'description' => 'Optional filter.'],
                'city' => ['type' => 'string', 'description' => 'Optional city filter.'],
                'min_price' => ['type' => 'number'],
                'max_price' => ['type' => 'number'],
                'bedrooms' => ['type' => 'integer', 'description' => 'Minimum bedrooms.'],
                'keyword' => ['type' => 'string', 'description' => 'Free-text search over title/description.'],
                'limit' => ['type' => 'integer', 'description' => 'Max results, default 10.'],
            ],
        ];
    }

    public function execute(array $arguments, ToolExecutionContext $context): AIToolResult
    {
        $limit = min((int)($arguments['limit'] ?? 10), 25);

        $listings = RealEstateListing::publiclyVisible()
            ->where('seller_id', $context->sellerId)
            ->when(!empty($arguments['listing_type']), fn ($q) => $q->where('listing_type', $arguments['listing_type']))
            ->when(!empty($arguments['purpose']), fn ($q) => $q->where('purpose', $arguments['purpose']))
            ->when(!empty($arguments['city']), fn ($q) => $q->where('city', 'like', '%' . $arguments['city'] . '%'))
            ->when(isset($arguments['min_price']), fn ($q) => $q->where('price', '>=', (float)$arguments['min_price']))
            ->when(isset($arguments['max_price']), fn ($q) => $q->where('price', '<=', (float)$arguments['max_price']))
            ->when(isset($arguments['bedrooms']), fn ($q) => $q->where('bedrooms', '>=', (int)$arguments['bedrooms']))
            ->when(!empty($arguments['keyword']), function ($q) use ($arguments) {
                $keyword = $arguments['keyword'];
                $q->where(function ($q2) use ($keyword) {
                    $q2->where('title', 'like', "%{$keyword}%")->orWhere('description', 'like', "%{$keyword}%");
                });
            })
            ->latest()
            ->limit($limit)
            ->get();

        return AIToolResult::ok([
            'listings' => $listings->map(fn (RealEstateListing $listing) => [
                'id' => $listing->id,
                'slug' => $listing->slug,
                'title' => $listing->title,
                'listing_type' => $listing->listing_type,
                'purpose' => $listing->purpose,
                'price' => (float)$listing->price,
                'price_period' => $listing->price_period,
                'city' => $listing->city,
                'bedrooms' => $listing->bedrooms,
                'bathrooms' => $listing->bathrooms,
                'area_size' => $listing->area_size,
                'area_unit' => $listing->area_unit,
            ])->values()->toArray(),
            'count' => $listings->count(),
        ]);
    }
}
