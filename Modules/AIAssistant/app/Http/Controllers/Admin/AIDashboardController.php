<?php

namespace Modules\AIAssistant\app\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\AIAssistant\app\Services\AiDashboardAggregationService;

/**
 * brief §28. Platform-wide — distinguishes platform-managed cost from
 * vendor-owned usage explicitly (never sums them) — see
 * AiDashboardAggregationService::platformSummary().
 */
class AIDashboardController extends Controller
{
    public function __construct(private readonly AiDashboardAggregationService $aggregation)
    {
    }

    public function index(Request $request): View
    {
        $days = (int)$request->input('days', 30);
        $to = Carbon::now();
        $from = (clone $to)->subDays($days);

        $summary = $this->aggregation->platformSummary($from, $to);
        $topVendors = $this->aggregation->topVendorsByAiOrders($from, $to);

        return view('aiassistant::admin.dashboard.index', compact('summary', 'topVendors', 'days'));
    }
}
