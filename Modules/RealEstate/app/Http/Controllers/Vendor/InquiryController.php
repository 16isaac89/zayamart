<?php

namespace Modules\RealEstate\app\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\RealEstate\app\Models\RealEstateInquiry;

class InquiryController extends Controller
{
    public function index(): View
    {
        $inquiries = RealEstateInquiry::where('seller_id', auth('seller')->id())
            ->with('listing')
            ->latest()
            ->paginate(20);

        return view('realestate::vendor.inquiry.index', compact('inquiries'));
    }

    public function show(RealEstateInquiry $inquiry): View
    {
        $this->authorizeOwnership($inquiry);

        return view('realestate::vendor.inquiry.show', compact('inquiry'));
    }

    public function updateStatus(Request $request, RealEstateInquiry $inquiry): RedirectResponse
    {
        $this->authorizeOwnership($inquiry);

        $request->validate(['status' => ['required', 'in:new,contacted,closed']]);
        $inquiry->update(['status' => $request->input('status')]);

        return back()->with('success', translate('Inquiry_status_updated'));
    }

    private function authorizeOwnership(RealEstateInquiry $inquiry): void
    {
        abort_unless($inquiry->seller_id === auth('seller')->id(), 403);
    }
}
