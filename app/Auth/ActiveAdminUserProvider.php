<?php

namespace App\Auth;

use App\Models\Admin\Admin;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Hashing\Hasher;

/**
 * User provider untuk guard `admin` yang menolak akun nonaktif.
 *
 * Kenapa tidakandalkan pemeriksaan di halaman SignIn saja:
 *
 *   `Auth::guard('admin')->attempt()` akan selalu mengembalikan `true` untuk
 *   kata sandi yang benar, siapa pun yang menaruhnya -- termasuk akun
 *   yang `active_status`-nya false. Itu perilaku bawaan guard, dan setiap
 *   jalur login baru (CLI, action kustom, SSO, test) akan menyalinnya lagi.
 *   Menonaktifkan admin jadi bergantung pada setiap pemanggil ingat untuk
 *   mengecek, dan satu yang lupa berarti akunnya tetap hidup.
 *
 * Di sini aturannya ditegakkan sekali, di lapisan yang sama dengan yang
 * membaca baris database. `attempt()` menanyakan kredensial lewat
 * `retrieveByCredentials()`, dan mengembalikannya null sudah cukup untuk membuat
 * percobaan itu gagal.
 *
 * `retrieveById()` juga ditimpa, dan itu yang menangani akun yang dinonaktifkan
 * DI TENGAH SESI: guard tidak bisa me-resolve barisnya, jadi session-nya
 * dibuang dan admin langsung expulsion -- bukan menunggu session kedaluwarsa.
 *
 * Hanya berlaku untuk guard `admin`. Provider `users` (shopper) tidak
 * memakai kelas ini, jadi status `active_status` di `users` tetap punya
 * makna yang sama seperti sebelumnya.
 */
class ActiveAdminUserProvider extends EloquentUserProvider
{
    public function __construct(Hasher $hasher, string $model)
    {
        parent::__construct($hasher, $model);
    }

    /**
     * @param  array<string, string>  $credentials
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $admin = parent::retrieveByCredentials($credentials);

        return $this->isUsable($admin) ? $admin : null;
    }

    /**
     * Dipakai saat session guard me-resolve ulang admin yang sudah login.
     *
     * Mengembalikan null membuat `SessionGuard::user()` melepas session, jadi
     * akun yang dinonaktifkan langsung kehilangan akses di request berikutnya.
     */
    public function retrieveById($identifier): ?Authenticatable
    {
        $admin = parent::retrieveById($identifier);

        return $this->isUsable($admin) ? $admin : null;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        return parent::validateCredentials($user, $credentials);
    }

    /**
     * Hanya akun `Admin` yang aktif yang boleh lewat.
     *
     * Cek `instanceof Admin` dipakai sebagai penjaga, bukan sekadar
     * `active_status`: kalau `AUTH_ADMIN_MODEL` diarahkan ke model lain yang
     * tidak punya kolom itu, membaca `$user->active_status` akan menghasilkan
     * null dan membiarkan semua orang masuk. Menolak di sini lebih aman
     * daripada membuka.
     */
    protected function isUsable(mixed $admin): bool
    {
        if (! $admin instanceof Admin) {
            return false;
        }

        // `deleted_at` ikut diperiksa supaya baris soft-deleted tidak bisa
        // di-revive hanya karena password-nya masih cocok.
        if (method_exists($admin, 'trashed') && $admin->trashed()) {
            return false;
        }

        return (bool) $admin->active_status;
    }
}