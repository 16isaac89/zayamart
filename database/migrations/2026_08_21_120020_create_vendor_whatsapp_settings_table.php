<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Vendor-owned WhatsApp Cloud API credentials (brief §21) —
        // separate from config/services.php's platform-level credentials.
        // WhatsAppService checks for a row here first, falling back to the
        // platform config — see app/Services/WhatsAppService.php.
        Schema::create('vendor_whatsapp_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('seller_id')->unique();
            $table->string('whatsapp_provider')->default('meta_cloud');
            $table->text('access_token')->nullable();
            $table->string('phone_number_id')->nullable();
            $table->string('status')->default('disabled'); // connected | error | disabled
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_whatsapp_settings');
    }
};
