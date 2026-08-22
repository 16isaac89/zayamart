<?php

namespace Modules\AIAssistant\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit trail of every tool invocation (table ai_tool_calls). Named
 * "AiToolCallLog" rather than "AIToolCall" to avoid colliding with the
 * provider-neutral AIToolCall DTO in DataTransfer\AIToolCall — same
 * concept, different layer (persisted row vs. in-flight value object).
 */
class AiToolCallLog extends Model
{
    protected $table = 'ai_tool_calls';

    public const STATUS_OK = 'ok';
    public const STATUS_ERROR = 'error';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'ai_message_id',
        'tool_name',
        'arguments',
        'result',
        'status',
    ];

    protected $casts = [
        'ai_message_id' => 'integer',
        'arguments' => 'array',
        'result' => 'array',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(AIMessage::class, 'ai_message_id');
    }
}
