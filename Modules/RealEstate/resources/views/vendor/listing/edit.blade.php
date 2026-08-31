@extends('layouts.vendor.app')

@section('title', translate('Edit_Listing'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3 mb-sm-20">
            <h2 class="h1 mb-0 text-capitalize">{{ translate('Edit_Listing') }}</h2>
            <span class="badge bg-{{ $listing->status === 'approved' ? 'success' : ($listing->status === 'denied' ? 'danger' : 'warning text-dark') }}">
                {{ translate(ucfirst($listing->status)) }}
            </span>
        </div>

        @if($listing->status === 'denied' && $listing->denied_note)
            <div class="alert alert-danger">{{ translate('Denial_reason') }}: {{ $listing->denied_note }}</div>
        @endif

        <div class="card">
            <div class="card-body">
                <form method="post" action="{{ route('vendor.real-estate.listings.update', $listing->id) }}" enctype="multipart/form-data">
                    @csrf
                    @include('realestate::vendor.listing._form')
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">{{ translate('Save_and_Resubmit') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
