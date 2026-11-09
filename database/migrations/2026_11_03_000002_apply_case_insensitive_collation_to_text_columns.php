<?php

use App\Support\PostgresCaseInsensitiveCollation\PostgresCaseInsensitiveCollation;
use Illuminate\Database\Migrations\Migration;

/**
 * Kolom teks PostgreSQL dibuat case-insensitive seperti MySQL.
 *
 * `utf8mb4_0900_ai_ci` membuat `=` dan `LIKE` tidak peka huruf di MySQL, dan
 * pencarian aplikasi (`where('name','like',...)`, `where('email',...)`)
 * mengandalkan itu. Setelah pindah ke PostgreSQL, collation default database
 * peka huruf sehingga query yang sama mengembalikan 0 baris tanpa error.
 *
 * Collation nondeterministic tidak bisa menjadi default database, jadi dipasang
 * per kolom. Lihat support class untuk alasan `und-u-ks-level2`.
 */
return new class extends Migration
{
    public function up(): void
    {
        PostgresCaseInsensitiveCollation::apply();
    }

    public function down(): void
    {
        PostgresCaseInsensitiveCollation::reset();
    }
};
