<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Schema only — no enforcement wired up yet (brief §32: "do not
        // implement billing enforcement if the existing billing system is
        // not ready, but design the schema so it can be added safely").
        Schema::table('vendor_ai_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('monthly_token_limit')->nullable();
            $table->unsignedInteger('monthly_conversation_limit')->nullable();
            $table->unsignedTinyInteger('usage_warning_threshold_percent')->default(80);
        });
    }

    public function down(): void
    {
        Schema::table('vendor_ai_settings', function (Blueprint $table) {
            $table->dropColumn(['monthly_token_limit', 'monthly_conversation_limit', 'usage_warning_threshold_percent']);
        });
    }
};
