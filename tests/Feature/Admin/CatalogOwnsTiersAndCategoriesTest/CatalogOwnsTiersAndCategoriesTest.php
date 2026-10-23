<?php

/*
 * Kategori & paket sekarang hidup di katalog, bukan di halaman sendiri.
 *
 * Yang dipindah:
 *
 *   - `PackageResource` dihapus. Kelima tier tiap katalog dikelola lewat tab
 *     "Pilihan Paket" (`TiersRelationManager`) di halaman katalog. Tidak ada
 *     lagi daftar global berisi 500 baris packages.
 *
 *   - `CategoryResource` dihapus. Semua kategori dikelola lewat tab
 *     "Kategori" (`CategoriesRelationManager`) di halaman katalog, dengan tombol
 *     "Pakai untuk Katalog Ini".
 *
 * Konsekuensi ke model, dan kenapa begitu:
 *
 *   - Form kategori sekarang hanya menanyakan NAMA. `slug` dan `type` keduanya
 *     NOT NULL di database, jadi `Category::booted()` mengisinya otomatis --
 *     kalau tidak, INSERT tanpa slug ditolak database.
 *
 *   - Slug hanya ditulis ulang saat nama berubah. `name_translations` berisi
 *     dua bahasa; kalau slug selalu dihitung dari accessor `name`, menyimpan
 *     kategori saat panel berbahasa Inggris akan mengganti `buket-bunga` jadi
 *     `flower-bouquet` tanpa disentuh admin, dan URL publik ikut patah.
 *
 *   - Deskripsi paket TIDAK diedit dari `TiersRelationManager`. Satu tempat
 *     saja yang boleh mengubahnya: lima field deskripsi di section "Pilihan
 *     Paket" pada form katalog. Dua editor untuk data yang sama berarti tidak
 *     ada yang tahu yang mana yang berlaku terakhir.
 */

use App\Filament\Admin\Resources\CatalogResource\CatalogResource;
use App\Filament\Admin\Resources\CatalogResource\Pages\EditCatalog\EditCatalog;
use App\Filament\Admin\Resources\CatalogResource\RelationManagers\CategoriesRelationManager;
use App\Filament\Admin\Resources\CatalogResource\RelationManagers\TiersRelationManager;
use App\Models\Catalog\Catalog;
use App\Models\Category\Category;
use App\Models\Package\Package;
use App\Models\Admin\Admin;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // `super_admin` juga dibuat di guard `web`: user di bawah adalah
    // `App\Models\User\User`, dan `assignRole()` mencari role di guard model itu
    // sendiri. Role versi `admin` milik tabel `admins` tetap dibuat juga, untuk
    // test yang memang butuh admin.
    seedRoles(adminRoles: ['super_admin'], webRoles: ['super_admin', 'customer']);

    // Guard `admin`, bukan `web`.
    //
    // Halaman di test ini adalah `EditCatalog` -- resource panel ADMIN, yang
    // berjalan di guard `admin` (tabel `admins`). `actingAs($user, 'web')`
    // tidak mengisi guard itu, jadi `canAccess()` menolak dan
    // `Livewire::test(EditCatalog::class)->instance()` mengembalikan null.
    //
    // Gejalanya bukan "403" tapi
    // `ComponentContainer::make(): Argument #1 ($livewire) must be of type
    // HasForms, null given` -- karena yang diuji sebenarnya adalah komponen yang
    // TIDAK PERNAH ter-mount.
    $this->admin = Admin::factory()->superAdmin()->create([
        'email_verified_at' => now(),
        'active_status'     => true,
    ]);

    test()->actingAs($this->admin, 'admin');

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->catalog = Catalog::factory()->create();
});

afterEach(function (): void {
    Filament::setCurrentPanel(null);
});

/**
 * Manager relasi kategori yang terpasang di katalog milik test.
 */
function categoriesManager(): object
{
    return Livewire::test(CategoriesRelationManager::class, [
        'ownerRecord' => test()->catalog,
        'pageClass' => EditCatalog::class,
    ]);
}

