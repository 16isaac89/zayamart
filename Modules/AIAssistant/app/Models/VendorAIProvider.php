<?php

namespace Modules\AIAssistant\app\Models;

use App\Models\Seller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A vendor's OWN provider credentials — "the platform should not be forced
 * to pay for every vendor's AI usage." Distinct from AIProvider (the
 * platform's own credentials for the same provider key). See architecture
 * doc Part III §1.
 */
class VendorAIProvider extends Model
{
    protected $table = 'vendor_ai_providers';

    protected $fillable = [
        'seller_id',
        'ai_provider_id',
        'api_key',
        'base_url',
        'status',
        'last_tested_at',
        'last_test_message',
    ];

    protected $casts = [
        'seller_id' => 'integer',
        'ai_provider_id' => 'integer',
        'api_key' => 'encrypted',
        'last_tested_at' => 'datetime',
    ];

    protected $hidden = [
        'api_key',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AIProvider::class, 'ai_provider_id');
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }
}
