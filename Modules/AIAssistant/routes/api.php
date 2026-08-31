<?php

use Illuminate\Support\Facades\Route;
use Modules\AIAssistant\app\Http\Controllers\ConversationController;

/*
|--------------------------------------------------------------------------
| Mobile app / third-party channel
|--------------------------------------------------------------------------
|
| Stateless — no session middleware in this project's 'api' group (see
| architecture doc Part II §10). 'apiGuestCheck' is the same middleware
| RestAPI\v1\OrderController's own COD checkout route group uses
| (routes/rest_api/v1/api.php, 'customer/order' group) — it resolves an
| authenticated customer via token when present and otherwise allows a
| guest_id supplied in the request body, without hard-rejecting either.
|
*/
Route::prefix('ai-assistant')->name('ai-assistant.api.')->middleware('apiGuestCheck')->group(function () {
    Route::post('{shop_slug}/chat', [ConversationController::class, 'send'])->name('chat');
    Route::get('{shop_slug}/chat/{conversationId}/messages', [ConversationController::class, 'messages'])->name('messages');
    Route::post('{shop_slug}/chat/{conversationId}/request-human', [ConversationController::class, 'requestHuman'])->name('request-human');
    Route::post('{shop_slug}/chat/{conversationId}/resume-ai', [ConversationController::class, 'resumeAi'])->name('resume-ai');
});
