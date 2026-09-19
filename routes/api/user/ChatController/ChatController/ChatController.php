<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\ChatController\ChatController;


Route::post('/messages/guest/start', [ChatController::class, 'guestStart']);
Route::post('/messages/guest/send', [ChatController::class, 'guestSend']);
Route::get('/messages/guest/{inboxId}', [ChatController::class, 'guestMessages']);
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/messages/conversations', [ChatController::class, 'getConversations']);
    Route::get('/messages/conversations/{inboxId}', [ChatController::class, 'getMessages']);
    Route::get('/messages/unread-count', [ChatController::class, 'getUnreadCount']);
    Route::get('/messages/customers', [ChatController::class, 'getCustomersForChat']);
    Route::post('/messages/send', [ChatController::class, 'sendMessage']);
    Route::post('/messages/start', [ChatController::class, 'startConversation']);
    Route::delete('/messages/{id}/delete', [ChatController::class, 'deleteMessage']);
    Route::post('/messages/{id}/star', [ChatController::class, 'starMessage']);
    Route::post('/messages/{id}/forward', [ChatController::class, 'forwardMessage']);
    Route::post('/messages/{id}/react', [ChatController::class, 'addReaction']);
    Route::post('/messages/{inboxId}/read', [ChatController::class, 'markInboxAsRead']);
    Route::post('/messages/{inboxId}/rate', [ChatController::class, 'rateInbox']);
});
