<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('real_estate_listings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('broker_id');
            // Denormalized on purpose, same convention as
            // ai_knowledge_chunks.seller_id: every query filters on this
            // column directly rather than joining through real_estate_brokers,
            // so a manipulated broker_id/listing_id can never widen a
            // request past the authenticated seller.
            $table->bigInteger('seller_id');

            $table->string('listing_type'); // house | land
            $table->string('purpose'); // sale | rent

            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            $table->decimal('price', 14, 2);
            $table->string('price_period')->nullable(); // one_time | monthly | yearly (rent only)

            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('postal_code')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->decimal('area_size', 12, 2)->nullable(); // land size or house floor area
            $table->string('area_unit')->nullable(); // sqft | sqm | acre | hectare

            // House-only — left null for land listings.
            $table->unsignedInteger('bedrooms')->nullable();
            $table->unsignedInteger('bathrooms')->nullable();
            $table->unsignedInteger('floors')->nullable();
            $table->unsignedInteger('year_built')->nullable();
            $table->unsignedInteger('parking_spaces')->nullable();
            $table->boolean('furnished')->nullable();

            // {amenity_key: true} pairs — see config('realestate.amenities').
            $table->text('amenities')->nullable();
            // [{path, storage_type}, ...] — first entry is the thumbnail,
            // same JSON-array-of-images convention as products.images.
            $table->text('images')->nullable();

            $table->string('status')->default('pending'); // pending | approved | denied | sold | rented
            $table->string('denied_note')->nullable();
            $table->boolean('published')->default(true);
            $table->unsignedInteger('views_count')->default(0);

            $table->timestamps();

            $table->index(['seller_id', 'broker_id']);
            $table->index(['status', 'published', 'listing_type', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('real_estate_listings');
    }
};
