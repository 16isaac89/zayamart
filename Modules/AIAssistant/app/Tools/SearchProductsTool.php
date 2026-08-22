<?php

namespace Modules\AIAssistant\app\Tools;

use App\Utils\ProductManager;
use Modules\AIAssistant\app\Contracts\AIToolInterface;
use Modules\AIAssistant\app\DataTransfer\AIToolResult;
use Modules\AIAssistant\app\DataTransfer\ToolExecutionContext;

/**
 * Reuses ProductManager::search_products() (marketplace-wide search) and
 * then filters to this conversation's seller — that filter is not
 * optional and does not depend on any argument the model supplied. See
 * architecture doc Part II §11.
 */
class SearchProductsTool implements AIToolInterface
{
    public function name(): string
    {
        return 'search_products';
    }

    public function description(): string
    {
        return "Search this vendor's product catalog by keyword and optional category. Returns real prices/stock — never state a price or availability the tool did not return.";
    }

    public function parameterSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Search keywords, e.g. "black dress"'],
                'category_id' => ['type' => 'integer', 'description' => 'Optional category filter'],
                'limit' => ['type' => 'integer', 'description' => 'Max results, default 10'],
            ],
            'required' => ['query'],
        ];
    }

    public function execute(array $arguments, ToolExecutionContext $context): AIToolResult
    {
        $query = trim((string)($arguments['query'] ?? ''));
        if ($query === '') {
            return AIToolResult::fail('query is required.');
        }

        $limit = min((int)($arguments['limit'] ?? 10), 25);
        $category = $arguments['category_id'] ?? 'all';

        $result = ProductManager::search_products(
            request: $context->request,
            name: $query,
            category: $category,
            limit: 50, // over-fetch before the seller filter, then cap below
            offset: 1,
        );

        $sellerProducts = collect($result['products'])
            ->filter(fn ($product) => (int)$product->user_id === $context->sellerId && $product->added_by === 'seller')
            ->take($limit)
            ->map(fn ($product) => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'unit_price' => (float)$product->unit_price,
                'current_stock' => (int)$product->current_stock,
                'thumbnail' => $product->thumbnail,
                'has_variants' => (int)$product->current_stock >= 0 && !empty($product->choice_options) && $product->choice_options !== '[]',
            ])
            ->values();

        return AIToolResult::ok([
            'products' => $sellerProducts->toArray(),
            'count' => $sellerProducts->count(),
        ]);
    }
}
