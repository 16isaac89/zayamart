@extends('layouts.vendor.app')

@section('title', translate('AI_Dashboard'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 mb-sm-20">
            <h2 class="h1 mb-0 text-capitalize">{{ translate('AI_Dashboard') }}</h2>
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
                ['AI_Conversations', $summary['conversations']],
                ['AI_Orders', $summary['ai_orders']],
                ['Conversion_Rate', $summary['conversion_rate'] . '%'],
                ['Average_Order_Value', number_format($summary['average_order_value'], 2)],
                ['Human_Handoffs', $summary['human_handoffs']],
                ['WhatsApp_Sent', $summary['whatsapp_sent']],
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
                    <div class="card-header"><h5 class="mb-0">{{ translate('AI_Usage_This_Period') }}</h5></div>
                    <div class="card-body">
                        <p class="mb-1">{{ translate('Total_tokens') }}: <strong>{{ number_format($summary['total_tokens']) }}</strong></p>
                        <p class="mb-1">{{ translate('Estimated_cost_billed_to_you') }}: <strong>{{ number_format($summary['estimated_cost_platform_billed'], 4) }}</strong></p>
                        <p class="mb-0 small text-muted">{{ translate('Estimated_cost_from_your_own_provider_account') }}: {{ number_format($summary['estimated_cost_vendor_owned'], 4) }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">{{ translate('AI_vs_Human') }}</h5></div>
                    <div class="card-body">
                        @if(($handlingSplit['ai'] + $handlingSplit['human']) > 0)
                            <div id="ai-human-chart"></div>
                        @else
                            <p class="text-muted mb-0">{{ translate('No_conversations_in_this_period_yet') }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="mb-0">{{ translate('Conversations_Over_Time') }}</h5></div>
            <div class="card-body">
                @if(count($conversationsPerDay) > 0)
                    <div id="conversations-chart"></div>
                @else
                    <p class="text-muted mb-0">{{ translate('No_conversations_in_this_period_yet') }}</p>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script src="{{ dynamicAsset(path: 'public/assets/back-end/js/apexcharts.js') }}"></script>
    <script>
        @if(count($conversationsPerDay) > 0)
        new ApexCharts(document.getElementById('conversations-chart'), {
            chart: {type: 'area', height: 280, toolbar: {show: false}},
            series: [{name: '{{ translate('conversations') }}', data: @json(array_column($conversationsPerDay, 'count'))}],
            xaxis: {categories: @json(array_column($conversationsPerDay, 'date'))},
            dataLabels: {enabled: false},
            stroke: {curve: 'smooth'},
        }).render();
        @endif

        @if(($handlingSplit['ai'] + $handlingSplit['human']) > 0)
        new ApexCharts(document.getElementById('ai-human-chart'), {
            chart: {type: 'donut', height: 240},
            series: [{{ $handlingSplit['ai'] }}, {{ $handlingSplit['human'] }}],
            labels: ['{{ translate('AI') }}', '{{ translate('Human') }}'],
        }).render();
        @endif
    </script>
@endpush
