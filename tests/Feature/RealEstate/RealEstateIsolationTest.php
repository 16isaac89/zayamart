<?php

namespace Tests\Feature\RealEstate;

use App\Models\Seller;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\RealEstate\app\Models\RealEstateBroker;
use Modules\RealEstate\app\Models\RealEstateInquiry;
use Modules\RealEstate\app\Models\RealEstateListing;
use Tests\TestCase;

/**
 * Mirrors tests/Feature/AIAssistant/KnowledgeIsolationTest.php's pattern: a
 * manipulated listing_id/inquiry_id in a vendor-panel URL must never widen
 * access past the authenticated seller, since broker_id/listing_id/
 * inquiry_id are trusted route parameters, not scoped by anything else.
 */
class RealEstateIsolationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_seller_cannot_edit_another_sellers_listing(): void
    {
        $sellerA = Seller::create(['f_name' => 'A', 'l_name' => 'V', 'phone' => '101', 'email' => 're-a@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);
        $sellerB = Seller::create(['f_name' => 'B', 'l_name' => 'V', 'phone' => '102', 'email' => 're-b@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);

        $brokerB = RealEstateBroker::create(['seller_id' => $sellerB->id, 'status' => 'active']);
        $listing = RealEstateListing::create([
            'broker_id' => $brokerB->id,
            'seller_id' => $sellerB->id,
            'listing_type' => 'house',
            'purpose' => 'sale',
            'title' => 'Seller B house',
            'slug' => 'seller-b-house',
            'price' => 100000,
            'status' => 'pending',
        ]);

        $this->actingAs($sellerA, 'seller');

        $this->get(route('vendor.real-estate.listings.edit', $listing->id))->assertForbidden();
    }

    public function test_a_seller_cannot_view_another_sellers_inquiry(): void
    {
        $sellerA = Seller::create(['f_name' => 'C', 'l_name' => 'V', 'phone' => '103', 'email' => 're-c@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);
        $sellerB = Seller::create(['f_name' => 'D', 'l_name' => 'V', 'phone' => '104', 'email' => 're-d@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);

        $brokerB = RealEstateBroker::create(['seller_id' => $sellerB->id, 'status' => 'active']);
        $listing = RealEstateListing::create([
            'broker_id' => $brokerB->id,
            'seller_id' => $sellerB->id,
            'listing_type' => 'land',
            'purpose' => 'rent',
            'title' => 'Seller B land',
            'slug' => 'seller-b-land',
            'price' => 500,
            'status' => 'approved',
        ]);
        $inquiry = RealEstateInquiry::create([
            'listing_id' => $listing->id,
            'seller_id' => $sellerB->id,
            'guest_name' => 'Guest',
            'guest_phone' => '000',
            'message' => 'Interested',
        ]);

        $this->actingAs($sellerA, 'seller');

        $this->get(route('vendor.real-estate.inquiries.show', $inquiry->id))->assertForbidden();
    }

    public function test_pending_listing_is_never_publicly_visible(): void
    {
        $seller = Seller::create(['f_name' => 'E', 'l_name' => 'V', 'phone' => '105', 'email' => 're-e@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);
        $broker = RealEstateBroker::create(['seller_id' => $seller->id, 'status' => 'active']);
        $listing = RealEstateListing::create([
            'broker_id' => $broker->id,
            'seller_id' => $seller->id,
            'listing_type' => 'house',
            'purpose' => 'sale',
            'title' => 'Pending house',
            'slug' => 'pending-house',
            'price' => 100000,
            'status' => 'pending',
        ]);

        $this->get(route('real-estate.show', $listing->slug))->assertNotFound();
    }
}
