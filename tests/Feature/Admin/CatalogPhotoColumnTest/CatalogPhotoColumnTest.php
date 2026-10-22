<?php

namespace Tests\Feature\Admin\CatalogPhotoColumnTest;

use App\Filament\Admin\Resources\CatalogResource\Pages\ListCatalogs\ListCatalogs;
use App\Models\Admin\Admin;
use App\Models\Catalog\Catalog;
use App\Models\Category\Category;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Kolom FOTO di tabel katalog panel admin.
 *
 * Kolom ini memakai `ViewColumn` + view sendiri
 * (`Admin.resources.catalog.photos-grid-column`), bukan `ImageColumn` bawaan
 * Filament: kolom bawaan merender SEMUA foto dalam satu collection sebagai
 * BARIS horizontal, padahal sel kolom cuma ~56px -- jadi lima fotonya meluber
 * keluar sel dan memunculkan scrollbar horizontal.
 *
 * State kolom dibaca dari accessor `Catalog::gallery_urls`
 * (`getGalleryUrlsAttribute()`), yang mengembalikan array berisi
 * ['id', 'url', 'thumb', 'collection', 'order'].
 *
 * JEB AKAN: nama kolomnya pernah `catalog_image` -- itu NAMA COLLECTION
 * media, bukan atribut model. `getAttribute('catalog_image')` mengembalikan
 * null, blade menerima array kosong, dan selnya tampil KOSONG tanpa error
 * sama sekali. Tidak ada exception, tidak ada stack trace: hanya kotak kosong
 * di setiap baris.
 */
class CatalogPhotoColumnTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected Catalog $catalog;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'admin']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->admin = Admin::factory()->create();
        $this->admin->assignRole('super_admin');

        $this->catalog = Catalog::create([
            'name' => 'Katalog Foto Uji',
            'slug' => 'katalog-foto-uji',
            'category_id' => Category::create(['name' => 'Kategori Foto Uji'])->id,
            'price' => 1_000_000,
            'stock' => 5,
        ]);
    }

    public function test_kolom_foto_menampilkan_thumbnail(): void
    {
        $this->catalog->addMedia(public_path('images/catalog/catalog-1.png'))
            ->preservingOriginal()
            ->toMediaCollection('catalog_image', 'public');

        $this->catalog->refresh();

        $html = Livewire::actingAs($this->admin, 'admin')
            ->test(ListCatalogs::class)
            ->html();

        // Grid 2x2 milik view kolom foto.
        $this->assertStringContainsString('grid w-14', $html);

        // Ada <img> di dalam grid itu -- inilah yang hilang saat nama
        // kolomnya salah.
        $this->assertMatchesRegularExpression(
            '/<div class="grid w-14[^"]*">\s*(?:<!--.*?-->\s*)*<img\s+src="[^"]+"/s',
            $html,
            'Sel foto katalog harus berisi <img> dari accessor gallery_urls.',
        );
    }

    public function test_nama_kolom_foto_adalah_gallery_urls(): void
    {
        // Pengaman: kalau kolomnya diubah lagi ke `catalog_image` atau
        // `photos`, test ini yang menangkapnya.
        $column = \App\Filament\Admin\Resources\CatalogResource\CatalogResource::table(
            new \Filament\Tables\Table(new class extends \Filament\Resources\Pages\ManageRecords
            {
                public function getTable(): \Filament\Tables\Table
                {
                    return \Filament\Tables\Table::make($this);
                }
            }),
        );

        $viewColumn = collect($column->getColumns())
            ->first(fn ($column): bool => $column instanceof \Filament\Tables\Columns\ViewColumn);

        $this->assertNotNull($viewColumn, 'Kolom ViewColumn untuk foto harus ada.');
        $this->assertSame('gallery_urls', $viewColumn->getName());
    }

    /**
     * Satu katalog punya LIMA foto, dan kelimanya tampil di grid.
     *
     * Ini yang hilang saat blade-nya hanya mengambil empat potong
     * (`array_slice(..., 0, 4)`) -- selnya jadi terlihat seperti katalog yang
     * cuma punya empat foto.
     */
    public function test_grid_menampilkan_lima_foto(): void
    {
        foreach (range(1, 5) as $index) {
            $this->catalog->addMedia(public_path("images/catalog/catalog-{$index}.png"))
                ->preservingOriginal()
                ->toMediaCollection('catalog_image', 'public');
        }

        $this->catalog->refresh();

        $this->assertCount(5, $this->catalog->gallery_urls);

        $html = Livewire::actingAs($this->admin, 'admin')
            ->test(ListCatalogs::class)
            ->html();

        // Grid foto ada satu, dan DI DALAMNYA lima <img>.
        $this->assertSame(1, substr_count($html, 'grid w-14 grid-cols-2'), 'Grid foto harus ada tepat satu.');

        // `alt` diambil dari nama collection media, jadi satu foto = satu
        // `alt="catalog_image"`. Katalognya cuma satu, jadi lima foto = lima
        // alt.
        $this->assertSame(5, substr_count($html, 'alt="catalog_image"'), 'Lima foto harus dirender.');
    }
}
