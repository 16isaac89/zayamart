@extends('layouts.vendor.app')

@section('title', translate('Add_Listing'))

@section('content')
    <div class="content container-fluid">
        <h2 class="h1 mb-3 mb-sm-20 text-capitalize">{{ translate('Add_Listing') }}</h2>

        <div class="card">
            <div class="card-body">
                <form method="post" action="{{ route('vendor.real-estate.listings.store') }}" enctype="multipart/form-data">
                    @csrf
                    @include('realestate::vendor.listing._form')
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">{{ translate('Submit_for_Review') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
