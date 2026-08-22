<?php

namespace Tests\Feature\AIAssistant;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\Seller;
use App\Models\ShippingAddress;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Modules\AIAssistant\app\DataTransfer\ToolExecutionContext;
use Modules\AIAssistant\app\Models\AIAgent;
use Modules\AIAssistant\app\Models\AIConversation;
use Modules\AIAssistant\app\Services\AICheckoutService;
use Tests\TestCase;

/**
 * See SellerIsolationTest's note on why this uses DatabaseTransactions
 * against the project's real installed DB rather than RefreshDatabase.
 *
 * Covers architecture doc Part II §12: a repeated tool call, HTTP retry, or
 * queue retry must never create a second order for the same confirmed
 * conversation.
 */
class CheckoutIdempotencyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_calling_confirm_order_twice_creates_only_one_order(): void
    {
        $seller = Seller::create(['f_name' => 'A', 'l_name' => 'V', 'phone' => '1', 'email' => 'idem@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);
        $guestId = 555;

        // Product::scopeActive() (used deep inside OrderManager's cart
        // lookup) requires request_status = 1 in addition to status = 1 —
        // easy to miss when hand-building a fixture; caught by actually
        // running this against a real DB instead of assuming it would pass.
        $product = Product::create([
            'name' => 'Idempotency Test Product', 'user_id' => $seller->id, 'added_by' => 'seller',
            'status' => 1, 'request_status' => 1, 'unit_price' => 100, 'current_stock' => 10,
            'minimum_order_qty' => 1, 'slug' => 'idempotency-test-product-' . uniqid(),
        ]);

        Cart::create([
            'customer_id' => $guestId, 'is_guest' => 1, 'cart_group_id' => 'guest-abc',
            'product_id' => $product->id, 'seller_id' => $seller->id, 'seller_is' => 'seller',
            'quantity' => 1, 'price' => 100, 'is_checked' => 1,
        ]);

        $address = ShippingAddress::create(['customer_id' => $guestId, 'is_guest' => 1, 'contact_person_name' => 'Guest', 'phone' => '123', 'address' => 'Somewhere', 'city' => 'Kampala', 'country' => 'Uganda']);

        $agent = AIAgent::create(['seller_id' => $seller->id, 'status' => true]);
        $conversation = AIConversation::create([
            'seller_id' => $seller->id, 'ai_agent_id' => $agent->id, 'guest_id' => $guestId,
            'mode' => 'checkout', 'status' => 'awaiting_confirmation',
        ]);

        $context = new ToolExecutionContext($seller->id, $agent->id, $conversation->id, null, $guestId, true, Request::create('/'));
        $service = app(AICheckoutService::class);

        $first = $service->confirmOrder($conversation, $context, ['address_id' => $address->id]);
        $second = $service->confirmOrder($conversation->fresh(), $context, ['address_id' => $address->id]);

        $this->assertSame($first['order_group_id'], $second['order_group_id']);
        $this->assertTrue($second['already_confirmed'] ?? false);
        $this->assertSame(1, Order::where('seller_id', $seller->id)->count());
    }
}
