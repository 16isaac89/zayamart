@extends('layouts.vendor.app')

@section('title', translate('Broker_Profile'))

@section('content')
    <div class="content container-fluid">
        <h2 class="h1 mb-3 mb-sm-20 text-capitalize">{{ translate('Broker_Profile') }}</h2>

        @if(!$broker->exists)
            <div class="alert alert-info">{{ translate('Save_this_form_to_start_listing_properties_as_a_broker') }}</div>
        @endif

        <div class="card">
            <div class="card-body">
                <form method="post" action="{{ route('vendor.real-estate.update') }}" class="row g-3">
                    @csrf
                    <div class="col-md-6">
                        <label class="form-label">{{ translate('Agency_Name') }}</label>
                        <input type="text" name="agency_name" class="form-control" value="{{ old('agency_name', $broker->agency_name) }}" maxlength="191">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ translate('License_Number') }}</label>
                        <input type="text" name="license_number" class="form-control" value="{{ old('license_number', $broker->license_number) }}" maxlength="191">
                    </div>
                    <div class="col-12">
                        <label class="form-label">{{ translate('Bio') }}</label>
                        <textarea name="bio" class="form-control" rows="4" maxlength="2000">{{ old('bio', $broker->bio) }}</textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">{{ translate('save') }}</button>
                        @if($broker->exists)
                            <a href="{{ route('vendor.real-estate.listings.index') }}" class="btn btn-outline-secondary">{{ translate('View_My_Listings') }}</a>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
