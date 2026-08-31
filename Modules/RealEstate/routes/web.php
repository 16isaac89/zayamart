<?php

use Illuminate\Support\Facades\Route;
use Modules\RealEstate\app\Http\Controllers\ListingController;

/*
|--------------------------------------------------------------------------
| Public listing search/detail pages
|--------------------------------------------------------------------------
|
| 'guestCheck' assigns a guest_id session key for anonymous visitors, same
| as the rest of the customer-facing storefront — an inquiry can be sent
| without an account.
|
*/
Route::group([
    'prefix' => 'real-estate',
    'as' => 'real-estate.',
    'middleware' => ['maintenance_mode', 'guestCheck'],
], function () {
    Route::get('/', [ListingController::class, 'index'])->name('index');
    Route::get('{slug}', [ListingController::class, 'show'])->name('show');
    Route::post('{slug}/inquiry', [ListingController::class, 'storeInquiry'])->name('inquiry');
});
