<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pricing/capabilities live here, never in application code — see
        // architecture doc Part II §7. Admin edits a row when a provider
        // changes its price list; no deploy required.
        Schema::create('ai_provider_models', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('ai_provider_id');
            $table->string('model_name');
            $table->json('capabilities')->nullable();
            $table->decimal('input_price', 21, 12)->default(0);
            $table->decimal('output_price', 21, 12)->default(0);
            $table->decimal('cached_input_price', 21, 12)->nullable();
            $table->string('currency', 8)->default('USD');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_provider_models');
    }
};
