<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Administrative event trail (brief §38). 'metadata' must never
        // contain secrets — enforced by AuditLogger, not by this schema.
        Schema::create('ai_audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('actor_type'); // admin | seller | system
            $table->bigInteger('actor_id')->nullable();
            $table->bigInteger('seller_id')->nullable(); // scope, when applicable
            $table->string('event_type');
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['seller_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_audit_logs');
    }
};
