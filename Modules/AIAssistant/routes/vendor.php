<?php

use App\Http\Controllers\Vendor\VendorWhatsAppController;
use Illuminate\Support\Facades\Route;
use Modules\AIAssistant\app\Http\Controllers\Vendor\AIAgentSettingsController;
use Modules\AIAssistant\app\Http\Controllers\Vendor\InboxController;
use Modules\AIAssistant\app\Http\Controllers\Vendor\KnowledgeBaseController;
use Modules\AIAssistant\app\Http\Controllers\Vendor\VendorAIDashboardController;
use Modules\AIAssistant\app\Http\Controllers\Vendor\VendorAIProviderController;

Route::group([
    'prefix' => 'vendor/ai-assistant',
    'as' => 'vendor.ai-assistant.',
    'middleware' => ['maintenance_mode', 'seller'],
], function () {
    Route::get('/', [AIAgentSettingsController::class, 'edit'])->name('edit');
    Route::post('/', [AIAgentSettingsController::class, 'update'])->name('update');

    Route::get('dashboard', [VendorAIDashboardController::class, 'index'])->name('dashboard');

    Route::group(['prefix' => 'provider', 'as' => 'provider.'], function () {
        Route::get('/', [VendorAIProviderController::class, 'edit'])->name('edit');
        Route::post('/', [VendorAIProviderController::class, 'update'])->name('update');
        Route::post('credentials', [VendorAIProviderController::class, 'storeCredentials'])->name('credentials');
        Route::post('test-connection', [VendorAIProviderController::class, 'testConnection'])->name('test-connection');
    });

    Route::group(['prefix' => 'knowledge', 'as' => 'knowledge.'], function () {
        Route::get('/', [KnowledgeBaseController::class, 'index'])->name('index');
        Route::post('/', [KnowledgeBaseController::class, 'store'])->name('store');
        Route::post('{document}/reindex', [KnowledgeBaseController::class, 'reindex'])->name('reindex');
        Route::delete('{document}', [KnowledgeBaseController::class, 'destroy'])->name('destroy');
    });

    Route::group(['prefix' => 'whatsapp', 'as' => 'whatsapp.'], function () {
        Route::get('/', [VendorWhatsAppController::class, 'edit'])->name('edit');
        Route::post('/', [VendorWhatsAppController::class, 'update'])->name('update');
        Route::post('test-connection', [VendorWhatsAppController::class, 'testConnection'])->name('test-connection');
    });

    Route::group(['prefix' => 'inbox', 'as' => 'inbox.'], function () {
        Route::get('/', [InboxController::class, 'index'])->name('index');
        Route::get('{conversationId}', [InboxController::class, 'show'])->name('show');
        Route::get('{conversationId}/poll', [InboxController::class, 'poll'])->name('poll');
        Route::post('{conversationId}/take-over', [InboxController::class, 'takeOver'])->name('take-over');
        Route::post('{conversationId}/return-to-ai', [InboxController::class, 'returnToAi'])->name('return-to-ai');
        Route::post('{conversationId}/reply', [InboxController::class, 'reply'])->name('reply');
    });
});
