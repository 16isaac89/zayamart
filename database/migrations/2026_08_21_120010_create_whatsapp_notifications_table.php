<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Outbound WhatsApp order-notification log. One row is attempted per
        // (order_id, seller_id) — the unique index is the idempotency guard
        // that stops a redelivered queue job from sending twice (architecture
        // doc Part II §12).
        Schema::create('whatsapp_notifications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('order_id');
            $table->bigInteger('seller_id');
            $table->string('whatsapp_provider')->default('meta_cloud');
            $table->string('status')->default('pending'); // pending | sent | failed
            $table->string('provider_message_id')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'seller_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_notifications');
    }
};
