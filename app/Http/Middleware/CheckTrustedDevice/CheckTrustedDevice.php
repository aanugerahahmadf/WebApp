<?php

namespace App\Http\Middleware\CheckTrustedDevice;

use App\Filament\User\Auth\OtpEmailOrTwoFactory\OtpEmailOrTwoFactory;
use Illuminate\Http\Request;
use MixCode\FilamentMulti2fa\Middleware\CheckTrustedDevice as BaseCheckTrustedDevice;

/**
 * CheckTrustedDevice milik paket selalu mengarah ke OTPVerify bawaan paket
 * (slug o-t-p-verify). Aplikasi memakai halaman sendiri dengan slug
 * otp-verify, jadi redirect cabang "belum lewat OTP" diarahkan ke route
 * halaman itu -- bukan ke route paket yang tidak terdaftar.
 *
 * Semua perilaku lain (perangkat tepercaya, cabang TwoFactorySetup, dan
 * pengecekan session 2fa_passed) diwarisi apa adanya.
 */
class CheckTrustedDevice extends BaseCheckTrustedDevice
{
    protected function handleOtpRedirect(Request $request, $next)
    {
        $routeName = OtpEmailOrTwoFactory::getRouteName();

        if (! $request->routeIs($routeName)) {
            return redirect()->route($routeName);
        }

        return $next($request);
    }
}
