<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Kolom pengenalan perangkat untuk tabel trusted_devices (legacy).
 *
 * trusted_devices sebelumnya HANYA daftar perangkat yang user sebut
 * terpercaya (device_name + platform + trusted_at). Dari sekarang tabel
 * ini juga jadi REGISTRY perangkat yang pernah sign in ala Google:
 *
 *   - kolom device_fingerprint (yang sudah ada) dipakai sbg pengenal
 *     perangkat STABIL: hash User-Agent, diisi DeviceTrustService;
 *   - trusted_at masih NULL berarti "menunggu jawaban Apakah Ini Anda?";
 *   - kolom seen/ip menyimpan rincian kartu perangkat;
 *   - denied_at menandai jawaban "Bukan saya";
 *   - last_notified_at mencegah notifikasi kembar dalam hitungan detik.
 *
 * Semua kolom NULL-able supaya baris lama (device_fingerprint-nya mungkin
 * kosong dari era lama) tetap valid dan tidak menabrak aturan DB. Lookup
 * (user_id, device_fingerprint) di-indeks biasa, bukan unique -- baris
 * legacy dengan kolom kosong tidak boleh menggagalkan migrasi.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('trusted_devices', function (Blueprint $table) {
            $table->index('device_fingerprint', 'trusted_devices_fingerprint_index');
        });

        Schema::table('trusted_devices', function (Blueprint $table) {
            $table->timestamp('first_seen_at')->nullable()->after('trusted_at');
            $table->timestamp('last_seen_at')->nullable()->after('first_seen_at');
            $table->string('last_ip', 45)->nullable()->after('last_seen_at');
            $table->string('last_city')->nullable()->after('last_ip');
            $table->string('last_region')->nullable()->after('last_city');
            $table->string('last_country')->nullable()->after('last_region');
            $table->timestamp('denied_at')->nullable()->after('last_country');
            $table->timestamp('last_notified_at')->nullable()->after('denied_at');
        });
    }

    public function down(): void
    {
        Schema::table('trusted_devices', function (Blueprint $table) {
            $table->dropIndex('trusted_devices_fingerprint_index');
            $table->dropColumn([
                'first_seen_at',
                'last_seen_at',
                'last_ip',
                'last_city',
                'last_region',
                'last_country',
                'denied_at',
                'last_notified_at',
            ]);
        });
    }
};
