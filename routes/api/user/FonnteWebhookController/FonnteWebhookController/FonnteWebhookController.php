<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\FonnteWebhookController\FonnteWebhookController;


Route::post('/webhooks/fonnte', [FonnteWebhookController::class, 'handleIncomingMessage']);
Route::post('/webhooks/fonnte/connect', [FonnteWebhookController::class, 'handleConnectionStatus']);
Route::post('/webhooks/fonnte/status', [FonnteWebhookController::class, 'handleMessageStatus']);
