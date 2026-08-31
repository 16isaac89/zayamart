@extends('layouts.front-end.app')

@section('title', $listing->title)

@section('content')
    <div class="container py-4">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @forelse($listing->images_full_url as $image)
                        @if($image['path'])
                            <img src="{{ $image['path'] }}" alt="{{ $listing->title }}" style="width:180px;height:140px;object-fit:cover;border-radius:6px;">
                        @endif
                    @empty
                        <div class="bg-light d-flex align-items-center justify-content-center w-100" style="height:280px;">
                            <span class="text-muted">{{ translate('No_Image') }}</span>
                        </div>
                    @endforelse
                </div>

                <h1 class="h3">{{ $listing->title }}</h1>
                <p class="text-muted text-capitalize">
                    {{ translate($listing->listing_type) }} · {{ translate($listing->purpose === 'rent' ? 'For_Rent' : 'For_Sale') }}
                    @if($listing->address || $listing->city) · {{ trim(($listing->address ? $listing->address . ', ' : '') . $listing->city) }} @endif
                </p>
                <p class="h4 text-primary">
                    {{ number_format($listing->price, 2) }}
                    @if($listing->purpose === 'rent' && $listing->price_period)
                        / {{ translate($listing->price_period) }}
                    @endif
                </p>

                @if($listing->description)
                    <p class="mt-3">{{ $listing->description }}</p>
                @endif

                <h5 class="mt-4">{{ translate('Features') }}</h5>
                <table class="table table-sm w-auto">
                    <tbody>
                        @if($listing->area_size)
                            <tr><th>{{ translate('Area') }}</th><td>{{ $listing->area_size }} {{ $listing->area_unit }}</td></tr>
                        @endif
                        @if($listing->isHouse())
                            @if($listing->bedrooms !== null)
                                <tr><th>{{ translate('Bedrooms') }}</th><td>{{ $listing->bedrooms }}</td></tr>
                            @endif
                            @if($listing->bathrooms !== null)
                                <tr><th>{{ translate('Bathrooms') }}</th><td>{{ $listing->bathrooms }}</td></tr>
                            @endif
                            @if($listing->floors !== null)
                                <tr><th>{{ translate('Floors') }}</th><td>{{ $listing->floors }}</td></tr>
                            @endif
                            @if($listing->year_built !== null)
                                <tr><th>{{ translate('Year_Built') }}</th><td>{{ $listing->year_built }}</td></tr>
                            @endif
                            @if($listing->furnished !== null)
                                <tr><th>{{ translate('Furnished') }}</th><td>{{ $listing->furnished ? translate('Yes') : translate('No') }}</td></tr>
                            @endif
                        @endif
                        @if($listing->parking_spaces !== null)
                            <tr><th>{{ translate('Parking_Spaces') }}</th><td>{{ $listing->parking_spaces }}</td></tr>
                        @endif
                    </tbody>
                </table>

                @if(!empty(array_filter($listing->amenities ?? [])))
                    <h5 class="mt-3">{{ translate('Amenities') }}</h5>
                    <ul class="list-inline">
                        @foreach(array_filter($listing->amenities ?? []) as $key => $enabled)
                            <li class="list-inline-item badge bg-light text-dark border">{{ translate(config('realestate.amenities.' . $key, $key)) }}</li>
                        @endforeach
                    </ul>
                @endif

                @if($otherListings->isNotEmpty())
                    <h5 class="mt-4">{{ translate('More_from_this_broker') }}</h5>
                    <div class="row g-3">
                        @foreach($otherListings as $other)
                            <div class="col-md-6">
                                <a href="{{ route('real-estate.show', $other->slug) }}" class="text-decoration-none text-dark">
                                    <div class="card">
                                        <div class="card-body py-2">
                                            <div class="fw-semibold">{{ $other->title }}</div>
                                            <div class="small text-muted">{{ number_format($other->price, 2) }}</div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-body">
                        <h6>{{ translate('Listed_by') }}</h6>
                        <p class="mb-1">{{ $listing->broker->agency_name ?: ($listing->broker->seller->f_name . ' ' . $listing->broker->seller->l_name) }}</p>

                        @if($whatsappLink)
                            <a href="{{ $whatsappLink }}" target="_blank" rel="noopener noreferrer" class="btn btn-success w-100 mb-2">
                                {{ translate('Chat_on_WhatsApp') }}
                            </a>
                        @endif
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h6>{{ translate('Send_an_Inquiry') }}</h6>

                        @if(session('success'))
                            <div class="alert alert-success">{{ session('success') }}</div>
                        @endif

                        <form method="post" action="{{ route('real-estate.inquiry', $listing->slug) }}">
                            @csrf
                            <div class="form-group mb-2">
                                <input type="text" name="guest_name" class="form-control" placeholder="{{ translate('Your_Name') }}" value="{{ old('guest_name') }}" required>
                            </div>
                            <div class="form-group mb-2">
                                <input type="text" name="guest_phone" class="form-control" placeholder="{{ translate('Phone') }}" value="{{ old('guest_phone') }}" required>
                            </div>
                            <div class="form-group mb-2">
                                <input type="email" name="guest_email" class="form-control" placeholder="{{ translate('Email_optional') }}" value="{{ old('guest_email') }}">
                            </div>
                            <div class="form-group mb-2">
                                <textarea name="message" class="form-control" rows="3" placeholder="{{ translate('Message') }}" required>{{ old('message') }}</textarea>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">{{ translate('Send_Inquiry') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
