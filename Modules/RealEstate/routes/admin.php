<?php

use Illuminate\Support\Facades\Route;
use Modules\RealEstate\app\Http\Controllers\Admin\ListingModerationController;

Route::group([
    'prefix' => 'admin/real-estate',
    'as' => 'admin.real-estate.',
    'middleware' => ['maintenance_mode', 'admin', 'module:real_estate_management'],
], function () {
    Route::get('listings', [ListingModerationController::class, 'index'])->name('listings.index');
    Route::post('listings/{listing}/approve', [ListingModerationController::class, 'approve'])->name('listings.approve');
    Route::post('listings/{listing}/deny', [ListingModerationController::class, 'deny'])->name('listings.deny');
});
