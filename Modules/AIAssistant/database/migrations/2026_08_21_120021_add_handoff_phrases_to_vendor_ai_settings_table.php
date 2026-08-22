<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_ai_settings', function (Blueprint $table) {
            // Vendor-configurable escalation phrases (brief §37), on top of
            // the fixed default list in config('aiassistant.default_handoff_phrases').
            // Detection is server-side keyword matching, not an LLM
            // "confidence score" — see HandoffService.
            $table->json('handoff_phrases')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('vendor_ai_settings', function (Blueprint $table) {
            $table->dropColumn('handoff_phrases');
        });
    }
};
