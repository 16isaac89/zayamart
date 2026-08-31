@extends('layouts.vendor.app')

@section('title', translate('Real_Estate_Inquiries'))

@section('content')
    <div class="content container-fluid">
        <h2 class="h1 mb-3 mb-sm-20 text-capitalize">{{ translate('Real_Estate_Inquiries') }}</h2>

        <div class="card">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                    <tr>
                        <th>{{ translate('Listing') }}</th>
                        <th>{{ translate('Name') }}</th>
                        <th>{{ translate('Phone') }}</th>
                        <th>{{ translate('Status') }}</th>
                        <th>{{ translate('Received') }}</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($inquiries as $inquiry)
                        <tr>
                            <td>{{ $inquiry->listing->title ?? translate('Deleted_listing') }}</td>
                            <td>{{ $inquiry->guest_name }}</td>
                            <td>{{ $inquiry->guest_phone }}</td>
                            <td>
                                @php($badge = ['new' => 'warning text-dark', 'contacted' => 'info', 'closed' => 'secondary'][$inquiry->status] ?? 'secondary')
                                <span class="badge bg-{{ $badge }}">{{ translate(ucfirst($inquiry->status)) }}</span>
                            </td>
                            <td>{{ $inquiry->created_at->format('d M Y H:i') }}</td>
                            <td class="text-end">
                                <a href="{{ route('vendor.real-estate.inquiries.show', $inquiry->id) }}" class="btn btn-sm btn-outline-secondary">{{ translate('view') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">{{ translate('No_inquiries_yet') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $inquiries->links() }}</div>
        </div>
    </div>
@endsection
