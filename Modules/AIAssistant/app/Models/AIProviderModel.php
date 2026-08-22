<?php

namespace Modules\AIAssistant\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A selectable model + its pricing/capabilities for one provider
 * (architecture doc Part II §5/§7). Pricing changes are a row edit here,
 * never a code deploy.
 */
class AIProviderModel extends Model
{
    protected $table = 'ai_provider_models';

    protected $fillable = [
        'ai_provider_id',
        'model_name',
        'capabilities',
        'input_price',
        'output_price',
        'cached_input_price',
        'currency',
        'active',
    ];

    protected $casts = [
        'ai_provider_id' => 'integer',
        'capabilities' => 'array',
        'input_price' => 'float',
        'output_price' => 'float',
        'cached_input_price' => 'float',
        'active' => 'boolean',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AIProvider::class, 'ai_provider_id');
    }
}
