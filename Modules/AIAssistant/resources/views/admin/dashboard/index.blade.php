@extends('layouts.admin.app')

@section('title', translate('AI_Dashboard'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 mb-sm-20">
            <h2 class="h1 mb-0 text-capitalize">{{ translate('Platform_AI_Dashboard') }}</h2>
            <form method="get" class="d-flex gap-2">
                <select name="days" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach([7 => '7_days', 30 => '30_days', 90 => '90_days'] as $value => $label)
                        <option value="{{ $value }}" {{ $days == $value ? 'selected' : '' }}>{{ translate($label) }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        <div class="row g-3 mb-3 mb-lg-5">
            @php($cards = [
                ['Total_Vendors', $summary['total_vendors']],
                ['Active_AI_Assistants', $summary['active_ai_assistants']],
                ['AI_Conversations', $summary['ai_conversations']],
                ['AI_Orders', $summary['ai_orders']],
                ['Conversion_Rate', $summary['conversion_rate'] . '%'],
                ['WhatsApp_Notifications', $summary['whatsapp_notifications']],
            ])
            @foreach($cards as [$label, $value])
                <div class="col-md-4 col-lg-2">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="small text-muted text-truncate">{{ translate($label) }}</div>
                            <div class="h3 mb-0">{{ $value }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row g-3 mb-3 mb-lg-5">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">{{ translate('Cost') }}</h5></div>
                    <div class="card-body">
                        <p class="mb-1">
                            {{ translate('Platform_managed_provider_cost') }}:
                            <strong>{{ number_format($summary['platform_ai_cost'], 4) }}</strong>
                        </p>
                        <p class="mb-0 small text-muted">
                            {{ translate('Vendor_owned_usage_never_billed_to_the_platform') }}:
                            {{ number_format($summary['vendor_owned_usage_tokens']) }} {{ translate('tokens') }}
                        </p>
                        <p class="mb-0 small text-muted">{{ translate('Total_AI_usage') }}: {{ number_format($summary['total_ai_usage_tokens']) }} {{ translate('tokens') }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">{{ translate('Reliability') }}</h5></div>
                    <div class="card-body">
                        <p class="mb-1">{{ translate('Failed_AI_jobs') }}: <strong>{{ $summary['failed_ai_jobs'] }}</strong></p>
                        <p class="mb-0">{{ translate('Failed_WhatsApp_notifications') }}: <strong>{{ $summary['whatsapp_failed'] }}</strong></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="mb-0">{{ translate('Top_Vendors_by_AI_Orders') }}</h5></div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>{{ translate('Vendor') }}</th><th>{{ translate('AI_Orders') }}</th></tr></thead>
                    <tbody>
                    @forelse($topVendors as $vendor)
                        <tr><td>{{ $vendor['name'] }}</td><td>{{ $vendor['ai_orders'] }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="text-center text-muted py-4">{{ translate('No_AI_generated_orders_in_this_period_yet') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
