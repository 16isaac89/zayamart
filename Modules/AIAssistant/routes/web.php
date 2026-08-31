<?php

use Illuminate\Support\Facades\Route;
use Modules\AIAssistant\app\Http\Controllers\ConversationController;

/*
|--------------------------------------------------------------------------
| Storefront chat widget
|--------------------------------------------------------------------------
|
| 'guestCheck' assigns a guest_id session key for anonymous shoppers,
| exactly like the rest of the customer-facing checkout flow (see
| routes/web/routes.php's own use of the same middleware). No 'customer'
| guard requirement — guests can shop and check out through the assistant,
| per the brief's guest-checkout requirement.
|
*/
Route::group(['prefix' => 'ai-assistant', 'as' => 'ai-assistant.', 'middleware' => ['maintenance_mode', 'guestCheck']], function () {
    Route::post('{shop_slug}/chat', [ConversationController::class, 'send'])->name('chat');
    Route::get('{shop_slug}/chat/{conversationId}/messages', [ConversationController::class, 'messages'])->name('messages');
    Route::post('{shop_slug}/chat/{conversationId}/request-human', [ConversationController::class, 'requestHuman'])->name('request-human');
    Route::post('{shop_slug}/chat/{conversationId}/resume-ai', [ConversationController::class, 'resumeAi'])->name('resume-ai');
});
