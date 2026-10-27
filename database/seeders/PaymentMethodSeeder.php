<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            // ── VIRTUAL ACCOUNT BRI (PAYUNG: BANK/M-BANKING + E-WALLET) ──
            // Satu payung pembayaran. 1 pesanan = 1 nomor VA BRI unik yang
            // bisa dibayar dari m-banking bank mana pun ATAU e-wallet yang
            // mendukung transfer ke nomor VA. Dana masuk otomatis ke rekening
            // BRI admin via webhook (tanpa verifikasi admin).
            [
                'name'           => 'Virtual Account BRI',
                'type'           => 'virtual_account',
                'code'           => 'bri_va',
                'bank_name'      => 'BRI',
                // Jangan isi rekening merchant di metode VA. Nomor VA dan
                // nama pemiliknya dibuat untuk user/order oleh BRI dan
                // dikirim kembali setelah create-va berhasil.
                'account_number' => null,
                'account_holder' => null,
                'image_url'      => null,
                'instructions'   => "1. Buka m-banking bank mana pun (BCA, Mandiri, BNI, BRI, dll) atau aplikasi e-wallet\n2. Pilih menu (Transfer/Bayar ke) → nomor Virtual Account BRI di bawah ini\n3. Masukkan nominal sesuai total pembayaran\n4. Konfirmasi → saldo otomatis berkurang, uang masuk rekening BRI\n5. Status pesanan otomatis LUNAS tanpa verifikasi admin",
                'fee'            => 0,
                'sort_order'     => 1,
                'is_active'      => true,
            ],
            // ── QRIS DINAMIS (BANK & E-WALLET) ──────────────────────────
            // 1 pesanan = 1 kode QR dinamis. Bisa discan dari aplikasi mana
            // pun (GoPay, DANA, OVO, ShopeePay, m-banking, dll). Dana masuk
            // otomatis ke rekening BRI admin via webhook.
            [
                'name'           => 'QRIS (Semua Bank & E-Wallet)',
                'type'           => 'qris',
                'code'           => 'qris',
                'bank_name'      => 'BRI',
                'account_number' => '421201032041536',
                'account_holder' => 'Anugerah Ahmad Fachrurochim',
                'image_url'      => null,
                'instructions'   => "1. Buka aplikasi bank/e-wallet Anda (BCA Mobile, BRImo, BNI Mobile, DANA, GoPay, OVO, ShopeePay, dll)\n2. Pilih menu Scan / Bayar QRIS\n3. Scan kode QR yang ditampilkan\n4. Konfirmasi di aplikasi Anda (saldo otomatis berkurang)\n5. Status pesanan otomatis LUNAS tanpa verifikasi admin",
                'fee'            => 0,
                'sort_order'     => 2,
                'is_active'      => true,
            ],
            // ── CASH ───────────────────────────────────────────────────
            [
                'name'           => 'Bayar di Tempat (Cash)',
                'type'           => 'cash',
                'code'           => 'cash',
                'bank_name'      => null,
                'account_number' => null,
                'account_holder' => null,
                'image_url'      => null,
                'instructions'   => "1. Lakukan pembayaran tunai di lokasi acara\n2. Minta tanda terima dari admin\n3. Upload bukti tanda terima di halaman pembayaran",
                'fee'            => 0,
                'sort_order'     => 3,
                'is_active'      => true,
            ],
        ];

        foreach ($methods as $method) {
            DB::table('payment_methods')->updateOrInsert(
                ['code' => $method['code']],
                $method + [
                    'updated_at' => now(),
                ]
            );
        }

        // Nonaktifkan metode lama yang butuh gateway (Midtrans) atau rekening fiktif,
        // agar tidak muncul di halaman pembayaran aplikasi.
        $legacyCodes = ['bca', 'mandiri', 'gopay', 'ovo', 'dana', 'shopeepay', 'credit_card', 'bri', 'qris'];
        foreach ($legacyCodes as $code) {
            DB::table('payment_methods')->where('code', $code)->update(['is_active' => false, 'updated_at' => now()]);
        }
    }
}
