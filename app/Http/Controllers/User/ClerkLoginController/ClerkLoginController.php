<?php

namespace App\Http\Controllers\User\ClerkLoginController;

use App\Http\Controllers\Controller;
use App\Models\User\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Login lewat token Sanctum, lalu arahkan ke panel yang memang boleh dibuka.
 *
 * Dulu baris terakhirnya:
 *
 *     $panel = $user->hasRole('super_admin') ? 'admin' : 'user';
 *
 * Itu jalur eskalasi yang sekarang sudah mustahil terjadi, dan sengaja
 * ditutup di sini.
 *
 * `$accessToken->tokenable` di bawah adalah `App\Models\User\User` -- token
 * Sanctum hanya bisa diterbitkan oleh model `users`, dan tabel `admins` tidak
 * punya `HasApiTokens` sama sekali. Jadi pertanyaan "apakah dia super_admin"
 * tidak pernah bisa dijawab `true` dari sini.
 *
 * Charm: memaksa `$panel = 'admin'` juga tidak akan berhasil. `Authenticate`
 * Filament memakai guard `admin` yang provider-nya `admins`; session guard
 * `web` yang baru diisi baris di `users` tidak dikenal di sana, jadi Filament
 * mengirimnya kembali ke `/admin/signin`. Baris di bawah menuliskan itu secara
 * eksplisit supaya tidak perlu ditebak lagi nanti.
 *
 * Admin punya pintunya sendiri: `/admin/signin` dengan provider `admins`.
 */
class ClerkLoginController extends Controller
{
    public function login(Request $request): RedirectResponse
    {
        $token = $request->query('token');

        if (! $token) {
            return redirect(route('filament.admin.auth.login'))->with('error', 'Token tidak ditemukan');
        }

        $accessToken = PersonalAccessToken::findToken($token);

        if (! $accessToken) {
            return redirect(route('filament.admin.auth.login'))->with('error', 'Token tidak valid');
        }

        $user = $accessToken->tokenable;

        if (! $user || ! $user instanceof User) {
            return redirect(route('filament.admin.auth.login'))->with('error', 'Pengguna tidak ditemukan');
        }

        Auth::login($user);

        // Selalu 'user'. Baris `users` tidak mungkin memegang role admin, dan
        // token Sanctum tidak bisa membuka panel admin -- lihat docblock kelas.
        return redirect('/user');
    }
}