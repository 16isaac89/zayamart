<?php

namespace Modules\AIAssistant\app\Models;

use App\Models\Seller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-turn cost accounting (table ai_usage). Named "AiUsageRecord" to avoid
 * colliding with the provider-neutral AIUsage DTO in DataTransfer\AIUsage.
 */
class AiUsageRecord extends Model
{
    protected $table = 'ai_usage';

    protected $fillable = [
        'seller_id',
        'billing_mode',
        'ai_conversation_id',
        'ai_provider_id',
        'ai_provider_model_id',
        'vendor_ai_provider_id',
        'input_tokens',
        'output_tokens',
        'cached_tokens',
        'estimated_cost',
        'currency',
        'usage_estimated',
    ];

    protected $casts = [
        'seller_id' => 'integer',
        'ai_conversation_id' => 'integer',
        'ai_provider_id' => 'integer',
        'ai_provider_model_id' => 'integer',
        'vendor_ai_provider_id' => 'integer',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'cached_tokens' => 'integer',
        'estimated_cost' => 'float',
        'usage_estimated' => 'boolean',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AIConversation::class, 'ai_conversation_id');
    }
}
