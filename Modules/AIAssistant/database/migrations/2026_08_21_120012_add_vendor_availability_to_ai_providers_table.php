<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_providers', function (Blueprint $table) {
            // Superadmin control surface (brief §29/§30): can vendors bring
            // their own key for this provider, and/or use the platform's
            // own credentials for it?
            $table->boolean('vendor_owned_allowed')->default(true)->after('status');
            $table->boolean('vendor_managed_available')->default(false)->after('vendor_owned_allowed');
        });
    }

    public function down(): void
    {
        Schema::table('ai_providers', function (Blueprint $table) {
            $table->dropColumn(['vendor_owned_allowed', 'vendor_managed_available']);
        });
    }
};
