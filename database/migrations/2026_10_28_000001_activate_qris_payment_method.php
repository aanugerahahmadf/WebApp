<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Aktifkan kembali metode pembayaran QRIS.
 *
 * Seeder lama sengaja menonaktifkan QRIS karena jalurnya masih bergantung pada
 * QR dinamis BRI (butuh kredensial gateway). Toko sekarang sudah punya QRIS
 * statis resmi di `public/images/qris/qris.jpg` (QRIS GPN, NMID
 * ID1026626838399), jadi QRIS bisa dipakai sebagai pembayaran manual:
 * pembeli memindai gambar QR itu, lalu mengunggah bukti bayar seperti transfer
 * bank.
 *
 * Tanpa baris ini, metode QRIS tidak pernah muncul di pilihan pembayaran --
 * akibatnya gambar QRIS tidak pernah bisa tampil di halaman checkout, sebaik
 * apa pun tampilannya dibuat.
 *
 * `down()` sengaja kosong: mengembalikan status nonaktif berarti menutup
 * kembali kanal pembayaran yang memang dipakai toko.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('payment_methods')) {
            return;
        }

        DB::table('payment_methods')
            ->where('code', 'qris')
            ->update([
                'is_active' => true,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Sengaja kosong -- lihat catatan di atas.
    }
};
