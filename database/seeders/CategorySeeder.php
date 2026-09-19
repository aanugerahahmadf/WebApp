<?php

namespace Database\Seeders;

use App\Models\Category\Category;
use App\Traits\TranslatesContent\TranslatesContent;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    use TranslatesContent;

    public function run(): void
    {
        $this->command->info('--- Seeding Categories ---');

        $productData = [
            'buket-bunga'       => 'Buket Bunga',
            'dekorasi-meja'     => 'Dekorasi Meja',
            'ornamen-dinding'   => 'Ornamen Dinding',
            'backdrop-pelaminan'=> 'Backdrop Pelaminan',
            'hiasan-gaun'       => 'Hiasan Gaun',
            'seserahan'         => 'Seserahan',
            'kain-dekorasi'     => 'Kain Dekorasi',
            'lighting'          => 'Lighting & Lampu',
            'aksesoris'         => 'Aksesoris Pernikahan',
            'tanaman-hias'      => 'Tanaman Hias',
        ];

        $packageData = [
            'hemat'             => 'Paket Hemat',
            'standar'           => 'Paket Standar',
            'premium'           => 'Paket Premium',
            'mewah'             => 'Paket Mewah',
            'eksekutif'         => 'Paket Eksekutif',
            'deluxe'            => 'Paket Deluxe',
            'royal'             => 'Paket Royal',
            'vip'               => 'Paket VIP',
            'complete'          => 'Paket Lengkap',
            'custom'            => 'Paket Custom',
        ];

        foreach ($productData as $slug => $idName) {
            $translations = $this->translateToAllLocales($idName);
            Category::updateOrCreate(
                ['slug' => $slug, 'type' => 'product'],
                ['name' => $idName, 'name_translations' => $translations]
            );
            $this->command->line("  <info>✓</info> $idName");
        }

        foreach ($packageData as $slug => $idName) {
            $translations = $this->translateToAllLocales($idName);
            Category::updateOrCreate(
                ['slug' => $slug, 'type' => 'package'],
                ['name' => $idName, 'name_translations' => $translations]
            );
            $this->command->line("  <info>✓</info> $idName");
        }

        $this->command->info('--- Category Seeding Complete ---');
    }
}
