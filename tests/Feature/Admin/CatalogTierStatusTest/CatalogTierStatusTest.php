<?php

use App\Filament\Admin\Resources\CatalogResource\CatalogResource;
use App\Filament\Admin\Resources\CatalogResource\Pages\ListCatalogs\ListCatalogs;
use App\Livewire\Admin\CatalogTables\PackageTable;
use App\Models\Admin\Admin;
use App\Models\Catalog\Catalog;
use App\Models\Category\Category;
use App\Models\Package\Package;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * `statusHint()` menandai paket yang identitasnya bukan salah satu dari lima
 * tier resmi. Pesan "Di luar daftar paket standar" hanya berguna kalau jarang
 * muncul.
 *
 * Bug yang diuji di sini pernah muncul sebagai "sering" -- SERATUS PERCENT dari
 * 500 paket kena label merah, padahal semuanya paket resmi.
 *
 * Akar masalahnya Comparisons yang salah bahasa:
 *
 *   - Yang dibaca : `name_translations['en']` -> "Standard Flower Decoration Package"
 *   - Yang dibandingkan: `display` -> "Paket Standar"
 *
 * Dua-duanya benar, tapi tidak akan pernah sama. Aucun error dilempar, kolom
 * tetap tampil, jadi yang memperhatikan hanya admin yang membacanya.
 *
 * Karena itu tes ini tidak sekadar "tidak error". Salah baca string di sini
 * akan mengembalikan string kosong untuk paket di luar daftar, dan string
 * kosong itulah yang membuat label merah hilang dari 500 baris sekaligus.
 */
beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'admin']);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->admin = Admin::factory()->create();
    $this->admin->assignRole('super_admin');

    $this->catalog = Catalog::create([
        'name' => 'Katalog Uji Tier',
        'slug' => 'katalog-uji-tier',
        'category_id' => Category::create(['name' => 'Kategori Uji Tier'])->id,
        'price' => 1_000_000,
        'stock' => 1,
    ]);
});

/**
 * Buat paket dengan kunci tier bahasa Inggris, persis seperti yang ditulis
 * form katalog: `name` kolomnya Indonesia, `name_translations['en']`-nya
 * Inggris.
 */
function makeTierPackage(string $slug, string $tierEnglishName): Package
{
    $tier = collect(CatalogResource::tiers())->first(
        fn (array $candidate): bool => $candidate['name'] === $tierEnglishName
    );

    return Package::create([
        'name' => $tier['display'] ?? 'Paket Tanpa Tier',
        'slug' => $slug,
        'catalog_id' => test()->catalog->id,
        'price' => 1_000_000,
        'stock' => 5,
        'name_translations' => [CatalogResource::TRANSLATION_KEY_LOCALE => $tierEnglishName],
    ]);
}

test('paket dengan kunci tier resmi tidak diberi label di luar daftar', function (): void {
    $component = Livewire::actingAs($this->admin, 'admin')
        ->test(PackageTable::class)
        ->instance();

    foreach (CatalogResource::tiers() as $tier) {
        $package = makeTierPackage('uji-'.$tier['key'], $tier['name']);

        expect($component->statusHint($package, PackageTable::tierKeys()))
            ->toBe('', "Paket tier '{$tier['name']}' seharusnya tidak diberi label");
    }
});

test('paket di luar lima tier tetap diberi label peringatan', function (): void {
    $package = makeTierPackage('paket-ngawur', 'Paket Yang Tidak Ada Di Daftar Resmi');

    expect(PackageTable::tierKeys())
        ->not->toContain('Paket Yang Tidak Ada Di Daftar Resmi');

    $component = Livewire::actingAs($this->admin, 'admin')
        ->test(PackageTable::class)
        ->instance();

    expect($component->statusHint($package, PackageTable::tierKeys()))
        ->toBe(__('Di luar daftar paket standar'));
});

/**
 * Nama terisi tapi kuncinya kosong -> "Di luar daftar paket standar".
 *
 * Kasus ini nyata sekarang form paket adalah `TextInput`: admin mengetik nama
 * yang bukan salah satu dari lima tier resmi, `afterStateUpdated` mengosongkan
 * `name_translations`, dan baris itu harus ditandai.
 *
 * Dulu pesannya "Nama paket belum diisi" -- yang menyesatkan, karena namanya
 * jelas sudah diisi. Yang salah isinya, bukan kosongnya.
 */
test('paket berisi nama tanpa kunci tier diberi label di luar daftar', function (): void {
    $package = Package::create([
        'name' => 'Paket Tanpa Terjemahan',
        'slug' => 'paket-tanpa-terjemahan',
        'catalog_id' => $this->catalog->id,
        'price' => 1_000_000,
        'stock' => 5,
    ]);

    $component = Livewire::actingAs($this->admin, 'admin')
        ->test(PackageTable::class)
        ->instance();

    expect($component->statusHint($package, PackageTable::tierKeys()))
        ->toBe(__('Di luar daftar paket standar'));
});

/**
 * Hanya nama yang benar-benar KOSONG yang mendapat "belum diisi".
 *
 * Tanpa pemisahan ini, `statusHint()` tidak bisa membedakan "admin belum
 * sempat mengisi" dan "admin mengisi tapi isinya bukan tier resmi" -- dan yang
 * kedua adalah salah ketik yang harus diperbaiki, bukan field yang kosong.
 */
test('paket dengan nama kosong diberi label nama belum diisi', function (): void {
    $package = Package::create([
        'name' => '',
        'slug' => 'paket-kosong',
        'catalog_id' => $this->catalog->id,
        'price' => 1_000_000,
        'stock' => 5,
    ]);

    $component = Livewire::actingAs($this->admin, 'admin')
        ->test(PackageTable::class)
        ->instance();

    expect($component->statusHint($package, PackageTable::tierKeys()))
        ->toBe(__('Nama paket belum diisi'));
});

/**
 * Kunci pengaman terhadap regresi yang lebih halus: daftar yang dibandingkan
 * harus tetap berisi nama INGGRIS. Kalau ada yang "memperbaiki" `tierKeys()`
 * dengan mengembalikan `display` lagi, halaman tetap hijau -- semua label
 * merah hilang, dan tidak ada satu pun yang lebih tahu.
 */
test('daftar pembanding berisi nama inggris, bukan display indonesia', function (): void {
    expect(PackageTable::tierKeys())
        ->toBe(array_column(CatalogResource::tiers(), 'name'))
        ->each->toBeString()
        ->and(PackageTable::tierKeys())
        ->toContain('Standard Flower Decoration Package')
        ->not->toContain('Paket Standar');

    // YangJudul form tetap bahasa Indonesia, karena itulah isinya kolom
    // `packages.name`.
    expect(PackageTable::expectedTiers())
        ->toContain('Paket Standar')
        ->not->toContain('Standard Flower Decoration Package');
});

test('halaman daftar katalog tidak lagi menandai semua paket sebagai di luar daftar', function (): void {
    makeTierPackage('halaman-standar', 'Standard Flower Decoration Package');
    makeTierPackage('halaman-penuh', 'Full Flower Decoration Package');

    $html = actingAs($this->admin, 'admin')
        ->get(ListCatalogs::getUrl())
        ->assertSuccessful()
        ->getContent();

    expect($html)->not->toContain(__('Di luar daftar paket standar'));
});