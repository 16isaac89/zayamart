<?php

use Illuminate\Support\Facades\Route;
use Modules\AIAssistant\app\Http\Controllers\Admin\AIDashboardController;
use Modules\AIAssistant\app\Http\Controllers\Admin\AIProviderSettingsController;

Route::group([
    'prefix' => 'admin/ai-assistant',
    'as' => 'admin.ai-assistant.',
    'middleware' => ['maintenance_mode', 'admin', 'module:ai_assistant_management'],
], function () {
    Route::get('dashboard', [AIDashboardController::class, 'index'])->name('dashboard');

    Route::group(['prefix' => 'providers', 'as' => 'providers.'], function () {
        Route::get('/', [AIProviderSettingsController::class, 'index'])->name('index');
        Route::post('{provider}', [AIProviderSettingsController::class, 'updateProvider'])->name('update');
        Route::post('models', [AIProviderSettingsController::class, 'storeModel'])->name('models.store');
        Route::post('default', [AIProviderSettingsController::class, 'setPlatformDefault'])->name('default');
        Route::post('{provider}/vendor-availability', [AIProviderSettingsController::class, 'updateVendorAvailability'])->name('vendor-availability');
    });
});
