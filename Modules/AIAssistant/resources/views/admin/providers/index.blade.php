@extends('layouts.admin.app')

@section('title', translate('AI_Providers'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3 mb-sm-20">
            <h2 class="h1 mb-0 text-capitalize">{{ translate('AI_Providers') }}</h2>
        </div>

        <p class="text-muted">
            {{ translate('Connect_one_or_more_AI_providers_below_Switching_the_platform_default_model_never_requires_a_code_change') }}
        </p>

        <div class="row g-3">
            @foreach($providers as $provider)
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">{{ $provider->display_name }}</h5>
                            <span class="badge {{ $provider->isConnected() ? 'bg-success' : 'bg-secondary' }}">
                                {{ $provider->isConnected() ? translate('Connected') : translate('Disabled') }}
                            </span>
                        </div>
                        <div class="card-body">
                            <form method="post" action="{{ route('admin.ai-assistant.providers.update', $provider->id) }}" class="mb-4">
                                @csrf
                                <div class="mb-2">
                                    <label class="form-label">{{ translate('API_Key') }}</label>
                                    <input type="password" class="form-control" name="api_key" placeholder="{{ $provider->api_key ? '••••••••' : '' }}">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">{{ translate('Base_URL') }} <span class="text-muted small">({{ translate('optional_leave_blank_for_provider_default') }})</span></label>
                                    <input type="text" class="form-control" name="base_url" value="{{ $provider->base_url }}">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">{{ translate('Status') }}</label>
                                    <select class="form-select" name="status">
                                        <option value="connected" {{ $provider->status == 'connected' ? 'selected' : '' }}>{{ translate('Connected') }}</option>
                                        <option value="disabled" {{ $provider->status == 'disabled' ? 'selected' : '' }}>{{ translate('Disabled') }}</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">{{ translate('save') }}</button>
                            </form>

                            <form method="post" action="{{ route('admin.ai-assistant.providers.vendor-availability', $provider->id) }}" class="mb-4 border-top pt-3">
                                @csrf
                                <p class="small text-muted mb-2">{{ translate('Vendor_availability') }}</p>
                                <div class="form-check form-switch mb-1">
                                    <input class="form-check-input" type="checkbox" role="switch" name="vendor_owned_allowed" value="1"
                                           id="voa-{{ $provider->id }}" {{ $provider->vendor_owned_allowed ? 'checked' : '' }}>
                                    <label class="form-check-label small" for="voa-{{ $provider->id }}">{{ translate('Vendors_may_bring_their_own_API_key') }}</label>
                                </div>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" role="switch" name="vendor_managed_available" value="1"
                                           id="vma-{{ $provider->id }}" {{ $provider->vendor_managed_available ? 'checked' : '' }}>
                                    <label class="form-check-label small" for="vma-{{ $provider->id }}">{{ translate('Vendors_may_use_this_platform_managed_provider') }}</label>
                                </div>
                                <button type="submit" class="btn btn-outline-secondary btn-sm">{{ translate('save') }}</button>
                            </form>

                            <h6>{{ translate('Models') }}</h6>
                            <table class="table table-sm">
                                <thead>
                                <tr>
                                    <th>{{ translate('Model') }}</th>
                                    <th>{{ translate('Input') }} / 1M</th>
                                    <th>{{ translate('Output') }} / 1M</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($provider->models as $model)
                                    <tr>
                                        <td>{{ $model->model_name }}</td>
                                        <td>{{ $model->input_price }} {{ $model->currency }}</td>
                                        <td>{{ $model->output_price }} {{ $model->currency }}</td>
                                        <td>
                                            <form method="post" action="{{ route('admin.ai-assistant.providers.default') }}">
                                                @csrf
                                                <input type="hidden" name="ai_provider_model_id" value="{{ $model->id }}">
                                                <button type="submit" class="btn btn-outline-primary btn-sm">
                                                    {{ translate('Make_platform_default') }}
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>

                            <form method="post" action="{{ route('admin.ai-assistant.providers.models.store') }}" class="row g-2 align-items-end">
                                @csrf
                                <input type="hidden" name="ai_provider_id" value="{{ $provider->id }}">
                                <div class="col-4">
                                    <input type="text" class="form-control form-control-sm" name="model_name" placeholder="{{ translate('model_name') }}" required>
                                </div>
                                <div class="col-2">
                                    <input type="number" step="0.000001" class="form-control form-control-sm" name="input_price" placeholder="{{ translate('input') }}" required>
                                </div>
                                <div class="col-2">
                                    <input type="number" step="0.000001" class="form-control form-control-sm" name="output_price" placeholder="{{ translate('output') }}" required>
                                </div>
                                <div class="col-2">
                                    <input type="text" class="form-control form-control-sm" name="currency" value="USD" required>
                                </div>
                                <div class="col-2">
                                    <button type="submit" class="btn btn-secondary btn-sm w-100">{{ translate('add') }}</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card mt-3">
            <div class="card-header"><h5 class="mb-0">{{ translate('Current_platform_default') }}</h5></div>
            <div class="card-body">
                @php($default = $configs->firstWhere('is_platform_default', true))
                @if($default)
                    <p class="mb-0">{{ $default->provider->display_name }} — {{ $default->model->model_name }}</p>
                @else
                    <p class="mb-0 text-muted">{{ translate('No_platform_default_model_selected_yet') }}</p>
                @endif
            </div>
        </div>
    </div>
@endsection
