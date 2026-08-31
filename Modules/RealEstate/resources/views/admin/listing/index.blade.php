@extends('layouts.admin.app')

@section('title', translate('Real_Estate_Listings'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3 mb-sm-20">
            <h2 class="h1 mb-0 text-capitalize">{{ translate('Real_Estate_Listings') }}</h2>
            <form method="get" class="d-flex gap-2">
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">{{ translate('All_Statuses') }}</option>
                    @foreach(['pending', 'approved', 'denied', 'sold', 'rented'] as $status)
                        <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>{{ translate(ucfirst($status)) }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                    <tr>
                        <th>{{ translate('Title') }}</th>
                        <th>{{ translate('Broker') }}</th>
                        <th>{{ translate('Type') }}</th>
                        <th>{{ translate('Purpose') }}</th>
                        <th>{{ translate('Price') }}</th>
                        <th>{{ translate('Status') }}</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($listings as $listing)
                        <tr>
                            <td>{{ $listing->title }}</td>
                            <td>{{ $listing->broker?->agency_name ?: $listing->broker?->seller?->f_name . ' ' . $listing->broker?->seller?->l_name }}</td>
                            <td class="text-capitalize">{{ translate($listing->listing_type) }}</td>
                            <td class="text-capitalize">{{ translate($listing->purpose) }}</td>
                            <td>{{ number_format($listing->price, 2) }}</td>
                            <td>
                                @php($badge = ['pending' => 'warning text-dark', 'approved' => 'success', 'denied' => 'danger', 'sold' => 'secondary', 'rented' => 'secondary'][$listing->status] ?? 'secondary')
                                <span class="badge bg-{{ $badge }}">{{ translate(ucfirst($listing->status)) }}</span>
                                @if($listing->status === 'denied' && $listing->denied_note)
                                    <div class="small text-danger">{{ $listing->denied_note }}</div>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($listing->status === 'pending')
                                    <form method="post" action="{{ route('admin.real-estate.listings.approve', $listing->id) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-success">{{ translate('Approve') }}</button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#js-deny-modal-{{ $listing->id }}">{{ translate('Deny') }}</button>

                                    <div class="modal fade" id="js-deny-modal-{{ $listing->id }}">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="post" action="{{ route('admin.real-estate.listings.deny', $listing->id) }}">
                                                    @csrf
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">{{ translate('Deny_Listing') }}</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <label class="form-label">{{ translate('Reason') }}</label>
                                                        <textarea name="denied_note" class="form-control" required maxlength="255"></textarea>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="submit" class="btn btn-danger">{{ translate('Deny') }}</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">{{ translate('No_listings_found') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $listings->links() }}</div>
        </div>
    </div>
@endsection
