<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\MessageController\MessageController;


Route::get('/messages/inboxes', [MessageController::class, 'inboxes']);
Route::get('/messages/inboxes/{id}', [MessageController::class, 'showInbox']);
Route::post('/messages/send', [MessageController::class, 'sendMessage']);
Route::delete('/messages/inboxes/{id}', [MessageController::class, 'destroyInbox']);
Route::delete('/messages/{id}', [MessageController::class, 'destroyMessage']);
