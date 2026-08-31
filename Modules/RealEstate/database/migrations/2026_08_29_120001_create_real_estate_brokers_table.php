<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('real_estate_brokers', function (Blueprint $table) {
            $table->bigIncrements('id');
            // A vendor "becomes a broker" by having a row here at all — no
            // separate enable/disable flag. Unique: one broker profile per
            // seller.
            $table->bigInteger('seller_id')->unique();
            $table->string('agency_name')->nullable();
            $table->string('license_number')->nullable();
            $table->text('bio')->nullable();
            $table->string('status')->default('active'); // active | suspended
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('real_estate_brokers');
    }
};
