<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Structured settings only — PromptBuilder compiles these into the
        // system prompt server-side. Vendors never see or write raw prompt
        // text (architecture doc Part II §6).
        Schema::create('vendor_ai_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('ai_agent_id')->unique();
            $table->string('personality')->nullable();
            $table->string('tone')->nullable();
            $table->json('languages')->nullable();
            $table->text('business_description')->nullable();
            $table->json('opening_hours')->nullable();
            $table->text('delivery_policy')->nullable();
            $table->json('payment_methods')->nullable();
            $table->text('return_policy')->nullable();
            $table->text('custom_instructions')->nullable();
            $table->json('faqs')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_ai_settings');
    }
};
