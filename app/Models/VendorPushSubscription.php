<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorPushSubscription extends Model
{
    protected $table = 'vendor_push_subscriptions';

    protected $fillable = [
        'seller_id',
        'fcm_token',
        'token_hash',
        'device_type',
        'user_agent',
        'last_active_at',
    ];

    protected $casts = [
        'seller_id' => 'integer',
        'last_active_at' => 'datetime',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
