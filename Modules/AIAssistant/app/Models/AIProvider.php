<?php

namespace Modules\AIAssistant\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A platform-level AI provider account (brief item 3). One row per
 * provider key ('deepseek', 'openai', 'anthropic', 'gemini', ...);
 * credentials live here, never in application code.
 */
class AIProvider extends Model
{
    protected $table = 'ai_providers';

    protected $fillable = [
        'key',
        'display_name',
        'base_url',
        'api_key',
        'status',
        'vendor_owned_allowed',
        'vendor_managed_available',
    ];

    protected $casts = [
        'api_key' => 'encrypted',
        'vendor_owned_allowed' => 'boolean',
        'vendor_managed_available' => 'boolean',
    ];

    protected $hidden = [
        'api_key',
    ];

    public function models(): HasMany
    {
        return $this->hasMany(AIProviderModel::class, 'ai_provider_id');
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }
}
