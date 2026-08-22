<?php

namespace Modules\AIAssistant\app\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\AIAssistant\app\Services\AiDashboardAggregationService;

/**
 * brief §26/§27. Vendor-scoped only — see AiDashboardAggregationService for
 * where the seller_id filter is enforced.
 */
class VendorAIDashboardController extends Controller
{
    public function __construct(private readonly AiDashboardAggregationService $aggregation)
    {
    }

    public function index(Request $request): View
    {
        $sellerId = auth('seller')->id();
        $days = (int)$request->input('days', 30);
        $to = Carbon::now();
        $from = (clone $to)->subDays($days);

        $summary = $this->aggregation->vendorSummary($sellerId, $from, $to);
        $conversationsPerDay = $this->aggregation->conversationsPerDay($sellerId, $from, $to);
        $handlingSplit = $this->aggregation->handlingModeSplit($sellerId, $from, $to);

        return view('aiassistant::vendor.dashboard.index', compact('summary', 'conversationsPerDay', 'handlingSplit', 'days'));
    }
}
