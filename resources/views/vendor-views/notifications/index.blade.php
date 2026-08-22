@extends('layouts.vendor.app')

@section('title', translate('Notifications'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 mb-sm-20">
            <h2 class="h1 mb-0 text-capitalize">{{ translate('Notifications') }}</h2>
            <div class="d-flex gap-2">
                <a href="{{ route('vendor.notifications.settings') }}" class="btn btn-outline-secondary">
                    <i class="fi fi-rr-settings"></i> {{ translate('Settings') }}
                </a>
                @if($unreadCount > 0)
                    <form method="post" action="{{ route('vendor.notifications.mark-all-read') }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">{{ translate('Mark_all_as_read') }}</button>
                    </form>
                @endif
            </div>
        </div>

        <ul class="nav nav-pills mb-3">
            <li class="nav-item">
                <a class="nav-link {{ $filter === 'all' ? 'active' : '' }}" href="{{ route('vendor.notifications.index') }}">{{ translate('All') }}</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $filter === 'unread' ? 'active' : '' }}" href="{{ route('vendor.notifications.index', ['filter' => 'unread']) }}">
                    {{ translate('Unread') }} @if($unreadCount > 0)<span class="badge bg-danger">{{ $unreadCount }}</span>@endif
                </a>
            </li>
        </ul>

        <div class="card">
            <div class="list-group list-group-flush">
                @forelse($notifications as $notification)
                    @php($icon = match($notification->type) {
                        'new_order' => '🔔', 'payment_received' => '💰', 'customer_needs_help' => '👤',
                        'low_stock' => '📦', default => 'ℹ️',
                    })
                    <form method="post" action="{{ route('vendor.notifications.read', $notification->id) }}"
                          class="list-group-item list-group-item-action {{ $notification->isRead() ? '' : 'bg-light' }}">
                        @csrf
                        <button type="submit" class="btn btn-link text-start text-decoration-none w-100 p-0 text-dark">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <span>{{ $icon }}</span>
                                    <strong>{{ $notification->title }}</strong>
                                    @if(!$notification->isRead())
                                        <span class="badge bg-danger ms-1">{{ translate('new') }}</span>
                                    @endif
                                    <div class="text-muted small">{{ $notification->message }}</div>
                                </div>
                                <div class="text-muted small text-nowrap">{{ $notification->created_at->diffForHumans() }}</div>
                            </div>
                        </button>
                    </form>
                @empty
                    <div class="text-center text-muted py-5">{{ translate('No_notifications_yet') }}</div>
                @endforelse
            </div>
            <div class="card-footer">{{ $notifications->links() }}</div>
        </div>
    </div>
@endsection
