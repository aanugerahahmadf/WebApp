<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\BriVaWebhookController\BriVaWebhookController;


Route::post('/webhooks/bri/va', [BriVaWebhookController::class, 'notification']);
Route::post('/webhooks/bri/qris', [BriVaWebhookController::class, 'qrisNotification']);
