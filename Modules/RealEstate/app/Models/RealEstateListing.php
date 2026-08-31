<?php

namespace Modules\RealEstate\app\Models;

use App\Traits\StorageTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * seller_id is denormalized on purpose — every query filters on this
 * column directly, never by joining through real_estate_brokers, so a
 * manipulated broker_id/listing_id can never widen a request past the
 * authenticated seller. Same convention as AIKnowledgeChunk.seller_id.
 */
class RealEstateListing extends Model
{
    use StorageTrait;

    protected $table = 'real_estate_listings';

    public const TYPE_HOUSE = 'house';
    public const TYPE_LAND = 'land';

    public const PURPOSE_SALE = 'sale';
    public const PURPOSE_RENT = 'rent';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_DENIED = 'denied';
    public const STATUS_SOLD = 'sold';
    public const STATUS_RENTED = 'rented';

    protected $fillable = [
        'broker_id',
        'seller_id',
        'listing_type',
        'purpose',
        'title',
        'slug',
        'description',
        'price',
        'price_period',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'latitude',
        'longitude',
        'area_size',
        'area_unit',
        'bedrooms',
        'bathrooms',
        'floors',
        'year_built',
        'parking_spaces',
        'furnished',
        'amenities',
        'images',
        'status',
        'denied_note',
        'published',
        'views_count',
    ];

    protected $casts = [
        'broker_id' => 'integer',
        'seller_id' => 'integer',
        'price' => 'float',
        'latitude' => 'float',
        'longitude' => 'float',
        'area_size' => 'float',
        'bedrooms' => 'integer',
        'bathrooms' => 'integer',
        'floors' => 'integer',
        'year_built' => 'integer',
        'parking_spaces' => 'integer',
        'furnished' => 'boolean',
        'amenities' => 'array',
        'images' => 'array',
        'published' => 'boolean',
        'views_count' => 'integer',
    ];

    protected $appends = ['images_full_url'];

    public function broker(): BelongsTo
    {
        return $this->belongsTo(RealEstateBroker::class, 'broker_id');
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(RealEstateInquiry::class, 'listing_id');
    }

    public function isHouse(): bool
    {
        return $this->listing_type === self::TYPE_HOUSE;
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED)->where('published', true);
    }

    /**
     * @return array<int, array{key: string, path: string|null, status: int}>
     */
    public function getImagesFullUrlAttribute(): array
    {
        return collect($this->images ?? [])
            ->map(fn (array $image) => $this->storageLink('real-estate', $image['path'] ?? '', $image['storage_type'] ?? 'public'))
            ->all();
    }

    public function thumbnailUrl(): ?string
    {
        return $this->images_full_url[0]['path'] ?? null;
    }
}
