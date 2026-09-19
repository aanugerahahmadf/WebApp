<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\PaymentMethodController\PaymentMethodController;


Route::get('/payment-methods', [PaymentMethodController::class, 'index']);
Route::get('/payment-methods/{id}', [PaymentMethodController::class, 'show']);
Route::post('/payment-methods', [PaymentMethodController::class, 'store']);
Route::put('/payment-methods/{id}', [PaymentMethodController::class, 'update']);
Route::delete('/payment-methods/{id}', [PaymentMethodController::class, 'destroy']);
