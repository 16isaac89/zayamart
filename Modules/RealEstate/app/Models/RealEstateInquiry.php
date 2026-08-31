<?php

namespace Modules\RealEstate\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * seller_id is denormalized — same isolation convention as RealEstateListing.
 */
class RealEstateInquiry extends Model
{
    protected $table = 'real_estate_inquiries';

    public const STATUS_NEW = 'new';
    public const STATUS_CONTACTED = 'contacted';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'listing_id',
        'seller_id',
        'customer_id',
        'guest_name',
        'guest_phone',
        'guest_email',
        'message',
        'status',
    ];

    protected $casts = [
        'listing_id' => 'integer',
        'seller_id' => 'integer',
        'customer_id' => 'integer',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(RealEstateListing::class, 'listing_id');
    }
}
