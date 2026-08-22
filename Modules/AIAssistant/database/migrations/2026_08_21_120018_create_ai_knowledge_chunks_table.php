<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_knowledge_chunks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('ai_knowledge_document_id');
            // Denormalized on purpose: every knowledge query filters by
            // this column directly rather than joining through the parent
            // document, so a manipulated document_id/chunk_id can never
            // widen results past the authenticated seller — see
            // architecture doc Part III, knowledge security.
            $table->bigInteger('seller_id');
            $table->unsignedInteger('chunk_index');
            $table->longText('content');
            // JSON-encoded vector, unused by retrieval in this release
            // (keyword/FULLTEXT search is used instead — see
            // KnowledgeEmbeddingProviderInterface) — column exists so a
            // real embedding provider can be wired in later without a
            // schema change.
            $table->longText('embedding')->nullable();
            $table->timestamps();

            $table->index(['seller_id', 'ai_knowledge_document_id']);
            $table->fullText('content');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_knowledge_chunks');
    }
};
