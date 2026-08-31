<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('real_estate_inquiries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('listing_id');
            // Denormalized — same isolation convention as real_estate_listings.seller_id.
            $table->bigInteger('seller_id');
            $table->bigInteger('customer_id')->nullable();

            $table->string('guest_name')->nullable();
            $table->string('guest_phone')->nullable();
            $table->string('guest_email')->nullable();
            $table->text('message');

            $table->string('status')->default('new'); // new | contacted | closed

            $table->timestamps();

            $table->index(['seller_id', 'listing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('real_estate_inquiries');
    }
};
