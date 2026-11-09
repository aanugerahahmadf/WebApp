<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel favorit dibuat dengan nama berkapital (`Favorites`) oleh
 * 2026_02_09_172010. MySQL di Windows tidak peka huruf sehingga model
 * `Favorite` (tabel default `favorites`) tetap bekerja, tetapi PostgreSQL
 * menyimpan nama tabel apa adanya dan setiap query model gagal dengan
 * "relation favorites does not exist".
 */
return new class extends Migration
{
    public function up(): void
    {
        $stored = $this->storedName();

        if ($stored === null || $stored === 'favorites') {
            return;
        }

        // PostgreSQL: nama tabel peka huruf besar-kecil, jadi `Favorites` dan
        // `favorites` adalah objek berbeda -- rename langsung aman.
        if (DB::getDriverName() === 'pgsql') {
            Schema::rename($stored, 'favorites');

            return;
        }

        // SQLite (dipakai test in-memory): juga bisa rename, tapi karena nama
        // tabel tidak peka huruf besar-kecil, rename langsung ke casing yang
        // sama bisa gagal. Pakai nama sementara agar pasti berhasil.
        if (DB::getDriverName() === 'sqlite') {
            Schema::rename($stored, 'favorites_tmp');
            Schema::rename('favorites_tmp', 'favorites');
        }

        // MySQL: perilaku casing bergantung OS/filesystem; di Windows (umum di
        // dev/CI lokal) sudah case-insensitive sehingga tidak perlu diubah,
        // dan rename paksa berisiko di setup yang sudah konsisten.
    }

    public function down(): void
    {
        // Nama berkapital tidak pernah menjadi kontrak; rollback cukup no-op.
    }

    private function storedName(): ?string
    {
        foreach (Schema::getTables() as $table) {
            if (strtolower($table['name']) === 'favorites') {
                return $table['name'];
            }
        }

        return null;
    }
};
