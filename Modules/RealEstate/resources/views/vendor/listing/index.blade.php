@extends('layouts.vendor.app')

@section('title', translate('My_Listings'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3 mb-sm-20">
            <h2 class="h1 mb-0 text-capitalize">{{ translate('My_Listings') }}</h2>
            <a href="{{ route('vendor.real-estate.listings.create') }}" class="btn btn-primary">{{ translate('Add_Listing') }}</a>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                    <tr>
                        <th>{{ translate('Title') }}</th>
                        <th>{{ translate('Type') }}</th>
                        <th>{{ translate('Purpose') }}</th>
                        <th>{{ translate('Price') }}</th>
                        <th>{{ translate('Status') }}</th>
                        <th>{{ translate('Views') }}</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($listings as $listing)
                        <tr>
                            <td>{{ $listing->title }}</td>
                            <td class="text-capitalize">{{ translate($listing->listing_type) }}</td>
                            <td class="text-capitalize">{{ translate($listing->purpose) }}</td>
                            <td>{{ number_format($listing->price, 2) }}</td>
                            <td>
                                @php($badge = ['pending' => 'warning text-dark', 'approved' => 'success', 'denied' => 'danger', 'sold' => 'secondary', 'rented' => 'secondary'][$listing->status] ?? 'secondary')
                                <span class="badge bg-{{ $badge }}">{{ translate(ucfirst($listing->status)) }}</span>
                            </td>
                            <td>{{ $listing->views_count }}</td>
                            <td class="text-end">
                                <a href="{{ route('vendor.real-estate.listings.edit', $listing->id) }}" class="btn btn-sm btn-outline-secondary">{{ translate('edit') }}</a>

                                @if($listing->status === 'approved' && $listing->purpose === 'sale')
                                    <form method="post" action="{{ route('vendor.real-estate.listings.mark-sold', $listing->id) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-primary">{{ translate('Mark_Sold') }}</button>
                                    </form>
                                @elseif($listing->status === 'approved' && $listing->purpose === 'rent')
                                    <form method="post" action="{{ route('vendor.real-estate.listings.mark-rented', $listing->id) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-primary">{{ translate('Mark_Rented') }}</button>
                                    </form>
                                @endif

                                <form method="post" action="{{ route('vendor.real-estate.listings.destroy', $listing->id) }}" class="d-inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('{{ translate('are_you_sure') }}')">{{ translate('delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">{{ translate('No_listings_yet') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $listings->links() }}</div>
        </div>
    </div>
@endsection
