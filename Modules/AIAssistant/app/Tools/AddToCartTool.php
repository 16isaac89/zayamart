<?php

namespace Modules\AIAssistant\app\Tools;

use App\Models\Product;
use App\Utils\CartManager;
use Illuminate\Http\Request;
use Modules\AIAssistant\app\Contracts\AIToolInterface;
use Modules\AIAssistant\app\DataTransfer\AIToolResult;
use Modules\AIAssistant\app\DataTransfer\ToolExecutionContext;

/**
 * Delegates to the existing CartManager::add_to_cart() — the same static
 * method the storefront's CartController calls — rather than reimplementing
 * stock/variant/price validation. See architecture doc Part II §9 ("reuse,
 * never a parallel system").
 *
 * v1 limitation: variant_selections covers named choice_options (e.g. size)
 * — a dedicated colour-swatch selection needs the vendor's actual Color
 * codes, which get_product_variants does not yet expose. Products using
 * only choice_options work end to end; colour-swatch-only products should
 * fall back to a human handoff for now.
 */
class AddToCartTool implements AIToolInterface
{
    public function name(): string
    {
        return 'add_to_cart';
    }

    public function description(): string
    {
        return 'Add a product (with quantity and, if applicable, variant selections already confirmed with the customer) to their cart for this vendor. Always confirm stock via check_stock first.';
    }

    public function parameterSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'product_id' => ['type' => 'integer'],
                'quantity' => ['type' => 'integer', 'minimum' => 1],
                'variant_selections' => [
                    'type' => 'object',
                    'description' => 'Map of choice name (from get_product_variants) to the value the customer chose, e.g. {"Size": "L"}',
                    'additionalProperties' => ['type' => 'string'],
                ],
            ],
            'required' => ['product_id', 'quantity'],
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

        $payload = [
            'id' => $product->id,
            'quantity' => max(1, (int)($arguments['quantity'] ?? 1)),
            'buy_now' => 0,
            'guest_id' => $context->guestId,
        ];

        foreach ((array)($arguments['variant_selections'] ?? []) as $choiceName => $value) {
            $payload[$choiceName] = $value;
        }

        $syntheticRequest = new Request($payload);
        // getCustomerInformation() inside CartManager checks auth('customer')
        // first, which is already resolved from the real request/session —
        // the synthetic request only needs to carry the cart-specific fields
        // add_to_cart() reads via array access.

        $result = CartManager::add_to_cart($syntheticRequest);

        if (($result['status'] ?? 0) != 1) {
            return AIToolResult::fail($result['message'] ?? 'Could not add product to cart.');
        }

        return AIToolResult::ok([
            'cart_item_id' => $result['cart']['id'] ?? null,
            'message' => $result['message'] ?? 'Added to cart.',
        ]);
    }
}
