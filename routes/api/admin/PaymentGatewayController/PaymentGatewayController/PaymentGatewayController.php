<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\PaymentGatewayController\PaymentGatewayController;


Route::get('/payment-gateways', [PaymentGatewayController::class, 'index']);
Route::get('/payment-gateways/{id}', [PaymentGatewayController::class, 'show']);
Route::put('/payment-gateways/{id}', [PaymentGatewayController::class, 'update']);
