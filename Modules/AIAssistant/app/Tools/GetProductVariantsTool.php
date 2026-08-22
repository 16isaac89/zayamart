<?php

namespace Modules\AIAssistant\app\Tools;

use App\Models\Product;
use Modules\AIAssistant\app\Contracts\AIToolInterface;
use Modules\AIAssistant\app\DataTransfer\AIToolResult;
use Modules\AIAssistant\app\DataTransfer\ToolExecutionContext;

class GetProductVariantsTool implements AIToolInterface
{
    public function name(): string
    {
        return 'get_product_variants';
    }

    public function description(): string
    {
        return 'Get the selectable options (e.g. size, colour) for a product and the real price/stock of each combination, so you know exactly what to ask the customer before adding to cart.';
    }

    public function parameterSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'product_id' => ['type' => 'integer'],
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

        $choices = json_decode($product->choice_options ?: '[]', true) ?: [];
        $variations = json_decode($product->variation ?: '[]', true) ?: [];

        return AIToolResult::ok([
            'product_id' => $product->id,
            'choices' => array_map(fn ($choice) => [
                'name' => $choice['name'] ?? null,
                'title' => $choice['title'] ?? ($choice['name'] ?? null),
                'options' => $choice['options'] ?? [],
            ], $choices),
            'combinations' => array_map(fn ($variant) => [
                'combination' => $variant['type'] ?? null,
                'price' => (float)($variant['price'] ?? 0),
                'stock' => (int)($variant['qty'] ?? 0),
            ], $variations),
        ]);
    }
}
