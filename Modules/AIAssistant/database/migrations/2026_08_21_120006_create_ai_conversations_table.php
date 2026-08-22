<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('seller_id');
            $table->bigInteger('ai_agent_id');
            $table->bigInteger('customer_id')->nullable();
            $table->bigInteger('guest_id')->nullable();
            $table->string('channel')->default('web'); // web | api
            $table->string('mode')->default('shopping'); // shopping | checkout
            $table->string('status')->default('active'); // active | awaiting_confirmation | confirmed | ended
            $table->timestamp('checkout_confirmed_at')->nullable();
            // Idempotency guard for order creation — see architecture doc
            // Part II §12. Once set, CreateOrder returns the existing order
            // instead of calling OrderManager::generateOrder() again.
            $table->string('confirmed_order_group_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['seller_id', 'customer_id']);
            $table->index(['seller_id', 'guest_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_conversations');
    }
};
