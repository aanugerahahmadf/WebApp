<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\ProductController\ProductController;


Route::get('/products/public', [ProductController::class, 'index']);
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/featured', [ProductController::class, 'featured']);
    Route::get('/products/on-sale', [ProductController::class, 'onSale']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
});
