<?php

namespace Modules\RealEstate\app\Models;

use App\Models\Seller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RealEstateBroker extends Model
{
    protected $table = 'real_estate_brokers';

    protected $fillable = [
        'seller_id',
        'agency_name',
        'license_number',
        'bio',
        'status',
    ];

    protected $casts = [
        'seller_id' => 'integer',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    public function listings(): HasMany
    {
        return $this->hasMany(RealEstateListing::class, 'broker_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
