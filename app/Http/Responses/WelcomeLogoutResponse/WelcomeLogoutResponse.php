<?php

namespace App\Http\Responses\WelcomeLogoutResponse;

use App\Filament\Welcome\Pages\Home\Home;
use Filament\Http\Responses\Auth\Contracts\LogoutResponse as LogoutResponseContract;
use Illuminate\Http\RedirectResponse;

/**
 * Tujuan logout untuk SEMUA panel (Admin / User / Welcome): Welcome Home.
 *
 * Kenapa kelas ini ada, dan kenapa binding ke interface tidak cukup:
 *
 *   Filament\Http\Controllers\Auth\LogoutController::__invoke() mengembalikan
 *   `app(Filament\Http\Responses\Auth\LogoutResponse::class)` -- kelas NYATA,
 *   bukan interface `...\Contracts\LogoutResponse`. Response nyata itu pun
 *   tidak memanggil `app(Responsable::class)` di dalamnya; ia langsung
 *   `redirect()->to(Filament::hasLogin() ? getLoginUrl() : getUrl())`.
 *
 *   Artinya binding ke interface LogoutResponse di AppServiceProvider selama ini
 *   tidak pernah terpakai -- SignOut berakhir di halaman login panel masing-masing
 *   (atau dashboard welcome), tidak di Welcome Home. Karena return type
 *   controller adalah kelas nyata, override harus berupa SUBCLASS dari kelas
 *   nyata itu; anonymous class yang hanya mengimplementasikan contract akan
 *   memicu TypeError.
 *
 * Override-nya dipasang dari panel provider lewat trait
 * RedirectsLogoutToWelcomeHome (lihat UserPanelProvider & AdminPanelProvider).
 *
 * Sesi di-invalidate oleh controller sebelum response ini jalan, jadi flash
 * `after_logout` masih tersimpan di sesi baru dan dibaca satu kali oleh
 * HasAuthBreadcrumbs / HasDynamicBreadcrumbs pada render berikutnya -- itulah
 * yang membuat crumb Sign In setelah logout berparent ke "Beranda".
 */
class WelcomeLogoutResponse extends \Filament\Http\Responses\Auth\LogoutResponse implements LogoutResponseContract
{
    /**
     * Fallback kalau panel Welcome belum bisa di-resolve (mis. route belum
     * termuat). URL ini juga yang dipakai test sebagai nilai balik.
     */
    public const FALLBACK_URL = '/welcome/home';

    public function toResponse($request): RedirectResponse
    {
        try {
            $url = Home::getUrl(panel: 'welcome');
        } catch (\Throwable) {
            $url = self::FALLBACK_URL;
        }

        session()->flash('after_logout', true);

        return redirect()->to($url);
    }
}
