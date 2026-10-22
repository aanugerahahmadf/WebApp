<?php

use App\Models\User\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `exports.user` dan `imports.user` jadi POLIMORFIK.
 *
 * Kenapa perlu:
 *
 *   Aplikasi ini punya DUA model autentikasi. `users` (shopper + tamu,
 *   guard `web`) dan `admins` (tim internal, guard `admin`). Tabel `exports`
 *   dan `imports` awalnya hanya punya `user_id` dengan FK ke `users.id` --
 *   jadi asumsi Filament bawaannya ("semua yang export/import adalah baris
 *   `users`") sudah tidak benar sejak tabel `admins` dipisah.
 *
 *   Gejalanya tidak keras, dan justru itu yang berbahaya:
 *     - `Export::user()` jadi `belongsTo(User::class)`, jadi
 *       `associate($admin)` menulis `admins.id` ke kolom yang maknanya
 *       `users.id`. Tidak error (FK tetap valid selama id-nya kebetulan ada),
 *       tapi export tercatat atas nama shopper yang tidak ada hubungannya.
 *     - Notifikasi "ekspor selesai" dikirim ke shopper itu, bukan ke admin.
 *
 * Fix-nya mengikuti cara resmi Filament: `user_type` menyimpan model mana
 * yang menjadi pembuatnya, dan `Export::polymorphicUserRelationship()` /
 * `Import::polymorphicUserRelationship()` mengganti relasinya jadi `MorphTo`.
 *
 * `imports` ikut dikerjakan walau tidak ada `ImportAction` yang dipakai di
 * aplikasi ini. Tabelnya sudah ada dan FK-nya salah bentuk, jadi diperbaiki
 * sekarang -- bukan menunggu ada yang memakainya dan salah baca data.
 */
return new class extends Migration
{
    /**
     * Nama tabel yang relasi `user`-nya jadi polimorfik.
     *
     * @var list<string>
     */
    private const TABLES = ['exports', 'imports'];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            $hasType = Schema::hasColumn($tableName, 'user_type');

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $hasType): void {
                // FK ke `users` DIHAPUS. Dengan dua model autentikasi, satu FK
                // tidak bisa menyatakan "siapa pun yang boleh menjemduh data".
                $table->dropForeign(['user_id']);

                // `user_id` boleh NULL (ekspor tanpa actor) + `user_type`
                // menyimpan model aktualnya. Kolom `user_type` dibuat nullable
                // supaya baris lama bisa di-backfill di bawah, bukan gagal di
                // tengah jalan.
                $table->unsignedBigInteger('user_id')->nullable()->change();

                if (! $hasType) {
                    $table->string('user_type')->nullable()->after('user_id');
                }

                $table->index(['user_id', 'user_type'], $tableName.'_user_id_user_type_index');
            });
        }

        // Baris lama dibuat saat admin masih BERADA di tabel `users`, jadi
        // tipe morph-nya `User`. Tanpa backfill ini `morphTo()` mengembalikan
        // null untuk export lama -- riwayatnya attaches ke model yang salah
        // (atau tidak ada sama sekali).
        $userMorph = (new User)->getMorphClass();

        foreach (self::TABLES as $tableName) {
            DB::table($tableName)
                ->whereNull('user_type')
                ->update(['user_type' => $userMorph]);
        }
    }

    public function down(): void
    {
        // CATATAN: `user_id` sekarang boleh NULL (ekspor tanpa actor), dan
        // `NOT NULL` + FK ke `users` tidak bisa memulihkan baris NULL. Rollback
        // GAGAL dengan error SQL yang jelas -- itu lebih baik daripada diam-diam
        // menghapus riwayat export. Bersihkan baris NULL dulu kalau memang
        // perlu rollback.
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropIndex($tableName.'_user_id_user_type_index');
                $table->dropColumn('user_type');
                $table->unsignedBigInteger('user_id')->nullable(false)->change();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }
};
