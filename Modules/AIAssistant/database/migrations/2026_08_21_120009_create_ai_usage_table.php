<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('seller_id');
            $table->bigInteger('ai_conversation_id')->nullable();
            $table->bigInteger('ai_provider_id')->nullable();
            $table->bigInteger('ai_provider_model_id')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cached_tokens')->default(0);
            // Computed from ai_provider_models pricing at write time —
            // never a hard-coded price in application code. See
            // architecture doc Part II §7.
            $table->decimal('estimated_cost', 21, 12)->default(0);
            $table->string('currency', 8)->default('USD');
            $table->boolean('usage_estimated')->default(false);
            $table->timestamps();

            $table->index('seller_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage');
    }
};
