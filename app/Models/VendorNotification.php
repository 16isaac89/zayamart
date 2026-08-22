<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Targeted, per-vendor, per-order actionable notification. Distinct from
 * the existing Notification model (an audience broadcast system — see
 * migration 130001's docblock).
 */
class VendorNotification extends Model
{
    protected $table = 'vendor_notifications';

    public const TYPE_NEW_ORDER = 'new_order';
    public const TYPE_PAYMENT_RECEIVED = 'payment_received';
    public const TYPE_ORDER_STATUS_CHANGED = 'order_status_changed';
    public const TYPE_CUSTOMER_NEEDS_HELP = 'customer_needs_help';
    public const TYPE_LOW_STOCK = 'low_stock';
    public const TYPE_SYSTEM_ALERT = 'system_alert';

    protected $fillable = [
        'seller_id',
        'type',
        'title',
        'message',
        'related_type',
        'related_id',
        'action_url',
        'metadata',
        'read_at',
    ];

    protected $casts = [
        'seller_id' => 'integer',
        'related_id' => 'integer',
        'metadata' => 'array',
        'read_at' => 'datetime',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    public function isRead(): bool
    {
        return !is_null($this->read_at);
    }
}
