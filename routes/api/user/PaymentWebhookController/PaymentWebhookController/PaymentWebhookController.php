<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\PaymentWebhookController\PaymentWebhookController;


Route::post('/midtrans/notification', [PaymentWebhookController::class, 'notification']);
