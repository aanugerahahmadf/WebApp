<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\CategoryController\CategoryController;


Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{id}', [CategoryController::class, 'show']);
    Route::get('/categories-with-packages', [CategoryController::class, 'withTopPackages']);
});
