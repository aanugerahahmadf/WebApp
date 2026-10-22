<?php

namespace App\Support\ChatStaff;

use App\Models\Admin\Admin;
use App\Models\User\User;
use Illuminate\Support\Facades\Cache;

/**
 * Menjawab satu pertanyaan untuk fitur chat: siapa yang dihitung staff?
 *
 * Kenapa perlu kelas tersendiri:
 *
 *   Dulu jawabannya satu baris: `User::whereHas('roles', name: super_admin)`.
 *   Sekarang admin tidak lagi ada di tabel `users` -- mereka ada di `admins`,
 *   dengan guard role sendiri. Baris lama itu tidak mungkin bekerja lagi, dan
 *   kalau dibiarkan begitu, fitur chat akan selalu "tidak menemukan admin":
 *     - pesan tamu tidak pernah sampai ke siapa pun, dan
 *     - konfirmasi pesanan terkirim atas nama pembeli sendiri.
 *
 *   Gejalanya halus, karena tidak ada error thrown -- hanya chat yang diam.
 *
 *   Yang harus dijaga: `inboxes.user_ids` dan `messages.user_id` masih
 *   menyimpan `users.id`. Jadi "staff" di sini berarti BARIS DI `users` yang
 *   tertaut ke akun admin -- bukan objek `Admin`-nya. Itulah kenapa tautannya
 *   dibuat lewat `php artisan admin:grant-storefront`, dan kenapa kelas ini
 *   selalu mengembalikan `User` (atau null), tidak pernah `Admin`.
 *
 * Semua pemanggilan harus lewat sini. Menulis ulang query Super Admin di
 * Livewire atau controller akan mudah kembar ke versi berbeda -- dan yang
 * lupa tautan akan memperlakukan admin sebagai tamu.
 */
class ChatStaff
{
    private const CACHE_KEY = 'chat-staff-admin-user-id';

    /**
     * Baris `users` milik super admin, atau null kalau belum ada.
     *
     * Memakai cache supaya query ini tidak jalan di setiap pesan. Cache
     * sengaja keyed global (bukan per admin) karena hanya satu staff yang
     * dipakai sebagai tujuan chat, jadi tidak ada yang bisa tertukar.
     */
    public static function adminUser(): ?User
    {
        $id = Cache::rememberForever(self::CACHE_KEY, function (): ?int {
            return self::findLinkedUser()?->getKey();
        });

        if ($id === null) {
            return null;
        }

        // Di-resolve ulang lewat model, bukan diambil dari cache. Cache
        // di-refresh tiap request di panel, dan menyimpan objek Eloquent
        // bercampur dengan model lain di store yang sama adalah sumber bug
        // serialization yang sulit dilacak.
        return User::find($id);
    }

    /**
     * Baris `users` tertaut milik super admin pertama.
     *
     * Steel: `whereNotNull('user_id')` adalah sift-nya -- admin yang belum
     * diberi identitas belanja (kasus normal, dan default yang disengaja)
     * dilewati tanpa error, dan chat tetap degrade dengan rapi.
     */
    private static function findLinkedUser(): ?User
    {
        $admin = Admin::query()
            ->where('active_status', true)
            ->whereNotNull('user_id')
            ->whereHas('roles', fn ($query) => $query->where('name', 'super_admin'))
            ->orderBy('id')
            ->first();

        return $admin?->user;
    }

    /**
     * Apakah baris `users` ini adalah staff.
     *
     * Menggantikan `User::hasRole('super_admin')` yang lama, dan hasilnya memang
     * tidak sama persis dengan cara lama -- itu disengaja. Yang dihitung staff
     * sekarang adalah baris `users` yang tertaut ke akun super admin. Seseorang
     * yang hanya punya role ber-guard 'web', atau akun admin yang belum diberi
     * identitas belanja, KEDUA-DUA-nya dianggap bukan staff. Itu yang menjaga
     * aturan "pengirim admin tidak memicu bot balasan otomatis".
     */
    public static function isStaff(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        $staffId = static::adminUser()?->getKey();

        return $staffId !== null && (int) $user->getKey() === (int) $staffId;
    }

    /**
     * Versi yang menerima objek dari session mana pun.
     *
     * Dipakai dari Livewire panel admin, yang `auth()`-nya menunjuk
     * `App\Models\Admin\Admin` (guard `admin`) -- bukan `User`. Karena itu
     * pencocokan harus berbasis tipe, bukan instanceof `User`.
     */
    public static function isStaffMixed(mixed $subject): bool
    {
        if ($subject instanceof User) {
            return static::isStaff($subject);
        }

        // Admin yang sedang login di guard 'admin'.
        if ($subject instanceof Admin) {
            return $subject->active_status && $subject->hasRole('super_admin');
        }

        return false;
    }

    /**
     * Buang cache setelah `admins.user_id` berubah.
     *
     * Dipanggil dari command `admin:grant-storefront`. Tanpa ini, chat tetap
     * memakai hasil lama sampai cache dibersihkan -- jadi grant-nya terlihat
     * tidak berhasil walau database-nya sudah benar.
     */
    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}