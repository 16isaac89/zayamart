<?php

namespace App\Services;

use App\Models\VendorNotification;
use App\Models\VendorNotificationSetting;
use App\Models\VendorPushSubscription;
use App\Traits\PushNotificationTrait;
use Illuminate\Support\Facades\Log;

/**
 * BUSINESS EVENT -> Notification Orchestrator -> { in-app, PWA push,
 * (optional) WhatsApp } — see the notification architecture report.
 *
 * Deliberately reuses PushNotificationTrait::sendPushNotificationToDevice(),
 * the project's already-working FCM v1 send path (already used for the
 * existing native-app seller "new order" push via OrderPlacedListener), for
 * multiple devices per vendor instead of building a parallel Web Push
 * implementation.
 *
 * WhatsApp is NOT sent from here — it stays on its own existing queued
 * listener (WhatsAppOrderNotificationListener), which now checks
 * VendorNotificationSetting before sending. This class only decides
 * whether in-app/PWA fire; it never touches the order, and nothing here
 * can fail the order transaction — see NotifyVendorOfOrderListener, which
 * is where this is actually invoked from a queue.
 */
class VendorNotificationOrchestrator
{
    use PushNotificationTrait;

    public function notify(
        int $sellerId,
        string $type,
        string $title,
        string $message,
        ?string $relatedType = null,
        ?int $relatedId = null,
        ?string $actionUrl = null,
        array $metadata = [],
    ): VendorNotification {
        // Idempotency (brief §14): an exact-duplicate notification for this
        // seller/type/related entity/title/message created in the last few
        // minutes is treated as the same event re-delivered (a queue
        // retry, or the same underlying Laravel event firing twice — both
        // observed as real behavior in this codebase, e.g.
        // OrderPlacedEvent firing once per recipient) and returned as-is
        // rather than re-created and re-pushed. Distinct status changes
        // for the same order still get their own row/push, since their
        // title/message differ.
        $existing = VendorNotification::where('seller_id', $sellerId)
            ->where('type', $type)
            ->where('related_type', $relatedType)
            ->where('related_id', $relatedId)
            ->where('title', $title)
            ->where('message', $message)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->first();

        if ($existing) {
            return $existing;
        }

        $settings = VendorNotificationSetting::where('seller_id', $sellerId)->first();
        $isEnabled = fn (string $channel) => $settings
            ? $settings->isEnabled($type, $channel)
            : (bool)data_get(config('notifications.default_preferences', []), "{$type}.{$channel}", true);

        // In-app is always recorded — it's the notification CENTER's data
        // source and costs nothing to store; "in_app" preference only
        // controls whether it's surfaced as unread/prominent, not whether
        // it exists at all, matching how every other notification list in
        // this app behaves.
        $notification = VendorNotification::create([
            'seller_id' => $sellerId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'action_url' => $actionUrl,
            'metadata' => $metadata,
        ]);

        if ($isEnabled('pwa')) {
            $this->sendPushToAllDevices($sellerId, $title, $message, $actionUrl, $metadata);
        }

        return $notification;
    }

    /**
     * A push failure for one device must never stop delivery to the
     * vendor's other devices, and must never propagate to the caller — see
     * class docblock and the architecture report's failure-mode table.
     */
    private function sendPushToAllDevices(int $sellerId, string $title, string $message, ?string $actionUrl, array $metadata): void
    {
        $subscriptions = VendorPushSubscription::where('seller_id', $sellerId)->get();

        foreach ($subscriptions as $subscription) {
            try {
                $this->sendPushNotificationToDevice($subscription->fcm_token, [
                    'title' => $title,
                    'description' => $message,
                    'order_id' => (string)($metadata['order_id'] ?? ''),
                    'image' => '',
                    'type' => 'vendor_notification',
                    'message_key' => 'vendor_notification',
                ]);
            } catch (\Throwable $exception) {
                Log::warning('Vendor push notification failed', [
                    'seller_id' => $sellerId,
                    'subscription_id' => $subscription->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }
}
