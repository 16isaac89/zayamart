@extends('layouts.front-end.app')

@section('title', translate('Real_Estate'))

@section('content')
    <div class="container py-4">
        <h1 class="h3 mb-4">{{ translate('Real_Estate_Listings') }}</h1>

        <form method="get" class="row g-2 mb-4">
            <div class="col-md-2">
                <select name="listing_type" class="form-control">
                    <option value="">{{ translate('Any_Type') }}</option>
                    <option value="house" {{ request('listing_type') === 'house' ? 'selected' : '' }}>{{ translate('House') }}</option>
                    <option value="land" {{ request('listing_type') === 'land' ? 'selected' : '' }}>{{ translate('Land') }}</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="purpose" class="form-control">
                    <option value="">{{ translate('Sale_or_Rent') }}</option>
                    <option value="sale" {{ request('purpose') === 'sale' ? 'selected' : '' }}>{{ translate('For_Sale') }}</option>
                    <option value="rent" {{ request('purpose') === 'rent' ? 'selected' : '' }}>{{ translate('For_Rent') }}</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="text" name="city" class="form-control" placeholder="{{ translate('City') }}" value="{{ request('city') }}">
            </div>
            <div class="col-md-2">
                <input type="number" name="min_price" class="form-control" placeholder="{{ translate('Min_Price') }}" value="{{ request('min_price') }}">
            </div>
            <div class="col-md-2">
                <input type="number" name="max_price" class="form-control" placeholder="{{ translate('Max_Price') }}" value="{{ request('max_price') }}">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">{{ translate('Search') }}</button>
            </div>
        </form>

        <div class="row g-4">
            @forelse($listings as $listing)
                <div class="col-md-4">
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
                </div>
            @empty
                <div class="col-12 text-center text-muted py-5">{{ translate('No_listings_found') }}</div>
            @endforelse
        </div>

        <div class="mt-4">{{ $listings->links() }}</div>
    </div>
@endsection
