<?php

namespace App\Http\Middleware\SuperAdmin;

use App\Models\Admin\Admin;
use App\Support\AdminAccess\AdminAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gerbang panel admin: hanya admin yang masuk lewat guard `admin` dengan role
 * admin yang boleh lewat.
 *
 * DAN context: middleware ini dipakai di DUA tempat dengan cara berbeda.
 *
 *   1. Panel Filament admin -- `AdminPanelProvider::authMiddleware()`. Di sini
 *      panel sudah mengeset `->authGuard('admin')`, jadi Filament sudah
 *      mengisi guard itu dan middleware ini tinggal membaca.
 *
 *   2. API `/api/admin/*` -- `routes/api/api.php`, di dalam
 *      `auth:sanctum`. Tidak ada session guard di sini: request datang dengan
 *      header `Authorization: Bearer <token Sanctum>`, dan token itu milik
 *      tabel `users`. Ini jalur lama yang masih dipakai tool/internal.
 *
 * Karena itu `resolveUser()` mencoba `admin` dulu, lalu jatuh ke sanctum.
 * Urutan itu penting: sanctum dibaca HANYA kalau guard `admin` kosong, jadi
 * website tidak pernah bisa memakai token shopper untuk menyamar sebagai admin.
 * Sebaliknya token Sanctum tidak bisa membuka panel admin, karena panel admin
 * butuh session guard `admin` dan `Authenticate` Filament akan menolak sebelum
 * middleware ini jalan.
 */
class SuperAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->resolveUser($request);

        if ($user === null) {
            abort(403);
        }

        // Hanya model Admin yang bisa lolos di jalur session. Kalau request-nya
        // datang dari token Sanctum (jalur 2), user-nya `App\Models\User\User`
        // dan tidak mungkin memegang role ber-guard `admin` -- jadi dia otomatis
        // gagal di hasAnyRole() di bawah. Ini yang menutup pintu "akun belanja
        //+$token Sanctum => akses admin".
        if (! $user instanceof Admin) {
            abort(403);
        }

        // Akun yang dinonaktifkan langsung kehilangan akses, tanpa menunggu
        // session-nya kedaluwarsa.
        if (! $user->isActive()) {
            abort(403);
        }

        if (! $user->hasAnyRole(AdminAccess::ADMIN_ROLES)) {
            abort(403);
        }

        return $next($request);
    }

    /**
     * Admin yang sedang masuk, dari guard mana pun yang memang berlaku.
     */
    protected function resolveUser(Request $request): ?object
    {
        $admin = Auth::guard('admin')->user();

        if ($admin !== null) {
            return $admin;
        }

        // Fallback untuk /api/admin/*: bearer token Sanctum. Dicek hanya kalau
        // request benar-benar punya header token, supaya tidak pernah memanggil
        // sanctum pada request web biasa.
        if ($request->bearerToken()) {
            return Auth::guard('sanctum')->user();
        }

        return null;
    }
}