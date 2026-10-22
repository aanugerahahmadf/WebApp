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
 * Everything else (cart, orders, wishlist, messages, settings, profile, search,
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
        // (/signin, /signup, /auth, /password-reset/*, /o-t-p-verify,
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
        'o-t-p-verify',
        'email-verification',
        'email-verification/*',
    ];

    /**
     * Halaman auth yang menerima tamu dari panel Welcome.
     *
     * SHOPEE-STYLE: panel user dilebur ke panel welcome, jadi route auth
     * sekarang hidup di `filament.welcome.auth.index` (~FORM yang sama,
     * path /auth) yang memegang dua pintu: tombol Sign In ke form, dan
     * "Continue With Google".
     *
     * Namanya WAJIB 'welcome' sekarang: setelah UserPanelProvider keluar dari
     * bootstrap/providers.php, tidak ada lagi route `filament.user.auth.*` --
     * memanggil nama lama melempar RouteNotFoundException dan SELURUH halaman
     * panel (public path pun) 500 sebelum halaman manapun dirender.
     *
     * Alasannya tetap dari dulu: panel Welcome punya banyak pintu akun yang
     * hanya butuh satu
     * aksi -- tambah keranjang, wishlist, checkout -- dan sebagian pengguna
     * sudah punya pintu masuk yang tidak butuh email/kata sandi. Menugaskan
     * tamu yang menekan "Masukkan ke Keranjang" ke form email/kata sandi
     * menutup halaman itu tanpa Google, padahal tombolnya ada di halaman yang
     * sekarang jadi tujuan. Menunjuk ke /auth membuat semua pintu masuk
     * tamu punya pilihan yang sama: Sign In ATAU Google.
     *
     * Dipakai lewat `route(self::LOGIN_ROUTE)`, bukan `UserAuth::getUrl()`,
     * supaya ada SATU nama yang benar di seluruh panel: enam kelas Welcome
     * (CatalogResource, PackageResource, ManageCatalogs, ManagePackages,
     * CheckoutCatalog, CheckoutPackage) memanggil konstanta ini, dan menulis
     * URL langsung di sana berarti enam tempat harus dijaga sinkron. Satu
     * konstanta yang salah = semua pintu masuk tamu meleset.
     *
     * Yang dipakai adalah NAMA route-nya, bukan slug: halaman Auth
     * mendaftarkan routenya sendiri lewat `registerRoutes()`, jadi nama bawaan
     * Filament (`filament.welcome.pages.auth`) tidak ada -- persis alasan
     * `getUrl()`-nya menunjuk `filament.welcome.auth.index`.
     */
    public const LOGIN_ROUTE = 'filament.welcome.auth.index';

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
     * The welcome panel has no auth page of its own; the user panel owns them,
     * and the whole reason this middleware exists is to end up there.
     *
     * Yang ditunjuk bukan form Sign In, tapi halaman auth pertama /auth --
     * lihat LOGIN_ROUTE. Tamu yang ditolak di panel Welcome selalu diarahkan ke
     * halaman yang bisa menawarkan Sign In dan Google sekaligus.
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
