<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\ProfileController\ProfileController;


Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar']);
    Route::post('/profile/change-password', [ProfileController::class, 'changePassword']);
    Route::put('/profile/change-password', [ProfileController::class, 'changePassword']);
    Route::get('/profile/dashboard', [ProfileController::class, 'dashboard']);
    Route::get('/profile/order-history', [ProfileController::class, 'getOrderHistory']);
    Route::put('/profile/ktp', [ProfileController::class, 'updateKtp']);
    Route::post('/profile/ktp-photo', [ProfileController::class, 'uploadKtp']);
    Route::post('/profile/selfie', [ProfileController::class, 'uploadSelfie']);
    Route::post('/profile/face-scan', [ProfileController::class, 'uploadFaceScan']);
    Route::get('/profile/completion', [ProfileController::class, 'completion']);
    Route::get('/profile/wishlist', [ProfileController::class, 'getWishlist']);
});
