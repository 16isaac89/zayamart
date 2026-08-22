<?php

namespace Modules\AIAssistant\app\Tools;

use App\Models\Product;
use Modules\AIAssistant\app\Contracts\AIToolInterface;
use Modules\AIAssistant\app\DataTransfer\AIToolResult;
use Modules\AIAssistant\app\DataTransfer\ToolExecutionContext;

class CheckStockTool implements AIToolInterface
{
    public function name(): string
    {
        return 'check_stock';
    }

    public function description(): string
    {
        return 'Check real, current stock for a product (and, if given, a specific variant combination) before promising availability or confirming a quantity.';
    }

    public function parameterSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'product_id' => ['type' => 'integer'],
                'variant_combination' => ['type' => 'string', 'description' => 'Combination string as returned by get_product_variants, if this product has variants'],
                'quantity' => ['type' => 'integer', 'default' => 1],
            ],
            'required' => ['product_id'],
        ];
    }

    public function execute(array $arguments, ToolExecutionContext $context): AIToolResult
    {
        $product = Product::where('id', (int)($arguments['product_id'] ?? 0))
            ->where('user_id', $context->sellerId)
            ->where('added_by', 'seller')
            ->first();

        if (!$product) {
            return AIToolResult::fail('Product not found for this vendor.');
        }

        $requestedQty = (int)($arguments['quantity'] ?? 1);
        $variations = json_decode($product->variation ?: '[]', true) ?: [];

        if (!empty($arguments['variant_combination']) && !empty($variations)) {
            $match = collect($variations)->firstWhere('type', $arguments['variant_combination']);
            if (!$match) {
                return AIToolResult::fail('That variant combination does not exist for this product.');
            }

            return AIToolResult::ok([
                'in_stock' => (int)$match['qty'] >= $requestedQty,
                'available_quantity' => (int)$match['qty'],
                'requested_quantity' => $requestedQty,
            ]);
        }

        return AIToolResult::ok([
            'in_stock' => $product->product_type === 'physical'
                ? (int)$product->current_stock >= $requestedQty
                : true,
            'available_quantity' => (int)$product->current_stock,
            'requested_quantity' => $requestedQty,
            'minimum_order_qty' => (int)$product->minimum_order_qty,
        ]);
    }
}
