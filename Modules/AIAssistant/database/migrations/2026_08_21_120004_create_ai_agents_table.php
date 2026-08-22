<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per vendor's assistant instance. Keyed by seller_id — the
        // same tenant discriminator Cart/Order/Shop already use (see
        // architecture doc Part I §4) — with shop_id carried alongside for
        // display, mirroring Product's own seller_id + shop_id pair.
        Schema::create('ai_agents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('seller_id');
            $table->bigInteger('shop_id')->nullable();
            $table->boolean('status')->default(false);
            $table->text('greeting')->nullable();
            // Nullable = "use whichever ai_provider_configs row has
            // is_platform_default = true". Populated later, per-vendor,
            // without a migration — see architecture doc Part II §5.
            $table->bigInteger('ai_provider_config_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agents');
    }
};
