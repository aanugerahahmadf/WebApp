<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\VendorController\VendorController;


Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/vendors', [VendorController::class, 'index']);
    Route::get('/vendors/{id}', [VendorController::class, 'show']);
});
