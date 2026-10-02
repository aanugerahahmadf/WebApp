<?php

namespace App\Filament\Concerns;

use App\Http\Responses\WelcomeLogoutResponse\WelcomeLogoutResponse;
use Filament\Http\Responses\Auth\LogoutResponse;
use Filament\Navigation\MenuItem;

/**
 * Arahkan SignOut / Logout milik panel ke Welcome Home
 * (App\Filament\Welcome\Pages\Home\Home).
 *
 * Dipakai oleh UserPanelProvider dan AdminPanelProvider lewat override
 * `register()` -- keduanya extends PanelProvider yang masih ServiceProvider,
 * jadi `register()` dipanggil sekali saat boot.
 *
 * Kenapa `userMenuItems(['logout' => ...])` tidak cukup:
 * item logout di user menu Filament dirender sebagai
 * `<form method="post" action="...">`, jadi `->url()` di situ hanya mengganti
 * route tujuan POST-nya -- bukan halaman tujuan setelah user keluar. Halaman
 * tujuan itu ditentukan server-side oleh response logout.
 *
 * Kenapa binding ke KELAS NYATA, bukan ke interface `...\Contracts\LogoutResponse`:
 * Filament\Http\Controllers\Auth\LogoutController mengembalikan
 * `app(Filament\Http\Responses\Auth\LogoutResponse::class)`, dan kelas itu tidak
 * pernah me-resolve contract-nya sendiri. Binding ke contract karena itu tidak
 * akan pernah terpakai, dan SignOut berakhir di login URL panel masing-masing.
 * Karena return type controller adalah kelas nyatakanya, override-nya harus
 * berupa subclass dari kelas itu (WelcomeLogoutResponse) -- anonymous class
 * pemilik contract akan memicu TypeError.
 *
 * Login / Register sengaja tidak ikut di-override: default Filament
 * (`redirect()->intended(Filament::getUrl())`) sudah membawa user ke panelnya
 * masing-masing, jadi tidak ada yang perlu diubah.
 */
trait RedirectsLogoutToWelcomeHome
{
    /**
     * Label tombol SignOut.
     *
     * Sengaja tidak lewat `__()`:Filement mengambil label default dari
     * `filament-panels::layout.actions.logout.label`, yang sudah diterjemahkan
     * per-bahasa di lang/*.json ("Log out", "Keluar", "Déconnexion", ...).
     * Di sini labelnya dikunci ke "Sign Out" supaya seragam, dan supaya ikut
     * kosakota auth yang sudah dipakai halaman auth ("Sign In", "Sign Up").
     */
    public const SIGN_OUT_LABEL = 'Sign Out';

    /**
     * Ikon tombol SignOut, sama dengan default Filament supaya tidak berubah
     * dari yang sekarang.
     */
    public const SIGN_OUT_ICON = 'heroicon-m-arrow-left-on-rectangle';

    public function register(): void
    {
        $this->app->bind(LogoutResponse::class, WelcomeLogoutResponse::class);

        parent::register();
    }

    /**
     * Item menu SignOut untuk `userMenuItems()`.
     *
     * `->url()` sengaja TIDAK di-set: Filament merender item 'logout' sebagai
     * `<form method="post">` ke `filament()->getLogoutUrl()` milik panel yang
     * sedang aktif (lihat components/user-menu.blade.php). Jadi logout tetap
     * benar-benar POST ke route logout panel itu sendiri, dan yang menentukan
     * halaman tujuan adalah WelcomeLogoutResponse di atas. Mengisi `->url()`
     * hanya akan mengarahkan POST ke tempat lain tanpa perlu.
     */
    protected static function signOutMenuItem(): MenuItem
    {
        return MenuItem::make()
            ->label(self::SIGN_OUT_LABEL)
            ->icon(self::SIGN_OUT_ICON);
    }
}
