<?php

namespace Tests\Feature\Notifications;

use App\Models\Seller;
use App\Models\VendorPushSubscription;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Runs against the real, already-installed database — see
 * SellerIsolationTest (Modules/AIAssistant) for why DatabaseTransactions,
 * never RefreshDatabase, is used throughout this project's tests.
 *
 * Covers brief §6/§7: multi-device subscriptions, vendor isolation.
 */
class PushSubscriptionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_registering_a_device_creates_a_subscription(): void
    {
        $seller = Seller::create(['f_name' => 'A', 'l_name' => 'V', 'phone' => '1', 'email' => 'psa@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);
        $this->actingAs($seller, 'seller');

        $response = $this->postJson(route('vendor.push-subscriptions.store'), [
            'token' => 'fcm-token-device-1',
            'device_type' => 'web',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('vendor_push_subscriptions', [
            'seller_id' => $seller->id,
            'token_hash' => VendorPushSubscription::hashToken('fcm-token-device-1'),
        ]);
    }

    public function test_a_vendor_can_register_multiple_devices(): void
    {
        $seller = Seller::create(['f_name' => 'B', 'l_name' => 'V', 'phone' => '2', 'email' => 'psb@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);
        $this->actingAs($seller, 'seller');

        $this->postJson(route('vendor.push-subscriptions.store'), ['token' => 'device-a'])->assertOk();
        $this->postJson(route('vendor.push-subscriptions.store'), ['token' => 'device-b'])->assertOk();

        $this->assertSame(2, VendorPushSubscription::where('seller_id', $seller->id)->count());
    }

    public function test_re_registering_the_same_token_updates_rather_than_duplicates(): void
    {
        $seller = Seller::create(['f_name' => 'C', 'l_name' => 'V', 'phone' => '3', 'email' => 'psc@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);
        $this->actingAs($seller, 'seller');

        $this->postJson(route('vendor.push-subscriptions.store'), ['token' => 'same-token'])->assertOk();
        $this->postJson(route('vendor.push-subscriptions.store'), ['token' => 'same-token'])->assertOk();

        $this->assertSame(1, VendorPushSubscription::where('seller_id', $seller->id)->count());
    }

    public function test_a_seller_cannot_delete_another_sellers_subscription(): void
    {
        $sellerA = Seller::create(['f_name' => 'D', 'l_name' => 'V', 'phone' => '4', 'email' => 'psd@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);
        $sellerB = Seller::create(['f_name' => 'E', 'l_name' => 'V', 'phone' => '5', 'email' => 'pse@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);

        $subscription = VendorPushSubscription::create([
            'seller_id' => $sellerB->id,
            'fcm_token' => 'seller-b-token',
            'token_hash' => VendorPushSubscription::hashToken('seller-b-token'),
        ]);

        $this->actingAs($sellerA, 'seller');
        $this->deleteJson(route('vendor.push-subscriptions.destroy'), ['token' => 'seller-b-token'])->assertOk();

        // The destroy endpoint is scoped by seller_id server-side, so
        // seller A's request — even with seller B's exact token — deletes
        // nothing belonging to seller B.
        $this->assertDatabaseHas('vendor_push_subscriptions', ['id' => $subscription->id]);
    }
}
