<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_knowledge_documents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('seller_id');
            $table->string('original_filename');
            $table->string('disk_path');
            // Matches the project's existing paired-column upload
            // convention (e.g. Shop.image / Shop.image_storage_type) via
            // App\Traits\FileManagerTrait — see architecture doc Part III.
            $table->string('storage_type')->default('public');
            $table->string('mime_type')->nullable();
            $table->string('extension', 10)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('status')->default('processing'); // processing | indexed | failed
            $table->text('failure_reason')->nullable();
            $table->unsignedInteger('chunk_count')->default(0);
            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();

            $table->index('seller_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_knowledge_documents');
    }
};
