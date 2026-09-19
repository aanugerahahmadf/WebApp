<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\PackageController\PackageController;


Route::get('/packages/public', [PackageController::class, 'index']);
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/packages', [PackageController::class, 'index']);
    Route::get('/packages/featured', [PackageController::class, 'featured']);
    Route::get('/packages/on-sale', [PackageController::class, 'onSale']);
    Route::get('/packages/{id}', [PackageController::class, 'show']);
});
