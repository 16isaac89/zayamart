<?php

namespace Modules\AIAssistant\app\Models;

use App\Models\Seller;
use App\Traits\StorageTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AIKnowledgeDocument extends Model
{
    use StorageTrait;

    public const STATUS_PROCESSING = 'processing';
    public const STATUS_INDEXED = 'indexed';
    public const STATUS_FAILED = 'failed';

    protected $table = 'ai_knowledge_documents';

    protected $fillable = [
        'seller_id',
        'original_filename',
        'disk_path',
        'storage_type',
        'mime_type',
        'extension',
        'size_bytes',
        'status',
        'failure_reason',
        'chunk_count',
        'indexed_at',
    ];

    protected $casts = [
        'seller_id' => 'integer',
        'size_bytes' => 'integer',
        'chunk_count' => 'integer',
        'indexed_at' => 'datetime',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(AIKnowledgeChunk::class, 'ai_knowledge_document_id');
    }

    public function getFileFullUrlAttribute(): string|null|array
    {
        return $this->storageLink('ai-knowledge', $this->disk_path, $this->storage_type ?? 'public');
    }
}
