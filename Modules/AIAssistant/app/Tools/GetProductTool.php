<?php

namespace Modules\AIAssistant\app\Tools;

use App\Utils\ProductManager;
use Modules\AIAssistant\app\Contracts\AIToolInterface;
use Modules\AIAssistant\app\DataTransfer\AIToolResult;
use Modules\AIAssistant\app\DataTransfer\ToolExecutionContext;

class GetProductTool implements AIToolInterface
{
    public function name(): string
    {
        return 'get_product';
    }

    public function description(): string
    {
        return 'Get full details for one product by ID, including its real price, description, and stock.';
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
        $product = ProductManager::get_product((int)($arguments['product_id'] ?? 0));

        if (!$product || (int)$product->user_id !== $context->sellerId || $product->added_by !== 'seller') {
            return AIToolResult::fail('Product not found for this vendor.');
        }

        return AIToolResult::ok([
            'id' => $product->id,
            'name' => $product->name,
            'description' => strip_tags((string)$product->description),
            'unit_price' => (float)$product->unit_price,
            'current_stock' => (int)$product->current_stock,
            'minimum_order_qty' => (int)$product->minimum_order_qty,
            'thumbnail' => $product->thumbnail,
            'has_variants' => !empty($product->choice_options) && $product->choice_options !== '[]',
        ]);
    }
}
