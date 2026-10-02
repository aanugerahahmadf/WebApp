<?php

namespace App\Filament\Concerns;

use App\Http\Responses\WelcomeLogoutResponse\WelcomeLogoutResponse;
use Filament\Http\Responses\Auth\LogoutResponse;

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
    public function register(): void
    {
        $this->app->bind(LogoutResponse::class, WelcomeLogoutResponse::class);

        parent::register();
    }
}
