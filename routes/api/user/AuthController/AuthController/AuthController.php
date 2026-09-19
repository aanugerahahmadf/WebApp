<?php

use App\Http\Controllers\Api\User\AuthController\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/auth/send-otp', [AuthController::class, 'sendOtp']);
Route::post('/auth/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/auth/clerk-sync', [AuthController::class, 'clerkSync']);
Route::post('/auth/google', [AuthController::class, 'googleLogin']);
Route::post('/auth/facebook', [AuthController::class, 'facebookLogin']);
Route::post('/auth/apple', [AuthController::class, 'appleLogin']);
Route::get('/verify-email', [AuthController::class, 'verifyEmail']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::delete('/user/account', [AuthController::class, 'deleteAccount']);
    Route::post('/email/verification/send', [AuthController::class, 'sendVerificationEmail']);
    Route::get('/user', function (Request $request) {
        $user = $request->user();

        return response()->json([
            'status' => 'success',
            'data' => array_merge($user->toArray(), [
                'needs_completion' => ! $user->identity_type || ! $user->whatsapp || ! $user->birth_date,
            ]),
        ]);
    });
    Route::post('/profile', [AuthController::class, 'updateProfile']);
});