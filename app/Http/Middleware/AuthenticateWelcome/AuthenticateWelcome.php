<?php

namespace App\Http\Middleware\AuthenticateWelcome;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Auth gate for the 'welcome' panel, which doubles as the public storefront.
 *
 * Filament's own Authenticate middleware sends every unauthenticated visitor to
 * the panel's login page, and the welcome panel does not register one -- so
 * `/welcome` used to be unreachable for guests (AuthenticationException with no
 * redirect target). The storefront is meant to be browsable before signing in,
 * the way the reference site behaves, so this middleware keeps the gate but
 * punches a hole for the public paths below.
 *
 * Everything else (cart, orders, Favorite, messages, settings, profile, search,
 * logout) is still closed to guests, and a guest who lands on one of those is
 * redirected to the user panel's auth landing page (/auth), which does
 * have auth pages. The intended URL is kept in the session by Laravel's
 * exception handler, so the guest resumes where they were after signing in.
 *
 * Registered as a Livewire persistent middleware in AppServiceProvider, because
 * this class replaces Filament's Authenticate in the panel's authMiddleware --
 * without that, a Livewire update on a protected page would skip the gate.
 */
class AuthenticateWelcome extends Authenticate
{
    /**
     * Panel-relative paths a guest may open.
     *
     * The storefront and the catalog: the landing page (panel root + its
     * dashboard) and the catalog index / detail pages. Packages are no longer a
     * browsable page — they are tiers inside a catalog detail page — so they are
     * deliberately absent here. Everything account-shaped is absent too.
     *
     * Panel welcome berpindah ke path '/' (shopee-style), jadi path request
     * sekarang 'home', 'flowerdecorationscatalog', 'messages', dan '' untuk
     * '/'. Form lama ('welcome', 'welcome/home', ...) tetap valid untuk
     * bookmark /welcome/* yang berlapis redirect (redirect tidak lewat
     * middleware panel -- request-nya langsung ke URL root).
     *
     * @var list<string>
     */
    public const PUBLIC_PATHS = [
        '',
        'home',
        'welcome',
        'welcome/home',
        'flowerdecorationscatalog',
        'flowerdecorationscatalog/*',
        'inbox/chat',
        'inbox/chat/*',
        'chat',
        'chat/*',
        'messages',
        'messages/*',

        // PENGATURAN: halaman ini punya bagian yang boleh dipakai TAMU --
        // pemilih huruf tampilan (tamu pun boleh memilih hurufnya sendiri,
        // disimpan di session). Sisanya (notifikasi, keamanan, kunci
        // aplikasi, hapus akun) butuh akun, jadi di dalam view-nya
        // dibungkus @auth -- tamu tidak melihatnya, dan tidak ada kode yang
        // dijalankan untuk dia.
        'settings',

        // SHOPEE-STYLE: halaman auth sekarang HIDUP DI PANEL INI juga
        // (/signin, /signup, /auth, /password-reset/*, /otp-verify,
        // /email-verification/*). Tamu HARUS bisa membukanya -- sebaliknya
        // tamu yang menekan pintu auth akan di-redirect oleh middleware ini
        // ke LOGIN_ROUTE, yang adalah HALAMAN YANG SAMA -> langgeng.
        // Dulu halaman-halaman ini tinggal di panel user, sehingga tidak perlu
        // muncul di daftar sini (gate panel user memang mengizinkannya sendiri).
        'signin',
        'signup',
        'auth',
        'auth/*',
        'password-reset',
        'password-reset/*',
        'otp-verify',
        'email-verification',
        'email-verification/*',
    ];

    /**
     * Halaman auth yang menerima tamu dari panel Welcome.
     *
     * Route Sign In Filament pada panel welcome: /signin (slug 'signin').
     */
    public const LOGIN_ROUTE = 'filament.welcome.auth.login';

    /**
     * Filament guards every panel route with this, so a guest request either
     * falls through to the public paths above or is turned away here.
     *
     * @param  array<string>  $guards
     */
    protected function authenticate($request, array $guards): void
    {
        if (Filament::auth()->check()) {
            // Same panel-access check Filament performs, so swapping the
            // middleware in does not loosen who may enter the panel.
            $panel = Filament::getCurrentPanel();

            $this->auth->shouldUse($panel->getAuthGuard());

            $user = Filament::auth()->user();

            abort_if(
                $user instanceof FilamentUser
                    ? (! $user->canAccessPanel($panel))
                    : (config('app.env') !== 'local'),
                403,
            );

            return;
        }

        if ($this->isPublic($request)) {
            return;
        }

        $this->unauthenticated($request, $guards);
    }

    /**
     * Tamu yang ditolak di panel Welcome diarahkan ke halaman Sign In.
     */
    protected function redirectTo($request): ?string
    {
        return route(self::LOGIN_ROUTE);
    }

    protected function isPublic(Request $request): bool
    {
        $path = trim($request->path(), '/');

        foreach (self::PUBLIC_PATHS as $publicPath) {
            if (Str::is($publicPath, $path)) {
                return true;
            }
        }

        return false;
    }
}
