<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_tool_calls', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('ai_message_id');
            $table->string('tool_name');
            $table->json('arguments')->nullable();
            $table->json('result')->nullable();
            $table->string('status')->default('ok'); // ok | error | rejected
            $table->timestamps();

            $table->index('ai_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_tool_calls');
    }
};
