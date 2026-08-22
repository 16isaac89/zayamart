<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A vendor's OWN provider credentials — separate from ai_providers
        // (the platform's own credentials for that same provider key).
        // "The platform should not be forced to pay for every vendor's AI
        // usage" — see architecture doc Part III §1.
        Schema::create('vendor_ai_providers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('seller_id');
            // Which provider catalog entry (deepseek/openai/anthropic/...) —
            // used only to look up the display name and adapter key; the
            // platform's own api_key on that row is never touched here.
            $table->bigInteger('ai_provider_id');
            $table->text('api_key');
            $table->string('base_url')->nullable();
            $table->string('status')->default('disabled'); // connected | error | disabled
            $table->timestamp('last_tested_at')->nullable();
            // Human-readable outcome only — never store a raw provider
            // response body here (may contain sensitive echoes).
            $table->string('last_test_message')->nullable();
            $table->timestamps();

            $table->unique(['seller_id', 'ai_provider_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_ai_providers');
    }
};