test('form katalog menanyakan kategori lewat select yang bisa dicari', function (): void {
    // Select-nya tetap ada di form katalog (itu arah "produk -> kategori"),
    // berpasangan dengan tab "Kategori" yang mengarah sebaliknya.
    $select = CatalogResource::form(Form::make(
        Livewire::test(EditCatalog::class, ['record' => $this->catalog->getRouteKey()])->instance()
    ))->getComponent('category_id');

    expect($select)->toBeInstanceOf(Select::class)
        ->and($select->getRelationshipName())->toBe('category')
        ->and($select->isSearchable())->toBeTrue()
        // `name` saja tidak cukup: admin sering ingat slug URL-nya.
        ->and($select->getSearchColumns())->toContain('name', 'slug')
        // Kategori bisa dibuat dari dalam form katalog, cukup dengan nama.
        ->and($select->getCreateOptionUsing())->not->toBeNull();
});

test('kategori bisa dibuat dari dalam form katalog', function (): void {
    $select = CatalogResource::form(Form::make(
        Livewire::test(EditCatalog::class, ['record' => $this->catalog->getRouteKey()])->instance()
    ))->getComponent('category_id');

    $id = ($select->getCreateOptionUsing())(['name' => 'Buket Baru dari Form']);

    expect($id)->toBeString()
        ->and(Category::find($id)->slug)->toBe('buket-baru-dari-form')
        ->and(Category::find($id)->type)->toBe(Category::DEFAULT_TYPE);
});

/*
 * BULLET FITUR PER TIER.
 *
 * "Bunga segar pilihan standar", "Backdrop pelaminan sederhana", "Lighting
 * standar", "Dekorasi meja resepsi", "Tim setup 2 orang" -- itu isi
 * `packages.features`, dan tampil sebagai daftar centang di halaman detail
 * katalog. Field editornya sempat dihapus dari form dengan alasan "sudah
 * tercakup deskripsi", padahal kolomnya tetap dibaca halaman Welcome, halaman
 * User, dan `CatalogHasTiers` untuk API: akibatnya isinya tidak bisa diedit
 * dari mana pun.
 */

test('form katalog punya bullet fitur untuk kelima tier', function (): void {
    $form = CatalogResource::form(Form::make(
        Livewire::test(EditCatalog::class, ['record' => $this->catalog->getRouteKey()])->instance()
    ));

    $fields = array_keys($form->getFlatFields(withHidden: true));

    foreach (array_column(CatalogResource::tiers(), 'key') as $key) {
        expect($fields)->toContain("tier_{$key}_features");
    }
});

test('bullet fitur yang sudah ada dimuat ke form', function (): void {
    $tier = CatalogResource::tiers()[0];

    Package::factory()->create([
        'catalog_id' => $this->catalog->getKey(),
        'name' => $tier['display'],
        'name_translations' => ['en' => $tier['name'], 'id' => $tier['display']],
        'features' => ['Bunga segar pilihan standar', 'Tim setup 2 orang'],
    ]);

    $state = CatalogResource::tierStateFromRecord($this->catalog->fresh());

    expect($state["tier_{$tier['key']}_features"])
        ->toBe(['Bunga segar pilihan standar', 'Tim setup 2 orang']);
});

test('bullet fitur tersimpan saat form katalog disimpan', function (): void {
    CatalogResource::saveTiers($this->catalog, [
        'tier_standard_price' => 13500000,
        'tier_standard_features' => [
            'Bunga segar pilihan standar',
            'Backdrop pelaminan sederhana',
            'Lighting standar',
            'Dekorasi meja resepsi',
            'Tim setup 2 orang',
        ],
    ]);

    $package = $this->catalog->fresh()->tiers()->first();

    expect($package->features)->toBe([
        'Bunga segar pilihan standar',
        'Backdrop pelaminan sederhana',
        'Lighting standar',
        'Dekorasi meja resepsi',
        'Tim setup 2 orang',
    ]);
});

test('bullet fitur bisa dikosongkan admin', function (): void {
    $tier = CatalogResource::tiers()[0];

    $package = Package::factory()->create([
        'catalog_id' => $this->catalog->getKey(),
        'name' => $tier['display'],
        'name_translations' => ['en' => $tier['name'], 'id' => $tier['display']],
        'features' => ['Bunga segar pilihan standar'],
        'price' => 13500000,
    ]);

    // Kolom fitur TIDAK memakai aturan `filled()` seperti deskripsi: mengosongkan
    // semua bullet adalah keputusan admin yang sah, dan tidak ada tempat lain
    // untuk mengeditnya.
    CatalogResource::saveTiers($this->catalog, [
        "tier_{$tier['key']}_price" => $package->price,
        "tier_{$tier['key']}_features" => [],
    ]);

    expect($package->fresh()->features)->toBe([]);
});

