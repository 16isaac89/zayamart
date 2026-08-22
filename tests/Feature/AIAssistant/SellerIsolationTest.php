<?php

namespace Tests\Feature\AIAssistant;

use App\Models\Product;
use App\Models\Seller;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Modules\AIAssistant\app\DataTransfer\ToolExecutionContext;
use Modules\AIAssistant\app\Tools\SearchProductsTool;
use Tests\TestCase;

/**
 * Runs against a real, already-installed database (DatabaseTransactions,
 * not RefreshDatabase — this schema is installed via a SQL installer, not
 * buildable from migrations alone, and this project's dev DB is a shared
 * resource; every write here rolls back automatically at the end of each
 * test).
 *
 * Covers the single most safety-critical property in the architecture:
 * seller isolation is enforced in the tool layer regardless of what a
 * (possibly manipulated) tool argument claims — see architecture doc
 * Part II §11 and SearchProductsTool's own docblock.
 */
class SellerIsolationTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Product::scopeActive() (used by ProductManager::search_products(),
     * which this tool wraps) requires request_status = 1 in addition to
     * status = 1 — easy to miss hand-building a fixture; caught by actually
     * running this against a real DB instead of assuming it would pass.
     */
    private function activeProduct(array $attributes): Product
    {
        return Product::create($attributes + [
            'status' => 1,
            'request_status' => 1,
            'unit_price' => 100,
            'current_stock' => 5,
            'minimum_order_qty' => 1,
            'added_by' => 'seller',
            'slug' => 'test-product-' . uniqid(),
        ]);
    }

    public function test_search_products_never_returns_another_sellers_products(): void
    {
        $sellerA = Seller::create(['f_name' => 'A', 'l_name' => 'Vendor', 'phone' => '1', 'email' => 'a@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);
        $sellerB = Seller::create(['f_name' => 'B', 'l_name' => 'Vendor', 'phone' => '2', 'email' => 'b@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);

        $this->activeProduct(['name' => 'A Black Dress', 'user_id' => $sellerA->id]);
        $this->activeProduct(['name' => 'B Black Dress', 'user_id' => $sellerB->id]);

        $context = new ToolExecutionContext(
            sellerId: $sellerA->id,
            aiAgentId: 1,
            conversationId: 1,
            customerId: null,
            guestId: 999,
            isGuest: true,
            request: Request::create('/'),
        );

        $result = (new SearchProductsTool())->execute(['query' => 'Black Dress'], $context);

        $names = collect($result->data['products'])->pluck('name');

        $this->assertTrue($names->contains('A Black Dress'));
        $this->assertFalse($names->contains('B Black Dress'), 'Seller A\'s assistant must never surface Seller B\'s products, even though both matched the search term.');
    }

    public function test_a_forged_seller_id_style_argument_cannot_widen_the_search(): void
    {
        // search_products' parameterSchema doesn't even expose a seller_id
        // argument (architecture doc Part II §4/§11: authorization comes
        // from ToolExecutionContext, never from LLM-supplied arguments) —
        // this asserts that passing one anyway has no effect.
        $sellerA = Seller::create(['f_name' => 'A', 'l_name' => 'V', 'phone' => '1', 'email' => 'a2@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);
        $sellerB = Seller::create(['f_name' => 'B', 'l_name' => 'V', 'phone' => '2', 'email' => 'b2@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);
        // A same-seller "Shoe" too — proves a genuinely matching product
        // exists and is findable, so the zero count for seller B's shoe
        // below is a real isolation result, not just an empty/broken query.
        $this->activeProduct(['name' => 'Sneaker Shoe', 'user_id' => $sellerA->id]);
        $this->activeProduct(['name' => 'Shoe', 'user_id' => $sellerB->id]);

        $context = new ToolExecutionContext($sellerA->id, 1, 1, null, 999, true, Request::create('/'));

        $result = (new SearchProductsTool())->execute(['query' => 'Shoe', 'seller_id' => $sellerB->id], $context);

        $this->assertSame(1, $result->data['count']);
        $this->assertSame('Sneaker Shoe', $result->data['products'][0]['name']);
    }
}
