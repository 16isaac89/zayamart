<?php

namespace Modules\AIAssistant\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Structured settings a vendor edits. PromptBuilder compiles these into the
 * system prompt — vendors never see or write raw prompt text (architecture
 * doc Part II §6).
 */
class VendorAISetting extends Model
{
    protected $table = 'vendor_ai_settings';

    protected $fillable = [
        'ai_agent_id',
        'personality',
        'tone',
        'languages',
        'business_description',
        'opening_hours',
        'delivery_policy',
        'payment_methods',
        'return_policy',
        'custom_instructions',
        'faqs',
        'monthly_token_limit',
        'monthly_conversation_limit',
        'usage_warning_threshold_percent',
        'handoff_phrases',
    ];

    protected $casts = [
        'ai_agent_id' => 'integer',
        'languages' => 'array',
        'opening_hours' => 'array',
        'payment_methods' => 'array',
        'faqs' => 'array',
        'handoff_phrases' => 'array',
        'monthly_token_limit' => 'integer',
        'monthly_conversation_limit' => 'integer',
        'usage_warning_threshold_percent' => 'integer',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AIAgent::class, 'ai_agent_id');
    }
}