test('bullet fitur tier lain tidak terhapus saat satu tier disimpan', function (): void {
    // `saveTiers()` hanya menulis tier yang form-nya terisi harga/deskripsi.
    // Tier yang tidak tersentuh haruslah utuh -- termasuk bulletnya. Ini juga
    // alasan fitur dimuat di `tierStateFromRecord()`: kalau tidak, membuka lalu
    // menyimpan form akan menghapus semua bullet.
    $tier = CatalogResource::tiers()[0];

    $package = Package::factory()->create([
        'catalog_id' => $this->catalog->getKey(),
        'name' => $tier['display'],
        'name_translations' => ['en' => $tier['name'], 'id' => $tier['display']],
        'features' => ['Bunga segar pilihan standar'],
        'price' => 13500000,
    ]);

    CatalogResource::saveTiers($this->catalog, [
        'tier_full_price' => 25000000,
        'tier_full_features' => ['Bunga premium eksklusif'],
    ]);

    expect($package->fresh()->features)->toBe(['Bunga segar pilihan standar']);
});

test('normalisasi fitur menangani tag, teks, dan null', function (): void {
    // TagsInput mengirim array; opsi dari database datang sebagai array
    // asosiatif; teks yang ditempel datang sebagai string.
    expect(CatalogResource::normalizeTierFeatures([
        'Bunga segar pilihan standar',
        '   ',
        ['id' => 'Backdrop pelaminan sederhana'],
        ['en' => 'Tim setup 2 orang'],
    ]))->toBe(['Bunga segar pilihan standar', 'Backdrop pelaminan sederhana', 'Tim setup 2 orang'])
        ->and(CatalogResource::normalizeTierFeatures(
            "Bunga segar pilihan standar\nBackdrop pelaminan sederhana, Lighting standar"
        ))->toBe(['Bunga segar pilihan standar', 'Backdrop pelaminan sederhana', 'Lighting standar'])
        ->and(CatalogResource::normalizeTierFeatures(null))->toBe([])
        ->and(CatalogResource::normalizeTierFeatures([]))->toBe([])
        // Bentuk lama (`['decoration' => true]`) yang masih dibuat PackageFactory
        // dan seed lama: teksnya ada di key.
        ->and(CatalogResource::normalizeTierFeatures([
            'decoration' => true,
            'lighting' => false,
            'sound_system' => true,
        ]))->toBe(['decoration', 'sound_system']);
});

test('halaman katalog punya tab paket dan kategori', function (): void {
    expect(CatalogResource::getRelations())
        ->toContain(TiersRelationManager::class)
        ->toContain(CategoriesRelationManager::class);
});

test('halaman paket dan kategori tidak ada lagi', function (): void {
    // Dihapus supaya tidak ada dua tempat manage data yang sama. Yang tersisa
    // hanya eksportir, karena masih dipakai Pusat Unduhan Data dan tidak punya
    // halaman.
    expect(class_exists(\App\Filament\Admin\Resources\PackageResource\PackageResource::class))->toBeFalse()
        ->and(class_exists(\App\Filament\Admin\Resources\CategoryResource\CategoryResource::class))->toBeFalse()
        ->and(Route::has('filament.admin.resources.flowerdecorationspackagecatalog.index'))->toBeFalse()
        ->and(Route::has('filament.admin.resources.categories.index'))->toBeFalse();
});

test('form kategori di tab katalog hanya memiliki satu input penyimpanan: nama', function (): void {
    $manager = categoriesManager()->instance();

    $flat = $manager->form(Form::make($manager))->getFlatFields(withHidden: true);

    /*
     * Form ini hanya punya SATU field penyimpan: `name`. (Section
     * "Tipografi"/"Gaya Teks" lama sudah dihapus -- kustomisasi tampilan
     * dipindah ke panel kustomizer di bawah tabel,
     * `App\Livewire\Admin\PageCustomizer`.)
     */
    expect(array_keys($flat))->toBe(['name']);
});

test('kategori yang dibuat dari tab katalog menyimpan slug dan tipe otomatis', function (): void {
    categoriesManager()
        ->callTableAction('create', data: ['name' => 'Buket Bunga'])
        ->assertHasNoTableActionErrors();

    $category = Category::query()->where('name', 'Buket Bunga')->sole();

    expect($category->slug)->toBe('buket-bunga')
        ->and($category->type)->toBe(Category::DEFAULT_TYPE);
});

