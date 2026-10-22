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

        // Hanya kategori produk dekorasi.
        //
        // Level paket (Hemat / Standar / Premium / Mewah / Eksekutif / Deluxe /
        // Royal / VIP / Lengkap / Custom) SENGAJA TIDAK ada di sini.
        //
        // DULU sepuluh kategori "Paket *" ikut diseed. Itu keliru karena paket
        // bukan produk -- paket adalah TIER milik sebuah katalog (lihat
        // App\Filament\Admin\Resources\CatalogResource\CatalogResource::tiers()).
        // Akibatnya di seeder lama kategori paket ikut tampil sebagai pilihan
        // kategori di form katalog, padahal isinya cuma "Paket Standar",
        // "Paket Mewah", dst -- bukan jenis dekorasi sama sekali.
        //
        // Verifikasi penghapusan: seluruh 10 kategori tersebut tercatat 0 katalog,
        // jadi tidak ada katalog yang kehilangan kategori ketika dihapus.
        $catalogData = [
            'buket-bunga'        => 'Buket Bunga',
            'dekorasi-meja'      => 'Dekorasi Meja',
            'ornamen-dinding'    => 'Ornamen Dinding',
            'backdrop-pelaminan' => 'Backdrop Pelaminan',
            'hiasan-gaun'        => 'Hiasan Gaun',
            'seserahan'          => 'Seserahan',
            'kain-dekorasi'      => 'Kain Dekorasi',
            'lighting'           => 'Lighting & Lampu',
            'aksesoris'          => 'Aksesoris Pernikahan',
            'tanaman-hias'       => 'Tanaman Hias',
        ];

        foreach ($catalogData as $slug => $idName) {
            // Direct translations without calling translation service
            $translations = [
                'id' => $idName,
                'en' => $this->getEnglishTranslation($idName),
            ];
            Category::updateOrCreate(
                ['slug' => $slug, 'type' => 'catalog'],
                ['name' => $idName, 'name_translations' => $translations]
            );
            $this->command->line("  <info>✓</info> {$idName}");
        }

        // Hapus kategori paket yang dibuat oleh versi seeder lama.
        //
        // Aman karena kategori paket tidak pernah dipakai katalog mana pun
        // (dicek 0 katalog sebelum dihapus). Dibatasi pada slug yang dikenal,
        // jadi kategori user-made yang kebetulan mirip tidak ikut terhapus.
        //
        // Query builder dipakai langsung: Category tidak memakai SoftDeletes,
        // jadi `withTrashed()` tidak tersedia dan tidak diperlukan.
        $obsoletePackageCategories = [
            'hemat', 'standar', 'premium', 'mewah', 'eksekutif',
            'deluxe', 'royal', 'vip', 'complete', 'custom',
        ];

        $deleted = Category::where('type', 'catalog')
            ->whereIn('slug', $obsoletePackageCategories)
            ->delete();

        if ($deleted > 0) {
            $this->command->line("  <info>✓</info> {$deleted} kategori paket lama dihapus");
        }

        $this->command->info('--- Category Seeding Complete ---');
    }

    /**
     * Get English translation for common category names.
     */
    private function getEnglishTranslation(string $indonesian): string
    {
        $map = [
            'Buket Bunga' => 'Flower Bouquet',
            'Dekorasi Meja' => 'Table Decoration',
            'Ornamen Dinding' => 'Wall Ornament',
            'Backdrop Pelaminan' => 'Wedding Backdrop',
            'Hiasan Gaun' => 'Dress Ornament',
            'Seserahan' => 'Seserahan',
            'Kain Dekorasi' => 'Decorative Fabric',
            'Lighting & Lampu' => 'Lighting & Lamps',
            'Aksesoris Pernikahan' => 'Wedding Accessories',
            'Tanaman Hias' => 'Ornamental Plants',
        ];

        return $map[$indonesian] ?? $indonesian;
    }
}