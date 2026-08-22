<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The project's existing 'notifications' table is an audience
        // broadcast system (sent_by/sent_to are audience TYPES like
        // 'customer'/'seller', not specific recipients — confirmed by
        // inspecting its schema alongside notification_seens, which
        // records per-user read receipts against a shared broadcast row).
        // That is a different concept from a targeted, per-vendor,
        // per-order actionable notification, so this is a new table rather
        // than a forced reuse — see the notification architecture report.
        Schema::create('vendor_notifications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('seller_id');
            $table->string('type'); // new_order | payment_received | order_status_changed | customer_needs_help | low_stock | system_alert
            $table->string('title');
            $table->text('message');
            $table->string('related_type')->nullable(); // e.g. 'order', 'ai_conversation'
            $table->bigInteger('related_id')->nullable();
            $table->string('action_url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['seller_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_notifications');
    }
};
