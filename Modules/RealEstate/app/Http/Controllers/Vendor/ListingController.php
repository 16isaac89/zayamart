<?php

namespace Modules\RealEstate\app\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Traits\FileManagerTrait;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\RealEstate\app\Models\RealEstateBroker;
use Modules\RealEstate\app\Models\RealEstateListing;

class ListingController extends Controller
{
    use FileManagerTrait;

    private const IMAGE_DIR = 'real-estate/';

    public function index(): View
    {
        $listings = RealEstateListing::where('seller_id', auth('seller')->id())
            ->latest()
            ->paginate(20);

        return view('realestate::vendor.listing.index', compact('listings'));
    }

    public function create(): View|RedirectResponse
    {
        $broker = $this->brokerOrNull();
        if (!$broker) {
            return redirect()->route('vendor.real-estate.edit')
                ->with('error', translate('Set_up_your_broker_profile_before_adding_a_listing'));
        }

        $listing = new RealEstateListing();

        return view('realestate::vendor.listing.create', compact('listing'));
    }

    public function store(Request $request): RedirectResponse
    {
        $broker = $this->brokerOrNull();
        if (!$broker) {
            return redirect()->route('vendor.real-estate.edit')
                ->with('error', translate('Set_up_your_broker_profile_before_adding_a_listing'));
        }

        $data = $this->validated($request);
        $data['broker_id'] = $broker->id;
        $data['seller_id'] = auth('seller')->id();
        $data['slug'] = $this->uniqueSlug($data['title']);
        $data['images'] = $this->storeUploadedImages($request);
        $data['status'] = RealEstateListing::STATUS_PENDING;

        RealEstateListing::create($data);

        return redirect()->route('vendor.real-estate.listings.index')
            ->with('success', translate('Listing_submitted_for_review'));
    }

    public function edit(RealEstateListing $listing): View
    {
        $this->authorizeOwnership($listing);

        return view('realestate::vendor.listing.edit', compact('listing'));
    }

    public function update(Request $request, RealEstateListing $listing): RedirectResponse
    {
        $this->authorizeOwnership($listing);

        $data = $this->validated($request);
        if ($data['title'] !== $listing->title) {
            $data['slug'] = $this->uniqueSlug($data['title'], $listing->id);
        }

        $newImages = $this->storeUploadedImages($request);
        if (!empty($newImages)) {
            $data['images'] = array_merge($listing->images ?? [], $newImages);
        }

        // Any change to a moderated field goes back to the review queue —
        // simpler and safer than trying to diff which specific fields
        // count as "moderated", and matches how a product edit already
        // triggers re-approval.
        $data['status'] = RealEstateListing::STATUS_PENDING;
        $data['denied_note'] = null;

        $listing->update($data);

        return redirect()->route('vendor.real-estate.listings.index')
            ->with('success', translate('Listing_updated_and_resubmitted_for_review'));
    }

    public function destroy(RealEstateListing $listing): RedirectResponse
    {
        $this->authorizeOwnership($listing);
        $listing->delete();

        return back()->with('success', translate('Listing_removed'));
    }

    public function markSold(RealEstateListing $listing): RedirectResponse
    {
        $this->authorizeOwnership($listing);
        $listing->update(['status' => RealEstateListing::STATUS_SOLD]);

        return back()->with('success', translate('Listing_marked_as_sold'));
    }

    public function markRented(RealEstateListing $listing): RedirectResponse
    {
        $this->authorizeOwnership($listing);
        $listing->update(['status' => RealEstateListing::STATUS_RENTED]);

        return back()->with('success', translate('Listing_marked_as_rented'));
    }

    private function authorizeOwnership(RealEstateListing $listing): void
    {
        abort_unless($listing->seller_id === auth('seller')->id(), 403);
    }

    private function brokerOrNull(): ?RealEstateBroker
    {
        return RealEstateBroker::where('seller_id', auth('seller')->id())->first();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'listing_type' => ['required', 'in:house,land'],
            'purpose' => ['required', 'in:sale,rent'],
            'title' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'min:0'],
            'price_period' => ['nullable', 'required_if:purpose,rent', 'in:one_time,monthly,yearly'],
            'address' => ['nullable', 'string', 'max:191'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'area_size' => ['nullable', 'numeric', 'min:0'],
            'area_unit' => ['nullable', 'in:sqft,sqm,acre,hectare'],
            'bedrooms' => ['nullable', 'integer', 'min:0'],
            'bathrooms' => ['nullable', 'integer', 'min:0'],
            'floors' => ['nullable', 'integer', 'min:0'],
            'year_built' => ['nullable', 'integer', 'min:1800', 'max:' . (date('Y') + 1)],
            'parking_spaces' => ['nullable', 'integer', 'min:0'],
            'furnished' => ['nullable', 'boolean'],
            'amenities' => ['nullable', 'array'],
            'images.*' => ['nullable', 'image', 'max:4096'],
        ]);
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $suffix = 1;

        while (
            RealEstateListing::where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }

    /**
     * @return array<int, array{path: string, storage_type: string}>
     */
    private function storeUploadedImages(Request $request): array
    {
        if (!$request->hasFile('images')) {
            return [];
        }

        $storageType = config('filesystems.disks.default') ?? 'public';

        return collect($request->file('images'))
            ->filter()
            ->map(fn ($file) => [
                'path' => $this->upload(dir: self::IMAGE_DIR, format: 'webp', image: $file),
                'storage_type' => $storageType,
            ])
            ->values()
            ->all();
    }
}
