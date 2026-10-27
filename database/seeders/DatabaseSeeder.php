<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run(): void
    {
        $this->call([
            // Identity dan authorization harus dibuat lebih dulu.
            RolesAndPermissionsSeeder::class,
            SuperAdminSeeder::class,

            // Data pengguna dan master katalog.
            CustomerSeeder::class,
            ReferenceOptionSeeder::class,
            CategorySeeder::class,
            CatalogSeeder::class,

            // Data transaksi/konten yang memiliki relasi ke katalog atau user.
            DiscountSeeder::class,
            VoucherSeeder::class,
            ReviewSeeder::class,

            // Konten halaman dan opsi pembayaran.
            LegalSeeder::class,
            HelpSeeder::class,
            PaymentMethodSeeder::class,
            BankSeeder::class,

            // Background default panel User & Welcome.
            BackgroundSeeder::class,

            // Hubungkan akun admin ke identitas panel storefront bila diperlukan.
            AdminUserPanelAccessSeeder::class,
        ]);
    }
}
