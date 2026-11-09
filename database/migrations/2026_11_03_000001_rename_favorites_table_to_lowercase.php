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
        // Hanya PostgreSQL yang membeda-bedakan nama tabel dengan huruf besar-
        // kecil. Di MySQL/Windows dan SQLite `Favorites` sudah terbaca sebagai
        // `favorites`, dan percobaan rename justru gagal karena sistem menganggap
        // keduanya objek yang sama.
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $stored = $this->storedName();

        if ($stored !== null && $stored !== 'favorites') {
            Schema::rename($stored, 'favorites');
        }
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
