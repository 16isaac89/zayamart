<?php

namespace Modules\RealEstate\app\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\RealEstate\app\Models\RealEstateListing;

class ListingModerationController extends Controller
{
    public function index(Request $request): View
    {
        $listings = RealEstateListing::with('broker.seller')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->latest()
            ->paginate(20);

        return view('realestate::admin.listing.index', compact('listings'));
    }

    public function approve(RealEstateListing $listing): RedirectResponse
    {
        $listing->update(['status' => RealEstateListing::STATUS_APPROVED, 'denied_note' => null]);

        return back()->with('success', translate('Listing_approved'));
    }

    public function deny(Request $request, RealEstateListing $listing): RedirectResponse
    {
        $request->validate(['denied_note' => ['required', 'string', 'max:255']]);

        $listing->update(['status' => RealEstateListing::STATUS_DENIED, 'denied_note' => $request->input('denied_note')]);

        return back()->with('success', translate('Listing_denied'));
    }
}
