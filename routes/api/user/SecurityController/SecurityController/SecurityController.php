<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\SecurityController\SecurityController;


Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/security/checkup', [SecurityController::class, 'checkup']);
    Route::get('/security/recent-emails', [SecurityController::class, 'recentEmails']);
    Route::get('/security/two-factor/status', [SecurityController::class, 'twoFactorStatus']);
    Route::post('/security/two-factor/toggle', [SecurityController::class, 'twoFactorToggle']);
    Route::post('/security/two-factor/backup-codes', [SecurityController::class, 'generateBackupCodes']);
    Route::get('/security/trusted-devices', [SecurityController::class, 'trustedDevices']);
    Route::delete('/security/trusted-devices/{deviceId}', [SecurityController::class, 'removeTrustedDevice']);
    Route::get('/security/saved-login', [SecurityController::class, 'savedLoginStatus']);
    Route::post('/security/saved-login/toggle', [SecurityController::class, 'savedLoginToggle']);
});
