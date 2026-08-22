<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Multi-device push (brief §6) — the existing sellers.cm_firebase_token
        // column is a single value (one device only, used by the native
        // mobile app's existing push flow, which this table does not
        // touch). This is a one-to-many extension for the PWA/web channel,
        // reusing the project's already-working FCM v1 send infrastructure
        // (PushNotificationTrait::sendPushNotificationToDevice()) rather
        // than a parallel VAPID Web Push implementation — see the
        // notification architecture report for why.
        Schema::create('vendor_push_subscriptions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('seller_id');
            $table->text('fcm_token');
            // FCM tokens are long and variable-length — too long for a
            // standard MySQL index on the raw column. A sha256 hash gives
            // an efficient, reliable dedup/lookup key without truncation.
            $table->char('token_hash', 64);
            $table->string('device_type')->default('web'); // web | android | ios
            $table->string('user_agent')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();

            $table->index('seller_id');
            $table->unique('token_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_push_subscriptions');
    }
};
