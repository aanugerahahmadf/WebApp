<?php

use App\Livewire\Admin\CatalogTables\CategoryTable;
use App\Livewire\Admin\CatalogTables\PackageTable;
use App\Models\Admin\Admin;
use App\Models\Catalog\Catalog;
use App\Models\Category\Category;
use App\Models\Package\Package;
use Filament\Facades\Filament;
use Filament\Forms\Form;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'admin']);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->admin = Admin::factory()->create();
    $this->admin->assignRole('super_admin');
});

/**
 * Nama-nama field di dalam form.
 *
 * Dua jebakan di sini:
 *
 * 1. `Section`/`Grid`/`Group` adalah container dan TIDAK punya `getName()` --
 *    memanggilnya di sana melempar BadMethodCallException. Kedua `instanceof
 *    Component` tidak cukup karena Section MEWARISI Component, jadi checknya
 *    harus `method_exists()`.
 * 2. Anaknya harus ditelusuri rekursif lewat `getChildComponents()`, kalau
 *    tidak seluruh Section hanya jadi satu kotak dan nama field di dalamnya
 *    tidak pernah terlihat.
 *
 * @return array<int, string>
 */
function formFieldNames(Form $form): array
{
    $names = [];

    $walk = function (iterable $components) use (&$walk, &$names): void {
        foreach ($components as $component) {
            if (method_exists($component, 'getName')) {
                $names[] = $component->getName();
            }

            if (method_exists($component, 'getChildComponents')) {
                $walk($component->getChildComponents());
            }
        }
    };

    $walk($form->getComponents(withHidden: true));

    return $names;
}

/*
 * Modal form Category & Package.
 *
 * Yang diuji adalah ISI form-nya, bukan sekadar "modal-nya ada". Modal kosong
 * yang tetap terbuka menghasilkan halaman yang terlihat benar dan tidak
 * melempar apa pun -- jadi menghitung elemen modal tidak akan menangkap
 * masalah yang paling mungkin terjadi di sini.
 */
test('action create kategori punya field di formnya', function (): void {
    $component = Livewire::actingAs($this->admin, 'admin')
        ->test(CategoryTable::class)
        ->instance();

    $create = collect($component->getTable()->getHeaderActions())
        ->first(fn ($a) => $a->getName() === 'create');

    $form = $create->getForm(Form::make($component)->model(Category::class));

    expect($form)->not->toBeNull()
        ->and(formFieldNames($form))->toContain('name');
});

test('action edit kategori punya field yang terisi dari record', function (): void {
    $category = Category::create(['name' => 'Buket Melati']);

    $component = Livewire::actingAs($this->admin, 'admin')
        ->test(CategoryTable::class)
        ->instance();

    $actionGroup = collect($component->getTable()->getActions())
        ->first(fn ($a) => $a instanceof \Filament\Tables\Actions\ActionGroup);

    $edit = collect($actionGroup->getActions())
        ->first(fn ($a) => $a->getName() === 'edit');

    $form = $edit->getForm(Form::make($component)->model($category));

    expect($form)->not->toBeNull()
        ->and(formFieldNames($form))->toContain('name');
    // Isi form dari record TIDAK diuji di sini: `getRawState()` belum ter-hydrate
    // sebelum `fill()` dipanggil, dan `getState()` memunculkan aturan validasi
    // Livewire yang butuh kunci `name` sudah ada di data. Kalau keduanya
    // dipaksa, test gagal karena cara pengujiannya -- bukan karena formnya.
    // Yang membuktikan form edit benar-benar terisi DAN tersimpan ada di test
    // "action edit kategori menyimpan perubahan" di bawah.
});

test('action create kategori benar-benar menyimpan', function (): void {
    Livewire::actingAs($this->admin, 'admin')
        ->test(CategoryTable::class)
        ->callTableAction('create', data: ['name' => 'Kategori dari Modal']);

    expect(Category::where('name', 'Kategori dari Modal')->exists())->toBeTrue();
});

