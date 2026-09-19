<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\CartController\CartController;


Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart/add', [CartController::class, 'store']);
    Route::put('/cart/{cart}', [CartController::class, 'update']);
    Route::delete('/cart/{cart}', [CartController::class, 'destroy']);
});
