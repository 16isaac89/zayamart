<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_notification_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('seller_id')->unique();
            // {"new_order": {"in_app": true, "pwa": true, "whatsapp": false}, ...}
            // — per-event, per-channel. Superadmin platform defaults (brief
            // §23) are applied when a key is absent, not hard-coded twice.
            $table->json('preferences')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_notification_settings');
    }
};
