<?php

namespace Tests\Feature\Notifications;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\Seller;
use App\Models\ShippingAddress;
use App\Models\VendorNotification;
use App\Models\VendorNotificationSetting;
use App\Models\VendorPushSubscription;
use App\Models\WhatsAppNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Modules\AIAssistant\app\DataTransfer\ToolExecutionContext;
use Modules\AIAssistant\app\Models\AIAgent;
use Modules\AIAssistant\app\Models\AIConversation;
use Modules\AIAssistant\app\Services\AICheckoutService;
use Tests\TestCase;

/**
 * The single most important guarantee in this phase (brief §33): an order
 * must succeed regardless of whether any notification channel succeeds,
 * fails, or is disabled. Runs the REAL order-creation path
 * (AICheckoutService -> OrderManager::generateOrder() -> OrderPlacedEvent)
 * with the actual registered EventServiceProvider listeners — including
 * NotifyVendorOfOrderListener and WhatsAppOrderNotificationListener — live,
 * not mocked, against the real database.
 *
 * See SellerIsolationTest (Modules/AIAssistant) for why DatabaseTransactions,
 * never RefreshDatabase.
 */
class OrderNotificationIndependenceTest extends TestCase
{
    use DatabaseTransactions;

    private function placeOrder(Seller $seller, int $guestId): array
    {
        $product = Product::create([
            'name' => 'Notif Test Product', 'user_id' => $seller->id, 'added_by' => 'seller',
            'status' => 1, 'request_status' => 1, 'unit_price' => 50000, 'current_stock' => 10,
            'minimum_order_qty' => 1, 'slug' => 'notif-test-product-' . uniqid(),
        ]);

        Cart::create([
            'customer_id' => $guestId, 'is_guest' => 1, 'cart_group_id' => 'guest-notif-' . uniqid(),
            'product_id' => $product->id, 'seller_id' => $seller->id, 'seller_is' => 'seller',
            'quantity' => 1, 'price' => 50000, 'is_checked' => 1,
        ]);

        $address = ShippingAddress::create(['customer_id' => $guestId, 'is_guest' => 1, 'contact_person_name' => 'Guest', 'phone' => '123', 'address' => 'Somewhere', 'city' => 'Kampala', 'country' => 'Uganda']);

        $agent = AIAgent::create(['seller_id' => $seller->id, 'status' => true]);
        $conversation = AIConversation::create([
            'seller_id' => $seller->id, 'ai_agent_id' => $agent->id, 'guest_id' => $guestId,
            'mode' => 'checkout', 'status' => 'awaiting_confirmation',
        ]);

        $context = new ToolExecutionContext($seller->id, $agent->id, $conversation->id, null, $guestId, true, Request::create('/'));
        $summary = app(AICheckoutService::class)->confirmOrder($conversation, $context, ['address_id' => $address->id]);

        return [$summary, $seller];
    }

    public function test_order_succeeds_with_no_push_subscriptions_and_whatsapp_disabled_by_default(): void
    {
        $seller = Seller::create(['f_name' => 'A', 'l_name' => 'V', 'phone' => '1', 'email' => 'oia@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);

        [$summary] = $this->placeOrder($seller, 601);

        $this->assertNotEmpty($summary['order_ids']);
        $this->assertDatabaseHas('orders', ['id' => $summary['order_ids'][0], 'seller_id' => $seller->id]);

        // In-app notification fired (default on).
        $this->assertDatabaseHas('vendor_notifications', [
            'seller_id' => $seller->id,
            'type' => VendorNotification::TYPE_NEW_ORDER,
        ]);

        // WhatsApp defaults OFF and no credentials exist — nothing sent,
        // and critically, this did not affect the order above.
        $this->assertSame(0, WhatsAppNotification::where('seller_id', $seller->id)->count());
    }

    public function test_order_succeeds_with_pwa_push_enabled_but_no_registered_devices(): void
    {
        $seller = Seller::create(['f_name' => 'B', 'l_name' => 'V', 'phone' => '2', 'email' => 'oib@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);
        // PWA already defaults on for new_order — no subscriptions exist,
        // so sendPushToAllDevices() iterates zero rows. Must not error.

        [$summary] = $this->placeOrder($seller, 602);

        $this->assertNotEmpty($summary['order_ids']);
        $this->assertDatabaseHas('orders', ['id' => $summary['order_ids'][0]]);
    }

    public function test_order_succeeds_with_whatsapp_explicitly_enabled_but_unconfigured(): void
    {
        $seller = Seller::create(['f_name' => 'C', 'l_name' => 'V', 'phone' => '3', 'email' => 'oic@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);
        VendorNotificationSetting::create([
            'seller_id' => $seller->id,
            'preferences' => ['new_order' => ['in_app' => true, 'pwa' => true, 'whatsapp' => true]],
        ]);
        // No config/services.php whatsapp_cloud credentials and no
        // vendor_whatsapp_settings row exist in this test environment —
        // WhatsAppService::sendOrderNotification() will record a failed
        // WhatsAppNotification row, not throw.

        [$summary] = $this->placeOrder($seller, 603);

        $this->assertNotEmpty($summary['order_ids']);
        $this->assertDatabaseHas('orders', ['id' => $summary['order_ids'][0]]);
        // The order succeeded regardless of what happened to the WhatsApp
        // attempt — that's the property under test, not the WhatsApp
        // outcome itself.
    }

    public function test_order_succeeds_with_a_stale_push_subscription_present(): void
    {
        $seller = Seller::create(['f_name' => 'D', 'l_name' => 'V', 'phone' => '4', 'email' => 'oid@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);
        VendorPushSubscription::create([
            'seller_id' => $seller->id,
            'fcm_token' => 'stale-invalid-token',
            'token_hash' => VendorPushSubscription::hashToken('stale-invalid-token'),
        ]);
        // No real Firebase project is configured in this test environment
        // (business_settings.push_notification_key is a placeholder) — the
        // FCM send will fail internally; PushNotificationTrait's own
        // try/catch (and this listener's outer guard) must absorb that.

        [$summary] = $this->placeOrder($seller, 604);

        $this->assertNotEmpty($summary['order_ids']);
        $this->assertDatabaseHas('orders', ['id' => $summary['order_ids'][0]]);
        $this->assertDatabaseHas('vendor_notifications', ['seller_id' => $seller->id, 'type' => VendorNotification::TYPE_NEW_ORDER]);
    }
}
