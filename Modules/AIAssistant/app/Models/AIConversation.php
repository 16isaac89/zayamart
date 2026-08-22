<?php

namespace Modules\AIAssistant\app\Models;

use App\Models\Seller;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AIConversation extends Model
{
    protected $table = 'ai_conversations';

    // Human-handoff live state (support_status) — orthogonal to the
    // existing checkout-flow 'status' column and 'mode' column. See
    // architecture doc Part III / migration 120014's docblock.
    public const SUPPORT_ACTIVE = 'active';
    public const SUPPORT_HUMAN_REQUESTED = 'human_requested';
    public const SUPPORT_HUMAN_ACTIVE = 'human_active';
    public const SUPPORT_RESOLVED = 'resolved';
    public const SUPPORT_CLOSED = 'closed';

    protected $fillable = [
        'seller_id',
        'ai_agent_id',
        'customer_id',
        'guest_id',
        'channel',
        'mode',
        'status',
        'checkout_confirmed_at',
        'confirmed_order_group_id',
        'started_at',
        'ended_at',
        'support_status',
        'human_agent_seller_id',
        'human_requested_at',
        'human_taken_over_at',
        'human_returned_at',
    ];

    protected $casts = [
        'seller_id' => 'integer',
        'ai_agent_id' => 'integer',
        'customer_id' => 'integer',
        'guest_id' => 'integer',
        'human_agent_seller_id' => 'integer',
        'checkout_confirmed_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'human_requested_at' => 'datetime',
        'human_taken_over_at' => 'datetime',
        'human_returned_at' => 'datetime',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AIAgent::class, 'ai_agent_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function humanAgent(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'human_agent_seller_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AIMessage::class, 'ai_conversation_id')->orderBy('id');
    }

    public function isGuest(): bool
    {
        return is_null($this->customer_id) && !is_null($this->guest_id);
    }

    public function hasConfirmedOrder(): bool
    {
        return !is_null($this->confirmed_order_group_id);
    }

    public function isHumanActive(): bool
    {
        return $this->support_status === self::SUPPORT_HUMAN_ACTIVE;
    }

    public function needsAttention(): bool
    {
        return $this->support_status === self::SUPPORT_HUMAN_REQUESTED;
    }
}
