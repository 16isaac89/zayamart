<?php

use Illuminate\Support\Facades\Route;
use Modules\RealEstate\app\Http\Controllers\Vendor\BrokerProfileController;
use Modules\RealEstate\app\Http\Controllers\Vendor\InquiryController;
use Modules\RealEstate\app\Http\Controllers\Vendor\ListingController;

Route::group([
    'prefix' => 'vendor/real-estate',
    'as' => 'vendor.real-estate.',
    'middleware' => ['maintenance_mode', 'seller'],
], function () {
    Route::get('/', [BrokerProfileController::class, 'edit'])->name('edit');
    Route::post('/', [BrokerProfileController::class, 'update'])->name('update');

    Route::group(['prefix' => 'listings', 'as' => 'listings.'], function () {
        Route::get('/', [ListingController::class, 'index'])->name('index');
        Route::get('create', [ListingController::class, 'create'])->name('create');
        Route::post('/', [ListingController::class, 'store'])->name('store');
        Route::get('{listing}/edit', [ListingController::class, 'edit'])->name('edit');
        Route::post('{listing}', [ListingController::class, 'update'])->name('update');
        Route::delete('{listing}', [ListingController::class, 'destroy'])->name('destroy');
        Route::post('{listing}/mark-sold', [ListingController::class, 'markSold'])->name('mark-sold');
        Route::post('{listing}/mark-rented', [ListingController::class, 'markRented'])->name('mark-rented');
    });

    Route::group(['prefix' => 'inquiries', 'as' => 'inquiries.'], function () {
        Route::get('/', [InquiryController::class, 'index'])->name('index');
        Route::get('{inquiry}', [InquiryController::class, 'show'])->name('show');
        Route::post('{inquiry}/status', [InquiryController::class, 'updateStatus'])->name('status');
    });
});
