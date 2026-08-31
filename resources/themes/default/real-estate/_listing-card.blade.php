<a href="{{ route('real-estate.show', $listing->slug) }}" class="text-decoration-none text-dark">
    <div class="card h-100">
        @if($listing->thumbnailUrl())
            <img src="{{ $listing->thumbnailUrl() }}" class="card-img-top" style="height:200px;object-fit:cover;" alt="{{ $listing->title }}">
        @else
            <div class="bg-light d-flex align-items-center justify-content-center" style="height:200px;">
                <span class="text-muted">{{ translate('No_Image') }}</span>
            </div>
        @endif
        <div class="card-body">
            <h5 class="card-title">{{ $listing->title }}</h5>
            <p class="mb-1 text-capitalize small text-muted">
                {{ translate($listing->listing_type) }} · {{ translate($listing->purpose === 'rent' ? 'For_Rent' : 'For_Sale') }}
                @if($listing->city) · {{ $listing->city }} @endif
            </p>
            <p class="fw-bold mb-0">
                {{ number_format($listing->price, 2) }}
                @if($listing->purpose === 'rent' && $listing->price_period)
                    / {{ translate($listing->price_period) }}
                @endif
            </p>
        </div>
    </div>
</a>
