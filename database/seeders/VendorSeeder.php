<?php

namespace Database\Seeders;

use App\Models\User\User;
use App\Models\Vendor\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class VendorSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('--- Seeding Vendors ---');

        // Prompt interaktif hanya boleh muncul saat dijalankan manual dari CLI
        // (bukan saat test). Saat testing, console di-mock tanpa askQuestion(),
        // sehingga panggil confirm() akan crash. Gunakan default (false).
        $confirmed = ! app()->environment('testing') &&
            (bool) $this->command->confirm('Vendor nonaktif: semua item saat ini in-house tanpa vendor. Tetap buat akun vendor (untuk calon mitra)? [y/N]', false);

        if ($confirmed) {
            $this->seedVendors();
        }

        $this->command->info('--- Vendor Seeding Complete ---');
    }

    private function seedVendors(): void
    {
        $vendorData = [
            ['store_name' => 'Toko Bunga Indah', 'contact_person' => 'Ayu Lestari', 'no_telp' => '081234567801', 'store_description' => 'Menyediakan berbagai rangkaian bunga segar untuk pernikahan Anda dengan sentuhan elegan dan natural.', 'logo' => 'images/vendor/vendor-logo-0.png'],
            ['store_name' => 'Dekorasi Cinta Abadi', 'contact_person' => 'Bambang Wijaya', 'no_telp' => '081234567802', 'store_description' => 'Spesialis dekorasi pernikahan mewah dengan konsep modern dan klasik yang timeless.', 'logo' => 'images/vendor/vendor-logo-1.png'],
            ['store_name' => 'Pelaminan Mewah', 'contact_person' => 'Citra Dewi', 'no_telp' => '081234567803', 'store_description' => 'Menyewakan pelaminan adat dan modern dengan kualitas terbaik untuk hari bahagia Anda.', 'logo' => 'images/vendor/vendor-logo-2.png'],
            ['store_name' => 'Bridal Bloom Studio', 'contact_person' => 'Dwi Hartono', 'no_telp' => '081234567804', 'store_description' => 'Studio bunga pengantin lengkap dengan layanan konsultasi dekorasi pernikahan.', 'logo' => 'images/vendor/vendor-logo-3.png'],
            ['store_name' => 'Florist Nusantara', 'contact_person' => 'Eka Pratiwi', 'no_telp' => '081234567805', 'store_description' => 'Menghadirkan keindahan bunga Nusantara dalam setiap rangkaian pernikahan.', 'logo' => 'images/vendor/vendor-logo-4.png'],
            ['store_name' => 'Dekorasi Bahagia', 'contact_person' => 'Fajar Nugroho', 'no_telp' => '081234567806', 'store_description' => 'Dekorasi pernikahan dengan konsep ceria dan warna-warna pastel yang memikat.', 'logo' => 'images/vendor/vendor-logo-5.png'],
            ['store_name' => 'Taman Cinta Abadi', 'contact_person' => 'Gita Rahmawati', 'no_telp' => '081234567807', 'store_description' => 'Konsep taman bunga alami untuk pernikahan outdoor yang romantis dan berkesan.', 'logo' => 'images/vendor/vendor-logo-6.png'],
            ['store_name' => 'Panggung Istimewa', 'contact_person' => 'Hendra Gunawan', 'no_telp' => '081234567808', 'store_description' => 'Membangun panggung pernikahan impian dengan desain eksklusif dan megah.', 'logo' => 'images/vendor/vendor-logo-7.png'],
            ['store_name' => 'Bunga Kasih Sayang', 'contact_person' => 'Intan Permatasari', 'no_telp' => '081234567809', 'store_description' => 'Rangkaian bunga penuh cinta dan makna untuk momen pernikahan yang tak terlupakan.', 'logo' => 'images/vendor/vendor-logo-8.png'],
            ['store_name' => 'Pernikahan Idaman', 'contact_person' => 'Joko Susilo', 'no_telp' => '081234567810', 'store_description' => 'Solusi lengkap dekorasi pernikahan dari konsep hingga eksekusi sesuai impian Anda.', 'logo' => 'images/vendor/vendor-logo-9.png'],
        ];

        foreach ($vendorData as $i => $data) {
            $user = User::firstOrCreate(
                ['email' => "vendor{$i}@demo.com"],
                [
                    'full_name' => $data['contact_person'],
                    'username' => "vendor{$i}",
                    'password' => Hash::make('@Vendor123'),
                    'email_verified_at' => now(),
                ]
            );
            if (! $user->hasRole('vendor')) {
                $user->assignRole('vendor');
            }

            Vendor::updateOrCreate(
                ['store_name' => $data['store_name']],
                [
                    'contact_person' => $data['contact_person'],
                    'no_telp' => $data['no_telp'],
                    'store_description' => $data['store_description'],
                    'logo' => $data['logo'],
                    'is_active' => true,
                ]
            );

            $this->command->line("  <info>✓</info> {$data['store_name']} — akun: vendor{$i}@demo.com / @Vendor123");
        }

        $this->command->info('--- Vendor Seeding Complete ---');
    }
}
