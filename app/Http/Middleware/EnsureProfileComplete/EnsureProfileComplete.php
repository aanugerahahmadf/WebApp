<?php

namespace App\Http\Middleware\EnsureProfileComplete;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Paksa akun social yang profilnya belum lengkap ke halaman Complete Profile.
 *
 * Jalannya di authMiddleware panel WELCOME (setelah panel user dilebur ke
 * sana), jadi dia berjalan juga untuk TAMU. Pemisahnya di baris pertama:
 * tamu tanpa sesi langsung lewat -- guest-ability storefront sudah diurus
 * AuthenticateWelcome, dan tamu tidak punya profil untuk diperiksa.
 *
 * DULU cabang tamu melempar `redirect()->route('filament.user.auth.login')`;
 * setelah panel user dihapus route itu tidak terdaftar lagi dan SELURUH
 * halaman panel welcome 500 dengan RouteNotFoundException sebelum halaman
 * manapun dirender. Isinya hybrid guard `web`, jadi yang benar bukan mengirim
 * tamu ke login, hanya meneruskan.
 *
 * Dulu juga ada cabang `if ($user->hasRole('super_admin')) return $next(...)`.
 * Cabang itu DIHAPUS, bukan karena tidak berlaku lagi, tapi karena sudah tidak
 * mungkin terjadi: role admin dipindah ke guard 'admin' bersama tabelnya
 * (lihat App\Models\Admin\Admin), jadi `User::hasRole('super_admin')` selalu
 * false. Membiarkannya menyesatkan -- terbaca seperti "admin punya
 * pengecualian", padahal tidak ada admin yang pernah menyentuh middleware ini.
 */
class EnsureProfileComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        // TAMU: lewat. Halaman publik milik AuthenticateWelcome; yang di sini
        // hanya profil orang yang sudah sign in.
        if (! Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        // Complete Profile hanya untuk tombol Google yang belum terdaftar
        // (akun social + profil belum lengkap). Akun form (Sign Up / Sign In)
        // datanya sudah sama seperti Complete Profile, langsung ke home.
        if (method_exists($user, 'shouldCompleteProfile') && $user->shouldCompleteProfile()) {
            // Email belum verifikasi -> biarkan flow OTP dulu, jangan paksa ke
            // complete-profile.
            if (method_exists($user, 'hasVerifiedEmail') && ! $user->hasVerifiedEmail()) {
                return $next($request);
            }

            // Nama route panel welcome (panel user sudah dilebur -- lihat
            // WelcomePanelProvider).
            if ($request->routeIs('filament.welcome.pages.complete-profile')) {
                return $next($request);
            }

            // Route auth tetap jalur yang wajib dikejar sampai selesai
            // (OTP forgot-password, verifikasi) selama profil masih belum
            // lengkap -- jangan memaksa keluar dari flow itu.
            if ($request->routeIs('filament.welcome.auth.*')) {
                return $next($request);
            }

            // Jalur lama bookmark (LEGACY USER, /user/*) + URL baru di root.
            // `user/complete-profile` akan diredirect web.php ke root, tapi
            // kalau sampai melewati tanpa redirect, izinkan juga.
            if ($request->is('complete-profile') || $request->is('user/complete-profile')) {
                return $next($request);
            }

            return redirect()->route('filament.welcome.pages.complete-profile');
        }

        return $next($request);
    }
}