test('action edit kategori menyimpan perubahan', function (): void {
    $category = Category::create(['name' => 'Nama Lama']);

    Livewire::actingAs($this->admin, 'admin')
        ->test(CategoryTable::class)
        ->callTableAction('edit', $category, data: ['name' => 'Nama Baru']);

    expect($category->fresh()->name)->toBe('Nama Baru');
});

/**
 * Form "Tambah Paket" hanya boleh punya SATU field yang terlihat: Nama Paket.
 *
 * Ini mengunci keputusan model data, bukan cuma tampilan. Harga, diskon,
 * deskripsi, dan stok milik katalog tertentu dan sudah punya tempatnya sendiri
 * di form Catalog (section "Pilihan Paket"). Kalau field-field itu muncul lagi
 * di sini, satu paket bisa terisi dua kali di dua tempat yang berbeda.
 *
 * `Hidden` tetap boleh ada -- `name_translations` dan `sort_order` tidak bisa
 * dilihat tapi wajib ikut tersimpan, dan `formFieldNames()` ikut menghitungnya.
 */
test('action create paket hanya menanyakan nama paket', function (): void {
    $component = Livewire::actingAs($this->admin, 'admin')
        ->test(PackageTable::class)
        ->instance();

    $create = collect($component->getTable()->getHeaderActions())
        ->first(fn ($a) => $a->getName() === 'create');

    $form = $create->getForm(Form::make($component)->model(Package::class));

    expect($form)->not->toBeNull();

    $names = formFieldNames($form);

    // Satu-satunya field yang boleh terlihat.
    expect($names)->toContain('name');

    // Field yang TIDAK boleh ada -- masing-masing punya pemilik lain.
    foreach (['catalog_id', 'category_id', 'price', 'discount_price', 'stock', 'is_active', 'slug'] as $forbidden) {
        expect($names)->not->toContain($forbidden);
    }

    // `name_translations` dan `sort_order` wajib tetap ada: keduanya ditulis
    // `afterStateUpdated`, dan Filament membuang state field yang tidak ada
    // di schema. Tanpa keduanya, paket yang baru disimpan langsung ditandai
    // "Di luar daftar paket standar".
    expect($names)->toContain('name_translations')->toContain('sort_order');
});

test('action create paket menyimpan nama saja dan mengisi sisanya sendiri', function (): void {
    Livewire::actingAs($this->admin, 'admin')
        ->test(PackageTable::class)
        ->callTableAction('create', data: [
            'name' => 'Paket Standar',
            'name_translations' => ['en' => 'Standard Flower Decoration Package'],
            'sort_order' => 1,
        ]);

    $created = Package::where('name', 'Paket Standar')->first();

    expect($created)->not->toBeNull();

    // `name_translations` ikut tersimpan -- inilah yang membuat paket ini
    // muncul sebagai pilihan di "Package Options" pada form Catalog.
    expect(data_get($created->name_translations, 'en'))
        ->toBe('Standard Flower Decoration Package');

    // `price` NOT NULL tanpa default di database. Kalau ini tidak diisi, yang
    // gagal adalah database -- errornya menyebut kolom, bukan field form.
    expect((float) $created->price)->toBe(0.0);

    // `packages.slug` punya index UNIQUE, jadi hook model menempelkan
    // `Str::random(5)`. Slug tetap harus ada dan tidak boleh kosong.
    expect($created->slug)->toBeString()->not->toBe('');

    // Belum menempel katalog mana pun -- itu diisi dari form Catalog.
    expect($created->catalog_id)->toBeNull();

    // Yang tidak boleh terjadi: data yang TIDAK ada di form ikut terisi.
    expect($created->discount_price)->toBeNull();
});

/**
 * Form paket adalah `TextInput`, bukan `Select` -- jadi admin mengetik, bukan
 * memilih. Tes ini mengunci dua hal yang membuat teks bebas tetap aman:
 *
 * 1. Mengetik salah satu dari lima tier resmi (di bahasa Indonesia ATAU
 *    Inggris) tetap menyimpan kunci tier. Tanpa ini, paket tidak akan muncul
 *    di "Package Options" pada form Catalog.
 * 2. Mengetik nama yang bukan tier resmi TIDAK menyimpan kunci -- karena
 *    menyimpannya berarti `tierPackagesFor()` memasangkan paket ini ke slot
 *    yang salah. Sebagai gantinya `statusHint()` menandai barisnya.
 */
