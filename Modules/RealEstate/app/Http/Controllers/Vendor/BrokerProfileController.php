<?php

namespace Modules\RealEstate\app\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\RealEstate\app\Models\RealEstateBroker;

/**
 * A vendor "becomes a broker" simply by saving this form once — see
 * RealEstateBroker's docblock. No separate enable/disable toggle.
 */
class BrokerProfileController extends Controller
{
    public function edit(): View
    {
        $broker = RealEstateBroker::firstOrNew(['seller_id' => auth('seller')->id()]);

        return view('realestate::vendor.broker.edit', compact('broker'));
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'agency_name' => ['nullable', 'string', 'max:191'],
            'license_number' => ['nullable', 'string', 'max:191'],
            'bio' => ['nullable', 'string', 'max:2000'],
        ]);

        RealEstateBroker::updateOrCreate(
            ['seller_id' => auth('seller')->id()],
            $request->only(['agency_name', 'license_number', 'bio']) + ['status' => 'active'],
        );

        return back()->with('success', translate('Broker_profile_updated'));
    }
}
