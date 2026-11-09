<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `notifications.data` harus bertipe JSON, bukan teks.
 *
 * Stub migrasi Laravel membuat kolom ini `text`, dan MySQL memaklumi operator
 * `->>` di atas teks yang isinya JSON. PostgreSQL menolak:
 *
 *     SQLSTATE[42883]: operator does not exist: text ->> unknown
 *
 * Query itu bukan query aplikasi sendiri — tray notifikasi Filament
 * (`database-notifications.blade.php`) menyaring dengan
 * `where('data->format', 'filament')`, jadi setiap halaman panel yang
 * merender notifikasi ikut 500, bukan hanya halaman notifikasi.
 *
 * `jsonb` dipakai supaya `->>` dan indeks GIN bekerja tanpa cast. Kolom JSON
 * tidak punya collation, jadi `PostgresCaseInsensitiveCollation::apply()`
 * (listener `MigrationsEnded`) tidak menyentuh kolom ini lagi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if ($this->currentType() === null || $this->currentType() === 'jsonb') {
            return;
        }

        DB::statement('alter table "notifications" alter column "data" type jsonb using "data"::jsonb');
    }

    public function down(): void
    {
        if ($this->currentType() !== 'jsonb') {
            return;
        }

        DB::statement('alter table "notifications" alter column "data" type text using "data"::text');
    }

    private function currentType(): ?string
    {
        if (DB::getDriverName() !== 'pgsql') {
            return null;
        }

        $row = DB::selectOne(
            "select data_type from information_schema.columns
              where table_name = 'notifications' and column_name = 'data'"
        );

        return $row === null ? null : $row->data_type;
    }
};
