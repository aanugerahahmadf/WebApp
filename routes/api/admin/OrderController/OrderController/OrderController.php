<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\OrderController\OrderController;


Route::get('/orders', [OrderController::class, 'index']);
Route::get('/orders/{id}', [OrderController::class, 'show']);
Route::get('/orders/statuses/list', [OrderController::class, 'statuses']);
Route::put('/orders/{id}/status', [OrderController::class, 'updateStatus']);
