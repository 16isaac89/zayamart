<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_usage', function (Blueprint $table) {
            // Which of the three billing modes produced this row — needed
            // to separate "platform cost" from "vendor-owned usage" on the
            // dashboards (brief §28/§31: never expose vendor-owned API
            // usage as platform cost).
            $table->string('billing_mode')->default('platform_default')->after('seller_id');
            $table->bigInteger('vendor_ai_provider_id')->nullable()->after('ai_provider_model_id');
        });
    }

    public function down(): void
    {
        Schema::table('ai_usage', function (Blueprint $table) {
            $table->dropColumn(['billing_mode', 'vendor_ai_provider_id']);
        });
    }
};
