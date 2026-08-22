@extends('layouts.vendor.app')

@section('title', translate('AI_Provider'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3 mb-sm-20">
            <h2 class="h1 mb-0 text-capitalize">{{ translate('AI_Provider') }}</h2>
        </div>

        <div class="card mb-3 mb-lg-5">
            <div class="card-header"><h5 class="mb-0">{{ translate('Current_Configuration') }}</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="text-muted small">{{ translate('Billing_mode') }}</div>
                        <div class="fw-bold">
                            @switch($agent->billing_mode)
                                @case('vendor_owned') {{ translate('Your_own_API_key') }} @break
                                @case('platform_managed') {{ translate('Platform_managed_provider') }} @break
                                @default {{ translate('Platform_default') }}
                            @endswitch
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">{{ translate('Provider') }}</div>
                        <div class="fw-bold">
                            @if($agent->billing_mode === 'vendor_owned')
                                {{ $agent->vendorProvider?->provider?->display_name ?? '—' }}
                            @else
                                {{ ($agent->providerConfig?->provider ?? $platformDefault?->provider)?->display_name ?? '—' }}
                            @endif
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">{{ translate('Model') }}</div>
                        <div class="fw-bold">
                            @if($agent->billing_mode === 'vendor_owned')
                                {{ $agent->vendor_model_name ?? '—' }}
                            @else
                                {{ ($agent->providerConfig?->model ?? $platformDefault?->model)?->model_name ?? '—' }}
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <form action="{{ route('vendor.ai-assistant.provider.update') }}" method="post" class="card mb-3 mb-lg-5">
            @csrf
            <div class="card-header"><h5 class="mb-0">{{ translate('Choose_how_your_AI_is_powered') }}</h5></div>
            <div class="card-body">
                <div class="form-check mb-3">
                    <input class="form-check-input" type="radio" name="billing_mode" value="platform_default"
                           id="mode_default" {{ $agent->billing_mode === 'platform_default' ? 'checked' : '' }}>
                    <label class="form-check-label" for="mode_default">
                        <strong>{{ translate('Platform_Default') }}</strong>
                        <div class="small text-muted">{{ translate('Use_whatever_provider_model_the_platform_currently_defaults_to') }}</div>
                    </label>
                </div>

                @if($platformManagedConfigs->isNotEmpty())
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="radio" name="billing_mode" value="platform_managed"
                               id="mode_managed" {{ $agent->billing_mode === 'platform_managed' ? 'checked' : '' }}>
                        <label class="form-check-label" for="mode_managed"><strong>{{ translate('Platform_Managed_Provider') }}</strong></label>
                        <div class="small text-muted mb-2">{{ translate('Billed_to_the_platform_choose_a_specific_provider_model') }}</div>
                        <select class="form-select w-auto" name="ai_provider_config_id">
                            @foreach($platformManagedConfigs as $config)
                                <option value="{{ $config->id }}" {{ $agent->ai_provider_config_id == $config->id ? 'selected' : '' }}>
                                    {{ $config->provider->display_name }} — {{ $config->model->model_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="billing_mode" value="vendor_owned"
                           id="mode_vendor" {{ $agent->billing_mode === 'vendor_owned' ? 'checked' : '' }}
                           {{ $vendorProviders->isEmpty() ? 'disabled' : '' }}>
                    <label class="form-check-label" for="mode_vendor"><strong>{{ translate('My_Own_API_Key') }}</strong></label>
                    <div class="small text-muted mb-2">{{ translate('Billed_directly_to_your_own_provider_account_the_platform_never_sees_this_cost') }}</div>
                    @if($vendorProviders->isNotEmpty())
                        <div class="row g-2">
                            <div class="col-auto">
                                <select class="form-select" name="ai_provider_id">
                                    @foreach($vendorProviders as $vp)
                                        <option value="{{ $vp->id }}" {{ $agent->vendor_ai_provider_id == $vp->id ? 'selected' : '' }}>
                                            {{ $vp->provider->display_name }} ({{ $vp->isConnected() ? translate('connected') : translate('not_connected') }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-auto">
                                <input type="text" class="form-control" name="vendor_model_name" placeholder="{{ translate('model_name') }}"
                                       value="{{ $agent->vendor_model_name }}">
                            </div>
                        </div>
                    @else
                        <p class="small text-warning">{{ translate('Add_your_API_key_below_first') }}</p>
                    @endif
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">{{ translate('save') }}</button>
            </div>
        </form>

        <div class="card mb-3 mb-lg-5">
            <div class="card-header"><h5 class="mb-0">{{ translate('My_API_Keys') }}</h5></div>
            <div class="card-body">
                @foreach($availableProviders->where('vendor_owned_allowed', true) as $provider)
                    @php($existing = $vendorProviders->firstWhere('ai_provider_id', $provider->id))
                    <div class="border rounded p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong>{{ $provider->display_name }}</strong>
                            @if($existing)
                                <span class="badge {{ $existing->isConnected() ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $existing->isConnected() ? translate('Connected') : translate('Not_tested') }}
                                </span>
                            @endif
                        </div>
                        <form method="post" action="{{ route('vendor.ai-assistant.provider.credentials') }}" class="row g-2 align-items-end">
                            @csrf
                            <input type="hidden" name="ai_provider_id" value="{{ $provider->id }}">
                            <div class="col-md-5">
                                <label class="form-label small">{{ translate('API_Key') }}</label>
                                <input type="password" class="form-control" name="api_key" placeholder="{{ $existing ? '••••••••' : '' }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">{{ translate('Base_URL') }} ({{ translate('optional') }})</label>
                                <input type="text" class="form-control" name="base_url">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-outline-primary btn-sm w-100">{{ translate('save_key') }}</button>
                            </div>
                        </form>
                        @if($existing)
                            <div class="mt-2 d-flex align-items-center gap-2">
                                <input type="text" class="form-control form-control-sm w-auto js-test-model" placeholder="{{ translate('model_to_test_with') }}">
                                <button type="button" class="btn btn-sm btn-outline-secondary js-test-connection" data-provider-id="{{ $provider->id }}">
                                    {{ translate('Test_Connection') }}
                                </button>
                                <span class="js-test-result small"></span>
                            </div>
                            @if($existing->last_test_message)
                                <p class="small text-muted mb-0 mt-1">{{ translate('Last_test') }}: {{ $existing->last_test_message }}</p>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ translate('Provider_Comparison') }}</h5>
                <p class="small text-muted mb-0">{{ translate('Actual_costs_depend_on_conversation_length_model_usage_caching_and_provider_pricing') }}</p>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                    <tr>
                        <th>{{ translate('Provider') }}</th>
                        <th>{{ translate('Model') }}</th>
                        <th>{{ translate('Input') }} / 1M</th>
                        <th>{{ translate('Output') }} / 1M</th>
                        <th>{{ translate('Tools') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($availableProviders as $provider)
                        @foreach($provider->models as $model)
                            <tr>
                                <td>{{ $provider->display_name }}</td>
                                <td>{{ $model->model_name }}</td>
                                <td>{{ $model->input_price }} {{ $model->currency }}</td>
                                <td>{{ $model->output_price }} {{ $model->currency }}</td>
                                <td>{{ (data_get($model->capabilities, 'tool_calling', true)) ? '✓' : '—' }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        document.querySelectorAll('.js-test-connection').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const providerId = btn.dataset.providerId;
                const modelInput = btn.parentElement.querySelector('.js-test-model');
                const resultEl = btn.parentElement.querySelector('.js-test-result');
                resultEl.textContent = '{{ translate('Testing') }}…';

                fetch('{{ route('vendor.ai-assistant.provider.test-connection') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({ai_provider_id: providerId, model: modelInput.value}),
                })
                    .then(r => r.json())
                    .then(data => {
                        resultEl.textContent = (data.success ? '✓ ' : '✕ ') + data.message;
                        resultEl.className = 'js-test-result small ' + (data.success ? 'text-success' : 'text-danger');
                    })
                    .catch(() => { resultEl.textContent = '{{ translate('Could_not_reach_server') }}'; });
            });
        });
    </script>
@endpush
