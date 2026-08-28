<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Policy change: vendors are now expected to bring their own AI provider
 * key by default, not ride on the platform's key. New AIAgent rows default
 * to billing_mode = 'vendor_owned' rather than 'platform_default'.
 *
 * This does NOT touch existing rows — AIProviderManager::resolveVendorOwned()
 * throws a clear AIProviderException (no fallback) when vendor_owned is
 * selected but no vendor-owned provider is connected yet, so an existing
 * vendor's already-working platform_default agent must stay untouched or
 * its chat would break the moment this migration runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_agents', function (Blueprint $table) {
            $table->string('billing_mode')->default('vendor_owned')->change();
        });
    }

    public function down(): void
    {
        Schema::table('ai_agents', function (Blueprint $table) {
            $table->string('billing_mode')->default('platform_default')->change();
        });
    }
};
