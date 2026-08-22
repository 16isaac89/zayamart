<?php

namespace Modules\AIAssistant\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AIMessage extends Model
{
    protected $table = 'ai_messages';

    // Provider-wire-format role (needed to replay history to an LLM).
    public const ROLE_USER = 'user';
    public const ROLE_ASSISTANT = 'assistant';
    public const ROLE_TOOL = 'tool';

    // Display participant — independent of role. See migration 120015's
    // docblock: both AI and human replies are role=assistant, but must be
    // labeled differently for the vendor and customer (brief §8/§10).
    public const SENDER_CUSTOMER = 'customer';
    public const SENDER_AI = 'ai';
    public const SENDER_HUMAN = 'human';
    public const SENDER_SYSTEM = 'system';

    protected $fillable = [
        'ai_conversation_id',
        'role',
        'content',
        'sender_type',
        'sender_id',
        'sender_name',
    ];

    protected $casts = [
        'ai_conversation_id' => 'integer',
        'sender_id' => 'integer',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AIConversation::class, 'ai_conversation_id');
    }

    public function toolCalls(): HasMany
    {
        return $this->hasMany(AiToolCallLog::class, 'ai_message_id');
    }
}
