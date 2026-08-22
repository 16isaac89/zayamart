@extends('layouts.vendor.app')

@section('title', translate('WhatsApp'))

@section('content')
    <div class="content container-fluid">
        <h2 class="h1 mb-3 mb-sm-20 text-capitalize">{{ translate('WhatsApp_Notifications') }}</h2>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ translate('Your_WhatsApp_Cloud_API') }}</h5>
                <p class="small text-muted mb-0">{{ translate('Leave_blank_to_keep_using_the_platform_default_WhatsApp_channel') }}</p>
            </div>
            <div class="card-body">
                @if($settings->exists)
                    <p class="mb-3">
                        {{ translate('Status') }}:
                        <span class="badge {{ $settings->isConnected() ? 'bg-success' : 'bg-secondary' }}">
                            {{ $settings->isConnected() ? translate('Connected') : translate('Not_connected') }}
                        </span>
                        @if($settings->last_test_message)
                            <span class="small text-muted">— {{ $settings->last_test_message }}</span>
                        @endif
                    </p>
                @endif

                <form method="post" action="{{ route('vendor.ai-assistant.whatsapp.update') }}" class="row g-3">
                    @csrf
                    <div class="col-md-6">
                        <label class="form-label">{{ translate('Access_Token') }}</label>
                        <input type="password" class="form-control" name="access_token" placeholder="{{ $settings->exists ? '••••••••' : '' }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ translate('Phone_Number_ID') }}</label>
                        <input type="text" class="form-control" name="phone_number_id" value="{{ $settings->phone_number_id }}">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">{{ translate('save') }}</button>
                        @if($settings->exists)
                            <button type="button" id="js-test-whatsapp" class="btn btn-outline-secondary">{{ translate('Test_Connection') }}</button>
                            <span id="js-whatsapp-result" class="small ms-2"></span>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        document.getElementById('js-test-whatsapp')?.addEventListener('click', function () {
            const resultEl = document.getElementById('js-whatsapp-result');
            resultEl.textContent = '{{ translate('Testing') }}…';
            fetch('{{ route('vendor.ai-assistant.whatsapp.test-connection') }}', {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            })
                .then(r => r.json())
                .then(data => {
                    resultEl.textContent = (data.success ? '✓ ' : '✕ ') + data.message;
                    resultEl.className = 'small ms-2 ' + (data.success ? 'text-success' : 'text-danger');
                });
        });
    </script>
@endpush