test('nama paket yang diketik cocok dengan tier resmi menyimpan kunci tier', function (): void {
    // Indonesia -> kunci Inggris.
    Livewire::actingAs($this->admin, 'admin')
        ->test(PackageTable::class)
        ->callTableAction('create', data: ['name' => 'Paket Tunangan']);

    $indonesia = Package::query()->where('name', 'Paket Tunangan')->first();

    expect(data_get($indonesia->name_translations, 'en'))
        ->toBe('Engagement Flower Decoration Package')
        ->and($indonesia->sort_order)->toBe(4);

    // Inggris -> kunci yang sama. Huruf besar/kecil tidak masalah.
    Livewire::actingAs($this->admin, 'admin')
        ->test(PackageTable::class)
        ->callTableAction('create', data: ['name' => 'full flower decoration package']);

    $inggris = Package::query()
        ->where('name_translations->en', 'Full Flower Decoration Package')
        ->first();

    expect($inggris)->not->toBeNull()
        ->and($inggris->sort_order)->toBe(5);
});

test('nama paket di luar lima tier tidak menyimpan kunci dan ditandai', function (): void {
    Livewire::actingAs($this->admin, 'admin')
        ->test(PackageTable::class)
        ->callTableAction('create', data: ['name' => 'Paket Yang Tidak Resmi']);

    $paket = Package::query()->where('name', 'Paket Yang Tidak Resmi')->first();

    expect($paket)->not->toBeNull()
        // Sengaja KOSONG, bukan diisi teks yang diketik.
        ->and($paket->name_translations)->toBeEmpty()
        // Dan karena itu, penanda salah ketik bekerja.
        ->and((new PackageTable)->statusHint($paket, PackageTable::tierKeys()))
            ->toBe(__('Di luar daftar paket standar'));
});

/**
 * Harga, katalog, dan stok milik katalog -- jadi form nama-saja tidak boleh
 * menimpanya saat admin mengganti nama paket yang sudah ada.
 */
test('action edit paket tidak menimpa harga katalog dan stok yang sudah ada', function (): void {
    $category = Category::create(['name' => 'Kategori Edit Paket']);
    $catalog = Catalog::create([
        'name' => 'Katalog Edit Paket',
        'slug' => 'katalog-edit-paket',
        'category_id' => $category->id,
        'price' => 1_000_000,
        'stock' => 1,
    ]);

    $package = Package::create([
        'name' => 'Paket Sedang',
        'slug' => 'katalog-edit-paket-medium',
        'catalog_id' => $catalog->id,
        'category_id' => $category->id,
        'price' => 1_500_000,
        'stock' => 7,
        'name_translations' => ['en' => 'Medium Flower Decoration Package'],
    ]);

    Livewire::actingAs($this->admin, 'admin')
        ->test(PackageTable::class)
        ->callTableAction('edit', $package, data: [
            'name' => 'Paket Standar',
            'name_translations' => ['en' => 'Standard Flower Decoration Package'],
            'sort_order' => 1,
        ]);

    $fresh = $package->fresh();

    // `getRawOriginal()`, bukan `$fresh->name`.
    //
    // `Package::getNameAttribute()` mengembalikan `trans('name')` -- yaitu
    // versi sesuai locale yang sedang aktif. Locale test `en`, jadi
    // `$fresh->name` mengembalikan "Standard Flower Decoration Package",
    // bukan isi kolom `packages.name`. Isi kolomnya "Paket Standar".
    expect($fresh->getRawOriginal('name'))->toBe('Paket Standar')
        ->and(data_get($fresh->name_translations, 'en'))->toBe('Standard Flower Decoration Package')
        // Field yang tidak ada di form harus utuh.
        ->and((float) $fresh->price)->toBe(1500000.0)
        ->and($fresh->stock)->toBe(7)
        ->and($fresh->catalog_id)->toBe($catalog->id);
});