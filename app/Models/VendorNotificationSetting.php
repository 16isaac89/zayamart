<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorNotificationSetting extends Model
{
    protected $table = 'vendor_notification_settings';

    protected $fillable = [
        'seller_id',
        'preferences',
    ];

    protected $casts = [
        'seller_id' => 'integer',
        'preferences' => 'array',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    /**
     * Whether $channel ('in_app'|'pwa'|'whatsapp') is enabled for $event
     * (VendorNotification::TYPE_*) — falls back to the platform default
     * (brief §23) when the vendor hasn't set a preference, never to a
     * hard-coded value.
     */
    public function isEnabled(string $event, string $channel): bool
    {
        $vendorValue = data_get($this->preferences, "{$event}.{$channel}");
        if (!is_null($vendorValue)) {
            return (bool)$vendorValue;
        }

        return (bool)data_get(config('notifications.default_preferences', []), "{$event}.{$channel}", true);
    }
}
