@extends('layouts.vendor.app')

@section('title', translate('Inbox'))

@section('content')
    <div class="content container-fluid">
        <h2 class="h1 mb-3 mb-sm-20 text-capitalize">{{ translate('Conversation_Inbox') }}</h2>

        <ul class="nav nav-pills mb-3">
            @foreach(['all' => 'All', 'ai' => 'AI', 'human' => 'Human', 'needs_attention' => 'Needs_attention', 'unread' => 'Unread'] as $key => $label)
                <li class="nav-item">
                    <a class="nav-link {{ $filter === $key ? 'active' : '' }}" href="{{ route('vendor.ai-assistant.inbox.index', ['filter' => $key]) }}">
                        {{ translate($label) }}
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="card">
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead>
                    <tr>
                        <th>{{ translate('Customer') }}</th>
                        <th>{{ translate('Last_message') }}</th>
                        <th>{{ translate('Mode') }}</th>
                        <th>{{ translate('Status') }}</th>
                        <th>{{ translate('Updated') }}</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($conversations as $conversation)
                        @php($lastMessage = $conversation->messages->first())
                        <tr>
                            <td>{{ $conversation->customer?->name ?? 'Guest #' . $conversation->guest_id }}</td>
                            <td class="text-truncate" style="max-width: 260px;">{{ $lastMessage?->content }}</td>
                            <td>
                                @if($conversation->support_status === 'human_active')
                                    <span class="badge bg-primary">{{ translate('Human') }}</span>
                                @elseif($conversation->support_status === 'human_requested')
                                    <span class="badge bg-danger">{{ translate('Needs_attention') }}</span>
                                @else
                                    <span class="badge bg-secondary">{{ translate('AI') }}</span>
                                @endif
                            </td>
                            <td>{{ translate($conversation->support_status) }}</td>
                            <td>{{ $conversation->updated_at->diffForHumans() }}</td>
                            <td><a href="{{ route('vendor.ai-assistant.inbox.show', $conversation->id) }}" class="btn btn-sm btn-outline-primary">{{ translate('Open') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">{{ translate('No_conversations_yet') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $conversations->links() }}</div>
        </div>
    </div>
@endsection
