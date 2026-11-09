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
    Route::put('/profile/ktp', [ProfileController::class, 'updateDocument']);
    Route::post('/profile/ktp-photo', [ProfileController::class, 'uploadDocument']);
    // The legacy KTP route names are also the document API. Keep them for
    // compatibility, while exposing explicit routes for every supported type.
    foreach (['sim', 'npwp', 'passport'] as $documentType) {
        Route::put('/profile/'.$documentType, [ProfileController::class, 'updateDocument'])
            ->defaults('documentType', $documentType);
        Route::post('/profile/'.$documentType.'-photo', [ProfileController::class, 'uploadDocument'])
            ->defaults('documentType', $documentType);
    }
    Route::post('/profile/selfie', [ProfileController::class, 'uploadSelfie']);
    Route::post('/profile/face-scan', [ProfileController::class, 'uploadFaceScan']);
    Route::get('/profile/completion', [ProfileController::class, 'completion']);
    Route::get('/profile/Favorite', [ProfileController::class, 'getFavorite']);
});