test('nama yang sama menghasilkan slug unik tanpa menimpa kategori lama', function (): void {
    $existing = Category::create(['name' => 'Buket Bunga']);

    $kedua = Category::create(['name' => 'Buket Bunga']);

    expect($kedua->slug)->toBe('buket-bunga-2')
        ->and($existing->fresh()->slug)->toBe('buket-bunga');

    expect(Category::create(['name' => 'Buket Bunga'])->slug)->toBe('buket-bunga-3');
});

test('slug dibersihkan dari simbol dan spasi berlebih', function (): void {
    expect(Category::create(['name' => 'Dekorasi  Dinding & Dinding!'])->slug)
        ->toBe('dekorasi-dinding-dinding');
});

test('menyimpan kategori tanpa mengubah nama tidak mengubah slug-nya', function (): void {
    // `name_translations` menyimpan dua bahasa. Kalau slug dihitung dari accessor
    // `name`, perubahan di sini akan menggantinya dengan versi bahasa Inggris
    // lalu URL publik ikut berubah.
    $category = Category::create(['name' => 'Buket Bunga']);
    $category->setTranslation('name', 'en', 'Flower Bouquet');
    $category->save();

    expect($category->fresh()->slug)->toBe('buket-bunga');

    $category->update(['description' => 'Deskripsi baru']);

    expect($category->fresh()->slug)->toBe('buket-bunga');
});

test('mengubah nama kategori memperbarui slug-nya', function (): void {
    $category = Category::create(['name' => 'Buket Bunga']);

    $category->update(['name' => 'Buket Storage']);

    expect($category->fresh()->slug)->toBe('buket-storage');
});

test('tab kategori menampilkan seluruh kategori, bukan cuma milik katalog ini', function (): void {
    // Kalau hanya menampilkan kategori milik katalog ini, tab ini tidak bisa
    // dipakai membuat kategori baru -- persis saat admin butuh.
    $memakai = Category::create(['name' => 'Kategori Dipakai']);
    $lain = Category::create(['name' => 'Kategori Lain']);

    test()->catalog->forceFill(['category_id' => $memakai->getKey()])->save();

    categoriesManager()
        ->assertCanSeeTableRecords([$memakai, $lain]);
});

test('kategori bisa dipasang ke katalog ini dari tab kategori', function (): void {
    $category = Category::create(['name' => 'Buket Bunga']);

    expect(test()->catalog->category_id)->not->toBe($category->getKey());

    categoriesManager()->callTableAction('pakai', $category);

    expect(test()->catalog->fresh()->category_id)->toBe($category->getKey());
});

test('kategori yang sudah dipakai tidak bisa dihapus dari tab katalog', function (): void {
    $category = Category::create(['name' => 'Buket Bunga']);

    test()->catalog->forceFill(['category_id' => $category->getKey()])->save();

    categoriesManager()->callTableAction('delete', $category);

    expect(Category::whereKey($category->getKey())->exists())->toBeTrue();
});

test('kategori tanpa produk bisa dihapus dari tab katalog', function (): void {
    $category = Category::create(['name' => 'Kategori Kosong']);

    categoriesManager()->callTableAction('delete', $category);

    expect(Category::whereKey($category->getKey())->exists())->toBeFalse();
});

test('tab paket tidak punya editor deskripsi', function (): void {
    // Deskripsi tier hanya boleh diubah dari section "Pilihan Paket" di form
    // katalog. Dua editor untuk kolom yang sama berarti tidak ada yang tahu
    // yang mana yang berlaku terakhir -- jadi kolom deskripsi dihapus dari
    // manager ini, sama seperti yang dilakukan halaman `PackageResource` yang
    // sekarang sudah dihapus.
    //
    // Schema dibaca langsung, bukan lewat `mountTableAction()`: harness Filament
    // 3.3.52 memanggil `getInfolist('form')` lebih dulu dan salah memilih
    // Infolist untuk method `form(Form $form)`.
    //
    // `features` sengaja tidak ikut dijadiankan: fitur tier juga hanya boleh
    // diubah dari form katalog (`tier_<key>_features`), jadi kolomnya tidak
    // ada di tab ini.
    $manager = Livewire::test(TiersRelationManager::class, [
        'ownerRecord' => $this->catalog,
        'pageClass' => EditCatalog::class,
    ])->instance();

    $fields = array_keys(
        $manager->form(Form::make($manager))->getFlatFields(withHidden: true)
    );

    expect($fields)->toContain('name', 'price', 'stock', 'slug', 'sort_order')
        ->and($fields)->not->toContain('description');
});
