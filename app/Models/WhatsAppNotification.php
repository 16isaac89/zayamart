<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Outbound WhatsApp order-notification log. The unique (order_id, seller_id)
 * pair (enforced at the DB level) is the idempotency guard that stops a
 * redelivered queue job from sending the same order twice — see the AI
 * Order Assistant architecture doc, Part II §12.
 */
class WhatsAppNotification extends Model
{
    protected $table = 'whatsapp_notifications';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'order_id',
        'seller_id',
        'whatsapp_provider',
        'status',
        'provider_message_id',
        'attempts',
        'last_error',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'seller_id' => 'integer',
        'attempts' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }
}
