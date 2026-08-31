<?php

namespace Modules\RealEstate\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Utils\Helpers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\RealEstate\app\Models\RealEstateInquiry;
use Modules\RealEstate\app\Models\RealEstateListing;
use Modules\RealEstate\app\Services\RealEstateInquiryNotifier;
use Modules\RealEstate\app\Services\RealEstateWhatsAppLinkService;

class ListingController extends Controller
{
    public function index(Request $request): View
    {
        $listings = RealEstateListing::publiclyVisible()
            ->when($request->filled('listing_type'), fn ($q) => $q->where('listing_type', $request->input('listing_type')))
            ->when($request->filled('purpose'), fn ($q) => $q->where('purpose', $request->input('purpose')))
            ->when($request->filled('city'), fn ($q) => $q->where('city', 'like', '%' . $request->input('city') . '%'))
            ->when($request->filled('bedrooms'), fn ($q) => $q->where('bedrooms', '>=', (int)$request->input('bedrooms')))
            ->when($request->filled('min_price'), fn ($q) => $q->where('price', '>=', (float)$request->input('min_price')))
            ->when($request->filled('max_price'), fn ($q) => $q->where('price', '<=', (float)$request->input('max_price')))
            ->when($request->filled('keyword'), function ($q) use ($request) {
                $keyword = $request->input('keyword');
                $q->where(function ($q2) use ($keyword) {
                    $q2->where('title', 'like', "%{$keyword}%")->orWhere('description', 'like', "%{$keyword}%");
                });
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('real-estate.index', compact('listings'));
    }

    public function show(string $slug, RealEstateWhatsAppLinkService $whatsAppLinkService): View
    {
        $listing = RealEstateListing::publiclyVisible()->where('slug', $slug)->firstOrFail();
        $listing->increment('views_count');

        $otherListings = RealEstateListing::publiclyVisible()
            ->where('broker_id', $listing->broker_id)
            ->where('id', '!=', $listing->id)
            ->limit(4)
            ->get();

        $whatsappLink = $whatsAppLinkService->inquiryLink($listing, route('real-estate.show', $listing->slug));

        return view('real-estate.show', compact('listing', 'otherListings', 'whatsappLink'));
    }

    public function storeInquiry(Request $request, string $slug, RealEstateInquiryNotifier $notifier): RedirectResponse
    {
        $listing = RealEstateListing::publiclyVisible()->where('slug', $slug)->firstOrFail();

        $data = $request->validate([
            'guest_name' => ['required', 'string', 'max:191'],
            'guest_phone' => ['required', 'string', 'max:30'],
            'guest_email' => ['nullable', 'email', 'max:191'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $user = Helpers::getCustomerInformation($request);
        $customerId = $user !== 'offline' ? (int)$user->id : null;

        $inquiry = RealEstateInquiry::create($data + [
            'listing_id' => $listing->id,
            'seller_id' => $listing->seller_id,
            'customer_id' => $customerId,
        ]);

        $notifier->notify(collect([$inquiry->setRelation('listing', $listing)]));

        return back()->with('success', translate('Your_inquiry_has_been_sent'));
    }
}
