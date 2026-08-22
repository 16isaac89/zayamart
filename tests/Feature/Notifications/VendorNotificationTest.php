<?php

namespace Tests\Feature\Notifications;

use App\Models\Seller;
use App\Models\VendorNotification;
use App\Services\VendorNotificationOrchestrator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Covers brief §2/§14/§19/§32: notification creation, read/unread,
 * idempotency, and vendor isolation.
 */
class VendorNotificationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_notify_creates_an_in_app_notification(): void
    {
        $seller = Seller::create(['f_name' => 'A', 'l_name' => 'V', 'phone' => '1', 'email' => 'vna@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);

        $notification = app(VendorNotificationOrchestrator::class)->notify(
            sellerId: $seller->id,
            type: VendorNotification::TYPE_NEW_ORDER,
            title: 'New Order #123',
            message: 'Test message',
        );

        $this->assertDatabaseHas('vendor_notifications', [
            'id' => $notification->id,
            'seller_id' => $seller->id,
            'type' => VendorNotification::TYPE_NEW_ORDER,
        ]);
        $this->assertFalse($notification->isRead());
    }

    public function test_a_repeated_identical_notification_within_the_dedup_window_is_not_duplicated(): void
    {
        $seller = Seller::create(['f_name' => 'B', 'l_name' => 'V', 'phone' => '2', 'email' => 'vnb@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);
        $orchestrator = app(VendorNotificationOrchestrator::class);

        // Simulates a queue retry / the same underlying Laravel event
        // firing twice — brief §14.
        $first = $orchestrator->notify($seller->id, VendorNotification::TYPE_NEW_ORDER, 'New Order #99', 'Same message');
        $second = $orchestrator->notify($seller->id, VendorNotification::TYPE_NEW_ORDER, 'New Order #99', 'Same message');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, VendorNotification::where('seller_id', $seller->id)->count());
    }

    public function test_a_distinct_status_change_for_the_same_order_is_not_deduplicated(): void
    {
        $seller = Seller::create(['f_name' => 'C', 'l_name' => 'V', 'phone' => '3', 'email' => 'vnc@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);
        $orchestrator = app(VendorNotificationOrchestrator::class);

        $orchestrator->notify($seller->id, VendorNotification::TYPE_ORDER_STATUS_CHANGED, 'Order #5 — Confirmed', 'Order #5 is now confirmed.', 'order', 5);
        $orchestrator->notify($seller->id, VendorNotification::TYPE_ORDER_STATUS_CHANGED, 'Order #5 — Delivered', 'Order #5 is now delivered.', 'order', 5);

        $this->assertSame(2, VendorNotification::where('seller_id', $seller->id)->where('related_id', 5)->count());
    }

    public function test_mark_read_and_mark_all_read(): void
    {
        $seller = Seller::create(['f_name' => 'D', 'l_name' => 'V', 'phone' => '4', 'email' => 'vnd@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);
        $n1 = VendorNotification::create(['seller_id' => $seller->id, 'type' => 'new_order', 'title' => 'A', 'message' => 'a']);
        $n2 = VendorNotification::create(['seller_id' => $seller->id, 'type' => 'new_order', 'title' => 'B', 'message' => 'b']);

        $this->actingAs($seller, 'seller');
        $this->post(route('vendor.notifications.read', $n1->id));
        $this->assertNotNull($n1->fresh()->read_at);
        $this->assertNull($n2->fresh()->read_at);

        $this->post(route('vendor.notifications.mark-all-read'));
        $this->assertNotNull($n2->fresh()->read_at);
    }

    public function test_a_seller_cannot_mark_another_sellers_notification_as_read(): void
    {
        $sellerA = Seller::create(['f_name' => 'E', 'l_name' => 'V', 'phone' => '5', 'email' => 'vne@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);
        $sellerB = Seller::create(['f_name' => 'F', 'l_name' => 'V', 'phone' => '6', 'email' => 'vnf@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);
        $notificationB = VendorNotification::create(['seller_id' => $sellerB->id, 'type' => 'new_order', 'title' => 'B', 'message' => 'b']);

        $this->actingAs($sellerA, 'seller');
        $this->withoutExceptionHandling();

        $threw = false;
        try {
            $this->post(route('vendor.notifications.read', $notificationB->id));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            $threw = true;
        }

        $this->assertTrue($threw);
        $this->assertNull($notificationB->fresh()->read_at, "Seller A must not be able to mark seller B's notification as read.");
    }

    public function test_the_recent_endpoint_only_returns_the_authenticated_sellers_notifications(): void
    {
        $sellerA = Seller::create(['f_name' => 'G', 'l_name' => 'V', 'phone' => '7', 'email' => 'vng@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);
        $sellerB = Seller::create(['f_name' => 'H', 'l_name' => 'V', 'phone' => '8', 'email' => 'vnh@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);
        VendorNotification::create(['seller_id' => $sellerA->id, 'type' => 'new_order', 'title' => 'Mine', 'message' => 'a']);
        VendorNotification::create(['seller_id' => $sellerB->id, 'type' => 'new_order', 'title' => 'Not mine', 'message' => 'b']);

        $this->actingAs($sellerA, 'seller');
        $response = $this->getJson(route('vendor.notifications.recent'));

        $response->assertOk();
        $titles = collect($response->json('notifications'))->pluck('title');
        $this->assertTrue($titles->contains('Mine'));
        $this->assertFalse($titles->contains('Not mine'));
    }
}
