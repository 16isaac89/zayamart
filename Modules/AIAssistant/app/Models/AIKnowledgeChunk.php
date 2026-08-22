<?php

namespace Modules\AIAssistant\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * seller_id is denormalized here deliberately — every retrieval query
 * filters on this column directly, never by joining through the parent
 * document, so a manipulated document_id can never widen results past the
 * authenticated seller. See architecture doc Part III, knowledge security.
 */
class AIKnowledgeChunk extends Model
{
    protected $table = 'ai_knowledge_chunks';

    protected $fillable = [
        'ai_knowledge_document_id',
        'seller_id',
        'chunk_index',
        'content',
        'embedding',
    ];

    protected $casts = [
        'ai_knowledge_document_id' => 'integer',
        'seller_id' => 'integer',
        'chunk_index' => 'integer',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(AIKnowledgeDocument::class, 'ai_knowledge_document_id');
    }
}
