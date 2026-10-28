<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BankSeeder extends Seeder
{
    public function run(): void
    {
        /*
         * Rekening settlement BRI.
         *
         * Semua dana dari Virtual Account unik pelanggan diteruskan BRI ke
         * rekening ini (421201032041536 / Anugerah Ahmad Fachrurochim).
         * Nomor VA yang ditampilkan ke pembeli BUKAN nomor ini, melainkan
         * nomor unik per user + pesanan yang dibuat lewat BRI SNAP.
         *
         * `qris_image` menunjuk gambar QRIS statis toko yang juga dipakai
         * halaman checkout (`App\Support\QrisImage\QrisImage`):
         * public/images/qris/qris.jpg. Tanpa nilai ini kolomnya NULL dan
         * QRIS hanya muncul lewat fallback model PaymentMethod.
         */
        DB::table('banks')->updateOrInsert(
            ['code' => 'bri'],
            [
                'name'             => 'Bank BRI',
                'type'             => 'bank_transfer',
                'account_number'   => '421201032041536',
                'account_holder'   => 'Anugerah Ahmad Fachrurochim',
                'logo'             => null,
                'qris_payload'     => null,
                'qris_image'       => \App\Support\QrisImage\QrisImage::RELATIVE_PATH,
                'is_active'        => true,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]
        );
    }
}
