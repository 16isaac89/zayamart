@extends('layouts.vendor.app')

@section('title', translate('Inquiry'))

@section('content')
    <div class="content container-fluid">
        <h2 class="h1 mb-3 mb-sm-20 text-capitalize">{{ translate('Inquiry') }}</h2>

        <div class="card">
            <div class="card-body">
                <p><strong>{{ translate('Listing') }}:</strong>
                    @if($inquiry->listing)
                        <a href="{{ route('vendor.real-estate.listings.edit', $inquiry->listing->id) }}">{{ $inquiry->listing->title }}</a>
                    @else
                        {{ translate('Deleted_listing') }}
                    @endif
                </p>
                <p><strong>{{ translate('Name') }}:</strong> {{ $inquiry->guest_name }}</p>
                <p><strong>{{ translate('Phone') }}:</strong> {{ $inquiry->guest_phone }}</p>
                @if($inquiry->guest_email)
                    <p><strong>{{ translate('Email') }}:</strong> {{ $inquiry->guest_email }}</p>
                @endif
                <p><strong>{{ translate('Message') }}:</strong></p>
                <p class="border rounded p-3 bg-light">{{ $inquiry->message }}</p>

                <form method="post" action="{{ route('vendor.real-estate.inquiries.status', $inquiry->id) }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-auto">
                        <label class="form-label">{{ translate('Status') }}</label>
                        <select name="status" class="form-select">
                            <option value="new" {{ $inquiry->status === 'new' ? 'selected' : '' }}>{{ translate('New') }}</option>
                            <option value="contacted" {{ $inquiry->status === 'contacted' ? 'selected' : '' }}>{{ translate('Contacted') }}</option>
                            <option value="closed" {{ $inquiry->status === 'closed' ? 'selected' : '' }}>{{ translate('Closed') }}</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary">{{ translate('Update_Status') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
