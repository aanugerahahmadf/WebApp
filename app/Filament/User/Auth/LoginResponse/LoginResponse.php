<?php

namespace App\Filament\User\Auth\LoginResponse;

use Filament\Http\Responses\Auth\Contracts\LoginResponse as BaseLoginResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Custom LoginResponse untuk panel storefront (dulu panel USER).
 *
 * Semua user (termasuk admin team dan Google OAuth) yang masuk ke panel
 * diarahkan langsung ke /home. Tidak ada redirect ke halaman lain
 * (Complete Profile, Security Question, Two Factor Auth, dst) kecuali
 * memang diperlukan oleh alur auth itu sendiri.
 *
 * CATATAN -- tipe balasan.
 *
 * Halaman auth Filament adalah komponen Livewire, dan di dalam konteks
 * Livewire `redirect()` adalah milik Livewire, bukan milik Laravel:
 * `redirect()->intended()` mengembalikan objek `Redirector` Livewire --
 * BUKAN `Illuminate\Http\RedirectResponse`.
 *
 * Karena itu tipe balannya harus memuat KEDUA-nya, sama seperti
 * `Filament\Http\Responses\Auth\LoginResponse` bawaan. Kalau hanya
 * `Response|RedirectResponse`, setiap Sign In di dalam Livewire berakhir
 * TypeError: "Return value must be of type ... Redirector returned".
 */
class LoginResponse implements BaseLoginResponse
{
    public function toResponse($request): \Illuminate\Http\Response|RedirectResponse|Redirector
    {
        return redirect()->intended(route('filament.welcome.pages.home'));
    }
}
