<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_provider_configs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('ai_provider_id');
            $table->bigInteger('ai_provider_model_id');
            $table->decimal('temperature', 4, 2)->default(0.30);
            $table->integer('max_tokens')->nullable();
            $table->boolean('is_platform_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_provider_configs');
    }
};
