@extends('layouts.vendor.app')

@section('title', translate('Conversation'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h2 class="h1 mb-0">{{ $conversation->customer?->name ?? 'Guest #' . $conversation->guest_id }}</h2>
                <span class="badge" id="js-support-status">{{ translate($conversation->support_status) }}</span>
            </div>
            <div>
                @if($conversation->support_status !== 'human_active')
                    <form method="post" action="{{ route('vendor.ai-assistant.inbox.take-over', $conversation->id) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-primary">{{ translate('Take_Over') }}</button>
                    </form>
                @else
                    <form method="post" action="{{ route('vendor.ai-assistant.inbox.return-to-ai', $conversation->id) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary">{{ translate('Return_to_AI') }}</button>
                    </form>
                @endif
                <a href="{{ route('vendor.ai-assistant.inbox.index') }}" class="btn btn-outline-secondary">{{ translate('back') }}</a>
            </div>
        </div>

        <div class="card">
            <div class="card-body" id="js-messages" style="max-height: 60vh; overflow-y: auto;">
                @foreach($messages as $message)
                    <div class="mb-3 {{ $message->sender_type === 'customer' ? '' : 'text-end' }}">
                        <div class="small text-muted">
                            {{ $message->sender_name ?: translate($message->sender_type) }}
                            @if($message->sender_type === 'human')
                                <span class="badge bg-primary">{{ translate('Human_Agent') }}</span>
                            @elseif($message->sender_type === 'ai')
                                <span class="badge bg-secondary">{{ translate('AI') }}</span>
                            @endif
                        </div>
                        <div class="d-inline-block p-2 rounded {{ $message->sender_type === 'customer' ? 'bg-light' : 'bg-primary text-white' }}">
                            {{ $message->content }}
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="card-footer">
                <form method="post" action="{{ route('vendor.ai-assistant.inbox.reply', $conversation->id) }}" class="d-flex gap-2" id="js-reply-form">
                    @csrf
                    <input type="text" name="message" class="form-control" maxlength="2000" required
                           placeholder="{{ translate('Type_your_reply') }}"
                           {{ $conversation->support_status !== 'human_active' ? 'disabled' : '' }}>
                    <button type="submit" class="btn btn-primary" {{ $conversation->support_status !== 'human_active' ? 'disabled' : '' }}>
                        {{ translate('send') }}
                    </button>
                </form>
                @if($conversation->support_status !== 'human_active')
                    <p class="small text-muted mt-2 mb-0">{{ translate('Take_over_this_conversation_to_reply') }}</p>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        // Lightweight polling — this project's BROADCAST_DRIVER is 'log' by
        // default (no live socket push wired up), so polling is the honest
        // delivery mechanism here, matching the customer widget's own approach.
        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str ?? '';
            return div.innerHTML;
        }

        let lastCount = {{ $messages->count() }};
        setInterval(function () {
            fetch('{{ route('vendor.ai-assistant.inbox.poll', $conversation->id) }}')
                .then(r => r.json())
                .then(data => {
                    document.getElementById('js-support-status').textContent = data.support_status;
                    if (data.messages.length !== lastCount) {
                        lastCount = data.messages.length;
                        const container = document.getElementById('js-messages');
                        container.innerHTML = data.messages.map(function (m) {
                            const isCustomer = m.sender_type === 'customer';
                            const badge = m.sender_type === 'human' ? '<span class="badge bg-primary">{{ translate('Human_Agent') }}</span>'
                                : m.sender_type === 'ai' ? '<span class="badge bg-secondary">{{ translate('AI') }}</span>' : '';
                            const name = escapeHtml(m.sender_name || m.sender_type);
                            return `<div class="mb-3 ${isCustomer ? '' : 'text-end'}">`
                                + `<div class="small text-muted">${name} ${badge}</div>`
                                + `<div class="d-inline-block p-2 rounded ${isCustomer ? 'bg-light' : 'bg-primary text-white'}">${escapeHtml(m.content)}</div>`
                                + `</div>`;
                        }).join('');
                        container.scrollTop = container.scrollHeight;
                    }
                });
        }, 6000);
    </script>
@endpush
