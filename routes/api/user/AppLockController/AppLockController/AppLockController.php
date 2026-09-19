<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\AppLockController\AppLockController;


Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/profile/app-lock', [AppLockController::class, 'show']);
    Route::put('/profile/app-lock', [AppLockController::class, 'update']);
    Route::post('/profile/app-lock/pin', [AppLockController::class, 'setPin']);
    Route::post('/profile/app-lock/pin/verify', [AppLockController::class, 'verifyPin']);
    Route::post('/profile/app-lock/face-enroll', [AppLockController::class, 'faceEnroll']);
    Route::post('/profile/app-lock/face-verify', [AppLockController::class, 'faceVerify']);
});
