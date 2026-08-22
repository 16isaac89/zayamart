<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('ai_conversation_id');
            $table->string('role'); // user | assistant | tool
            $table->longText('content')->nullable();
            $table->timestamps();

            $table->index('ai_conversation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
    }
};
