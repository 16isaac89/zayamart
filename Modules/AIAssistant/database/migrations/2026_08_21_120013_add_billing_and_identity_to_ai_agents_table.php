<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_agents', function (Blueprint $table) {
            // Billing mode: platform_default | platform_managed | vendor_owned
            // (architecture doc Part III §1). platform_default keeps using
            // ai_provider_config_id == null -> AIProviderConfig::platformDefault(),
            // exactly as before. platform_managed reuses the existing
            // ai_provider_config_id column to point at a specific
            // vendor_managed_available platform config. vendor_owned uses
            // the two new columns below instead.
            $table->string('billing_mode')->default('platform_default')->after('ai_provider_config_id');
            $table->bigInteger('vendor_ai_provider_id')->nullable()->after('billing_mode');
            $table->string('vendor_model_name')->nullable()->after('vendor_ai_provider_id');

            // Bot identity (brief §7) — reuses the existing FileManagerTrait
            // upload convention (paired *_storage_type column, matching
            // Shop.image / Shop.image_storage_type).
            $table->string('bot_name')->nullable()->after('vendor_model_name');
            $table->string('bot_avatar')->nullable()->after('bot_name');
            $table->string('bot_avatar_storage_type')->nullable()->after('bot_avatar');
            $table->string('short_description')->nullable()->after('bot_avatar_storage_type');

            // AI / human / hybrid — governs whether a *new* conversation
            // starts out AI-handled at all (brief §8). Per-conversation live
            // state lives on ai_conversations.support_status instead.
            $table->string('handling_mode')->default('ai')->after('short_description');
        });
    }

    public function down(): void
    {
        Schema::table('ai_agents', function (Blueprint $table) {
            $table->dropColumn([
                'billing_mode', 'vendor_ai_provider_id', 'vendor_model_name',
                'bot_name', 'bot_avatar', 'bot_avatar_storage_type', 'short_description',
                'handling_mode',
            ]);
        });
    }
};
