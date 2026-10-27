<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\ChatController\ChatController;
use App\Http\Controllers\ChatCall\ChatCallController;


Route::middleware('throttle:guest-chat')->group(function (): void {
    Route::post('/messages/guest/start', [ChatController::class, 'guestStart']);
    Route::post('/messages/guest/send', [ChatController::class, 'guestSend']);
    Route::get('/messages/guest/{inboxId}', [ChatController::class, 'guestMessages']);
});
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
    Route::post('/messages/calls', [ChatCallController::class, 'start']);
    Route::get('/messages/{inboxId}/calls/active', [ChatCallController::class, 'active']);
    Route::get('/messages/calls/{token}', [ChatCallController::class, 'state']);
    Route::post('/messages/calls/{token}/signal', [ChatCallController::class, 'signal']);
    Route::post('/messages/calls/{token}/accept', [ChatCallController::class, 'accept']);
    Route::post('/messages/calls/{token}/end', [ChatCallController::class, 'end']);
});

Route::middleware('throttle:guest-chat')->group(function (): void {
    Route::post('/messages/guest/calls', [ChatCallController::class, 'start']);
    Route::get('/messages/guest/{inboxId}/calls/active', [ChatCallController::class, 'active']);
    Route::get('/messages/guest/calls/{token}', [ChatCallController::class, 'state']);
    Route::post('/messages/guest/calls/{token}/signal', [ChatCallController::class, 'signal']);
    Route::post('/messages/guest/calls/{token}/accept', [ChatCallController::class, 'accept']);
    Route::post('/messages/guest/calls/{token}/end', [ChatCallController::class, 'end']);
});
