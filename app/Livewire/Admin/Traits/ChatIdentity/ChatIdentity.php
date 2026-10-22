<?php

namespace App\Livewire\Admin\Traits\ChatIdentity;

use App\Models\User\User;
use App\Support\ChatStaff\ChatStaff;

/**
 * Identitas chat untuk komponen Livewire panel ADMIN.
 *
 * Kenapa perlu: di panel admin `auth()->user()` adalah
 * `App\Models\Admin\Admin` (guard `admin`, tabel `admins`), sedangkan
 * `inboxes.user_ids` dan `messages.user_id` menyimpan `users.id`. Memakai
 * `auth()->id()` apa adanya berarti memakai `admins.id`, dan akibatnya:
 *
 *   - `Auth::user()->allConversations()` -> method tidak ada (halaman 500);
 *   - pesan terkirim dengan `user_id` yang tidak ada di `users` (FK gagal);
 *   - `read_by` / reaksi / bintang menyimpan id yang tidak pernah cocok,
 *     jadi centang "sudah dibaca" tidak pernah muncul.
 *
 * Yang benar: seluruh panel admin berbagi SATU identitas staf -- baris `users`
 * yang tertaut ke super admin (`ChatStaff::adminUser()`), baris yang sama yang
 * dipakai tamu sebagai tujuan chat. Satu identitas berarti percakapan tidak
 * terpecah dan `ChatStaff::isStaff()` tetap konsisten.
 *
 * Tanpa tautan (admin belum menjalankan `php artisan admin:grant-storefront`),
 * `chatUser()` null dan pemakaiannya harus degrade dengan rapi -- bukan 500.
 */
trait ChatIdentity
{
    /**
     * Baris `users` yang mewakili staf di percakapan, atau null kalau belum
     * ada tautan.
     */
    public function chatUser(): ?User
    {
        return ChatStaff::adminUser();
    }

    /**
     * `users.id` staf, untuk kolom `user_id` dan daftar `read_by`.
     */
    public function chatUserId(): ?int
    {
        return $this->chatUser()?->getKey();
    }
}
