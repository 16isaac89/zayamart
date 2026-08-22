<?php

namespace Modules\AIAssistant\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A usable (provider, model, params) combination. ai_agents.ai_provider_config_id
 * points at one of these, or is null to mean "the row with
 * is_platform_default = true" — see architecture doc Part II §5.
 */
class AIProviderConfig extends Model
{
    protected $table = 'ai_provider_configs';

    protected $fillable = [
        'ai_provider_id',
        'ai_provider_model_id',
        'temperature',
        'max_tokens',
        'is_platform_default',
    ];

    protected $casts = [
        'ai_provider_id' => 'integer',
        'ai_provider_model_id' => 'integer',
        'temperature' => 'float',
        'max_tokens' => 'integer',
        'is_platform_default' => 'boolean',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AIProvider::class, 'ai_provider_id');
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(AIProviderModel::class, 'ai_provider_model_id');
    }

    public static function platformDefault(): ?self
    {
        return static::where('is_platform_default', true)->first();
    }
}
