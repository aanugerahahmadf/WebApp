<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pilihan huruf pribadi per pengguna.
 *
 * Tamu tidak punya baris di tabel `users` -- pilihan mereka disimpan di
 * session (lihat `App\Support\ViewerFont\ViewerFont`). Kolom ini supaya
 * pilihan user ikut ke perangkat lain: masuk di HP, buka di laptop, hurufnya
 * sama.
 *
 * Nilai kolom = NAMA KELUARGA dari katalog aset lokal
 * (`AppFont::families()`), bukan id. Alasannya: katalognya dibangun ulang
 * dari berkas font (`scripts/generate-fonts.py`), jadi id ikut berubah
 * setiap kali ada font baru; nama keluarga stabil.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'font_preference')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('font_preference')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'font_preference')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('font_preference');
            });
        }
    }
};
