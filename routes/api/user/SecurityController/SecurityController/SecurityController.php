<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\SecurityController\SecurityController;


Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/security/checkup', [SecurityController::class, 'checkup']);
    Route::get('/security/recent-emails', [SecurityController::class, 'recentEmails']);
    Route::get('/security/two-factor/status', [SecurityController::class, 'twoFactorStatus']);
    Route::post('/security/two-factor/toggle', [SecurityController::class, 'twoFactorToggle']);
    Route::post('/security/two-factor/backup-codes', [SecurityController::class, 'generateBackupCodes']);
    // Setup 2FA -- padanan TwoFactorySetup (Pengaturan) dan Auth\TwoFactorAuth
    // (tengah alur auth). Keduanya memakai endpoint yang sama persis; yang
    // membedakan hanya tujuan akhir navigasinya di sisi klien.
    Route::post('/security/two-factor/setup', [SecurityController::class, 'setupTwoFactor']);
    Route::post('/security/two-factor/setup/verify', [SecurityController::class, 'verifyTwoFactorSetup']);
    Route::post('/security/two-factor/setup/resend', [SecurityController::class, 'resendTwoFactorSetupOtp']);
    Route::get('/security/trusted-devices', [SecurityController::class, 'trustedDevices']);
    Route::delete('/security/trusted-devices/{deviceId}', [SecurityController::class, 'removeTrustedDevice']);
    Route::delete('/security/trusted-devices', [SecurityController::class, 'removeAllTrustedDevices']);
    Route::post('/security/sessions/remove-all', [SecurityController::class, 'removeAllOtherSessions']);
    Route::post('/security/change-email/send-otp', [SecurityController::class, 'sendEmailChangeOtp']);
    Route::post('/security/change-email/verify-otp', [SecurityController::class, 'verifyEmailChangeOtp']);
    Route::get('/security/saved-login', [SecurityController::class, 'savedLoginStatus']);
    Route::post('/security/saved-login/toggle', [SecurityController::class, 'savedLoginToggle']);
});
