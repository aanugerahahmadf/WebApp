<?php

namespace App\Filament\Admin\Resources\CatalogResource;

use App\Filament\Admin\Exports\CatalogExporter\CatalogExporter;
use App\Filament\Admin\Resources\CatalogResource\Pages;
use App\Filament\Admin\Resources\CatalogResource\RelationManagers;
use App\Models\Catalog\Catalog;
use App\Support\AppIcon\AppIcon;
use App\Models\Category\Category;
use App\Models\Package\Package;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use App\Support\AdminAccess\AdminAccess;
use Illuminate\Database\Eloquent\Model;

class CatalogResource extends Resource
{
    /**
     * Gate akses per role.
     *
     * `canViewAny()` dipakai Filament untuk dua hal sekaligus: menyembunyikan
     * entri navigasi dan memblokir URL yang diketik langsung. Jadi daftar di
     * sidebar dan yang bisa dibuka selalu sama -- tidak bisa berbeda.
     *
     * Permission write sengaja dipisah dari permission view: Data Lens dan
     * Data Analyst boleh membaca, tapi tidak boleh create / edit / delete.
     *
     * @see AdminAccess
     */
    public static function canViewAny(): bool
    {
        return AdminAccess::allows('view_catalog');
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return AdminAccess::allows('manage_catalog');
    }

    public static function canEdit(Model $record): bool
    {
        return AdminAccess::allows('manage_catalog');
    }

    public static function canDelete(Model $record): bool
    {
        return AdminAccess::allows('manage_catalog');
    }

    public static function canDeleteAny(): bool
    {
        return AdminAccess::allows('manage_catalog');
    }

    /**
     * Locale yang menyimpan KUNCI tier di `packages.name_translations`.
     *
     * Bukan `id`, karena yang disimpan di sana bukan teks untuk ditampilkan --
     * melainkan nama Inggris dari `tiers()`, yang jadi identitas stabil tier
     * di semua query dan lookup. Kolom `name` sendiri yang menyimpan teks
     * Indonesia; accessor `Package::getNameAttribute()` membacanya lewat
     * `name_translations['id']`.
     */
    public const TRANSLATION_KEY_LOCALE = 'en';

    protected static ?string $model = Catalog::class;

    protected static ?string $slug = 'catalogs';

    protected static ?string $navigationIcon = AppIcon::CATALOG;

    protected static ?int $navigationSort = 102;

    public static function getModelLabel(): string
    {
        return __('Bunga');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Bunga');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Data Master');
    }

    public static function getNavigationLabel(): string
    {
        return __('Daftar Bunga');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug', 'description'];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Informasi Catalog'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('Nama Catalog'))
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', str($state)->slug())),
                        Forms\Components\TextInput::make('slug')
                            ->label(__('Slug'))
                            ->required()
                            ->unique(ignorable: fn (?Catalog $record) => $record)
                            ->maxLength(255),
                        Forms\Components\Select::make('category_id')
                            ->label(__('Kategori'))
                            ->helperText(__('Ketik untuk mencari. Kategori baru bisa dibuat langsung dari sini lewat tombol +.'))
                            ->relationship('category', 'name')
                            /*
                             * Pencarian memakai `name` DAN `slug`, bukan
                             * `name` saja: admin sering ingat slug URL
                             * ("buket-bunga") saat sedang mengetik label.
                             */
                            ->searchable(['name', 'slug'])
                            ->preload()
                            /*
                             * `forCatalogs()` SENGAJA TIDAK dipakai. Satu kategori
                             * dipakai bersama oleh `catalogs` dan `packages` --
                             * di DB, `packages.category_id` menunjuk id yang sama
                             * dengan `catalogs.category_id` -- jadi memfilter
                             * per tipe hanya menyembunyikan kategori yang
                             * sebenarnya valid. `Category::booted()` juga mengisi
                             * `type` otomatis, jadi filter itu tidak lagi berarti
                             * apa pun.
                             */
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label(__('Nama Kategori'))
                                    ->placeholder(__('Contoh: Buket Bunga'))
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->createOptionUsing(function (array $data): string {
                                $category = Category::create([
                                    'name' => $data['name'],
                                ]);

                                return (string) $category->getKey();
                            })
                            ->required(),
                        Forms\Components\TextInput::make('price')
                            ->label(__('Harga'))
                            ->numeric()
                            ->prefix('Rp')
                            ->required(),
                        Forms\Components\TextInput::make('discount_price')
                            ->label(__('Harga Diskon'))
                            ->numeric()
                            ->prefix('Rp'),
                        Forms\Components\TextInput::make('stock')
                            ->label(__('Stok'))
                            ->numeric()
                            ->default(0)
                            ->required(),
                        Forms\Components\Toggle::make('is_active')
                            ->label(__('Aktif'))
                            ->default(true),
                        Forms\Components\RichEditor::make('description')
                            ->label(__('Deskripsi'))
                            ->columnSpanFull(),
                        Forms\Components\SpatieMediaLibraryFileUpload::make('image')
                            ->label(__('Foto Catalog'))
                            ->collection('catalog_image')
                            ->image()
                            ->multiple()
                            ->reorderable()
                            // The responsive WebP ladder (thumb 320 / sm 640 / md 1024 /
                            // lg 1600 / xl 2048) is enabled on the model collection; the
                            // editor just keeps the master file small enough to be worth it.
                            ->imageEditor()
                            ->imageEditorViewportWidth(1200)
                            ->imageEditorViewportHeight(800)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->formatStateUsing(fn (mixed $state): mixed => static::sanitizeSpatieUploadState($state))
                            ->afterStateHydrated(
                                fn (Forms\Components\SpatieMediaLibraryFileUpload $component, mixed $state): mixed => $component->state(static::sanitizeSpatieUploadState($state))
                            )
                            ->getUploadedFileUsing(
                                fn (Forms\Components\SpatieMediaLibraryFileUpload $component, mixed $file): ?array => static::safeUploadedMediaFileData($component, $file)
                            )
                            ->maxSize(8192)
                            ->helperText(__('Maks 8 MB per foto. Semua foto katalog diletakkan di sini, bukan di paket.'))
                            ->columnSpanFull(),

                    ])->columns(2),

                // Lima pilihan paket, satu field per tier.
                //
                // Kenapa field terpisah, bukan satu Select: tiap tier punya
                // harga dan deskripsi sendiri, jadi admin perlu bisa mengisi
                // kelimanya sekaligus dari satu halaman katalog. Satu Select
                // dengan lima opsi hanya akan menyimpan satu tier.
                //
                // Kolom yang disimpan tetap `packages.id` milik katalog ini,
                // bukan kolom baru di tabel catalogs: relasi tiers() sudah
                // menyatukan keduanya, dan setiap field menulis lewat
                // firstOrCreate() supaya tier yang belum ada dibuat otomatis
                // dengan nama, urutan, dan slug yang sudah benar.
                Forms\Components\Section::make(__('Pilihan Paket'))
                    ->description(__('Lima paket di bawah ini muncul sebagai pilihan di halaman detail katalog, tepat di atas tombol Order now / Add to Cart. Isi harga dan deskripsinya; bullet Fitur Paket tampil sebagai daftar centang di halaman pelanggan.'))
                    ->icon('heroicon-s-gift')
                    ->collapsible()
                    ->collapsed()
                    ->schema(
                        /*
                         * `array_merge(...)`, bukan `array_map` telanjang.
                         *
                         * `tierField()` mengembalikan `[Group]` -- satu array
                         * berisi satu komponen. `array_map` di atasnya
                         * menghasilkan array bersarang: [[Group], [Group],
                         * ...]. `schema()` lalu menerima array sebagai
                         * elemen, bukan Komponen, dan Filament v4 memverifikasi
                         * tipenya:
                         *
                         *   TypeError: Filament\Forms\ComponentContainer::getComponents():
                         *   Argument #1 ($component) must be of type
                         *   Filament\Forms\Components\Component, array given
                         *
                         * `array_merge(...$mapped)` meratakan satu tingkat,
                         * jadi yang masuk ke `schema()` benar-benar
                         * [Group, Group, ...].
                         */
                        array_merge(
                            ...array_map(
                                fn (array $tier): array => static::tierField($tier),
                                static::tiers()
                            )
                        )
                    )
                    ->columns(2),
            ])
            /*
             * Hook mutateFormData* TIDAK BOLEH dipanggil di sini.
             *
             * Dua hal memindahkannya ke halaman (EditCatalog / CreateCatalog):
             *
             * 1. Filament v4 tidak punya method itu di Filament\Forms\Form --
             *    memanggilnya di dalam form() berakhir dengan
             *    "BadMethodCallException: Method Filament\Forms\Form::mutateFormDataBeforeFill
             *    does not exist" dan halaman Edit jadi 500.
             *    Yang masih punya hook itu adalah PAGINYA: EditRecord punya
             *    mutateFormDataBeforeFill() dan mutateFormDataBeforeSave(),
             *    CreateRecord punya mutateFormDataBeforeCreate().
             *
             * 2. Isinya memang butuh akses ke record, dan record hanya ada
             *    di halaman -- bukan di form().
             *
             * Lihat EditCatalog::mutateFormDataBeforeFill() dan
             * CreateCatalog::mutateFormDataBeforeCreate() untuk versi yang
             * sudah dipindah.
             */;
    }

    /**
     /**
     * Rapi nilai TagsInput fitur jadi daftar teks yang siap disimpan.
     *
     * Bentuk yang masuk bisa apa saja; semua berakhir jadi daftar teks:
     * - Daftar teks biasa, yang disimpan di `packages.features`.
     * - Opsi dari database: array asosiatif `[id => ...]` / `[en => ...]`.
     * - Teks yang ditempel: string tunggal, dipecah per baris baru atau koma.
     * - Bentuk lama (`decoration` => true): teksnya ada di key.
     *
     * Entri kosong dibuang, karena bullet kosong merusak tampilan halaman
     * pelanggan.
     *
     * @param  mixed  $features
     * @return array<int, string>
     */
    public static function normalizeTierFeatures(mixed $features): array
    {
        if (is_string($features)) {
            $features = preg_split('/[\r\n,]+/', $features) ?: [];
        }

        $normalized = [];

        foreach ((array) $features as $key => $feature) {
            if (is_array($feature)) {
                $feature = $feature['id'] ?? $feature['en'] ?? '';
            }

            if (! is_int($key)) {
                if (is_bool($feature)) {
                    $feature = $feature ? (string) $key : '';
                } elseif (trim((string) $feature) === '') {
                    $feature = (string) $key;
                }
            }

            $feature = trim((string) $feature);

            if ($feature !== '') {
                $normalized[] = $feature;
            }
        }

        return $normalized;
    }

    /**
     * `key` tier -> nama INGGRIS, yaitu nilai yang tersimpan di
     * `packages.name_translations.en`.
     *
     * Kenapa kunci lnggris dan bukan yang tampil: kolom `packages.name`
     * berisi versi Indonesia ("Paket Standar"), sedangkan `tiers()` menyebut
     * "Standard Flower Decoration Package". Keduanya menunjuk baris yang sama
     * lewat `name_translations` = {"en": "Standard ...", "id": "Paket Standar"}.
     *
     * Kenapa ini penting, dan bukan sekadar beda nama: `rename_package_tiers.php`
     * tidak mengganti kolom `name` sama sekali -- ia mengisi
     * `name_translations`. Semua 500 baris sudah punya `en` yang cocok dengan
     * `tiers()`, 1:1, dan `sort_order` 1..5 persis sesuai `index`.
     *
     * DULU `tiers()['name']` dicocokkan ke kolom `name`, dan hasilnya nol
     * untuk SETIAP baris. Gejalanya ada di tiga tempat sekaligus:
     *   - halaman Paket kosong, karena `whereIn('name', [...])` -> 0 dari 500
     *   - form "Pilihan Paket" kosong di halaman Edit, untuk semua 100 katalog
     *   - setiap baris di TiersRelationManager diberi label merah
     *     "Di luar daftar paket standar"
     *
     * Jadi nama Inggris diperlakukan sebagai KUNCI yang tidak ikut berubah
     * saat bahasa tampilan berganti.
     *
     * @return array<string, string>
     */
    /**
     * Baca data tier milik katalog ke bentuk field form `tier_<key>_*`.
     *
     * PUBLIC karena dipakai dari halaman: `mutateFormDataBeforeFill()` di
     * EditCatalog memanggilnya untuk mengisi field `tier_*` dari record yang
     * sedang diedit. Semula protected, waktu hook-nya masih dipanggil dari
     * form() di dalam resource ini -- jadi tidak perlu publik. Sekarang begitu
     * hook-nya pindah ke halaman, halaman adalah kelas berbeda dan protected
     * tidak cukup.
     *
     * @return array<string, mixed>
     */
    public static function tierStateFromRecord(?Catalog $record): array
    {
        if (! $record) {
            return [];
        }

        $state = [];

        $tierName = static::tierTranslationKeys();

        foreach (static::tierPackagesFor($record) as $key => $package) {
            // null = tier ini belum dibuat untuk katalog ini. Form dibiarkan
            // kosong supaya admin mengisinya; tidak ada yang ditulis ke state.
            if (! $package instanceof Package) {
                continue;
            }

            // Nilai Select "Nama Paket": kunci tier, sama persis dengan yang
            // jadi value di `tierSelectOptions()`. Kalau diisi nama tampilan
            // (Indonesia), Filament akan menandai Select itu tidak valid
            // karena nilainya tidak ada di daftar opsi.
            $state["tier_{$key}_name"] = data_get(
                $package->name_translations,
                self::TRANSLATION_KEY_LOCALE,
                $tierName[$key] ?? null
            );

            $state["tier_{$key}_price"] = $package->price;
            $state["tier_{$key}_discount_price"] = $package->discount_price;
            $state["tier_{$key}_description"] = $package->description;
            // Wajib dimuat: TagsInput fitur ditulis ulang setiap kali form ini
            // disimpan (lihat `saveTiers()`). Kalau state-nya kosong, membuka
            // lalu menyimpan form tanpa menyentuh bullet mana pun akan
            // menghapus seluruh bullet tier tersebut.
            $state["tier_{$key}_features"] = static::normalizeTierFeatures($package->features);
        }

        return $state;
    }

    public static function tierTranslationKeys(): array
    {
        $map = [];

        foreach (static::tiers() as $tier) {
            $map[$tier['key']] = $tier['name'];
        }

        return $map;
    }

    /**
     * Batasi query ke lima tier resmi.
     *
     * Dipakai TiersRelationManager supaya tabnya tidak menampilkan seluruh 500
     * baris packages milik semua katalog -- hanya lima milik katalog yang sedang
     * dibuka. `where('name_translations->en', ...)` dipakai, bukan
     * `whereJsonContains`, karena yang dicari satu nilai tepat, bukan isi larik.
     * Operator `->` dikompilasi berbeda antara MySQL dan SQLite, tapi keduanya
     * didukung Laravel.
     */
    public static function scopeTiers(Builder $query): Builder
    {
        return $query->where(function (Builder $inner): void {
            foreach (static::tierTranslationKeys() as $key) {
                $inner->orWhere('name_translations->en', $key);
            }
        });
    }

    /**
     * Lima tier milik satu katalog, dipetakan `key` -> Package|null.
     *
     * Selalu mengembalikan lima entri, bukan hanya yang ada, supaya pemanggil
     * bisa membedakan "tier ini belum dibuat" (null) dari "tier ini tidak
     * dikenal" (tidak ada di daftar sama sekali). Sekaligus menghemat query:
     * satu pemanggilan untuk kelima tier, bukan lima.
     *
     * @return array<string, Package|null>
     */
    public static function tierPackagesFor(?Catalog $record): array
    {
        $result = array_fill_keys(array_keys(static::tierTranslationKeys()), null);

        if (! $record) {
            return $result;
        }

        $keys = static::tierTranslationKeys();

        /*
         * Dibalik: `tierTranslationKeys()` memetakan key -> nama Inggris
         * ("standard" => "Standard ..."), sedangkan yang dicari di sini justru
         * sebaliknya -- nama Inggris pada baris mana tier `standard` itu. Pakai
         * petanya apa adanya, lookup `$keys[$namaInggris]` selalu null karena
         * nama Inggris tidak pernah jadi kunci array itu.
         */
        $keyByTranslation = array_flip($keys);

        foreach ($record->tiers as $package) {
            // `name` accessor mengembalikan name_translations['id'] (Indonesia),
            // jadi kunci bacalah dari cast json secara langsung -- bukan lewat
            // accessor, dan bukan lewat kolom `name`.
            $translationKey = data_get($package->name_translations, self::TRANSLATION_KEY_LOCALE);

            $key = is_string($translationKey)
                ? ($keyByTranslation[$translationKey] ?? null)
                : null;

            if ($key === null) {
                continue;
            }

            $result[$key] = $package;
        }

        return $result;
    }

    /**
     * Tulis field tier_* ke tabel packages.
     *
     * Dipanggil dari afterCreate / afterSave di halaman CreateCatalog dan
     * EditCatalog, jadi katalog sudah punya record sebelum relasi ditulis.
     *
     * Tier dibuat dengan `firstOrCreate` supaya nama, sort_order, dan slug
     * selalu konsisten dengan tiers() -- bukan diisi manual di lima tempat.
     * Field yang benar-benar terisi (harga atau deskripsi) yang dipakai sebagai
     * penanda "admin mengisi tier ini"; tier yang kosong dilewati supaya
     * katalog baru tidak otomatis dapat lima paket kosong.
     *
     * @param  array<string, mixed>  $data
     */
    public static function saveTiers(Catalog $record, array $data): void
    {
        $existing = static::tierPackagesFor($record);
        $definitions = collect(static::tiers());

        /*
         * Paket yang sudah dipakai slot lain dalam form yang sama.
         *
         * Dua slot yang memilih paket yang sama akan menimpa harga yang baru
         * diketik di slot kedua. Menandai yang dipakai duluan membuat slot
         * kedua dilewati, jadi admin melihat harga yang diketik hilang tanpa penjelasan
         * -- lebih baik ditolak dengan jelas.
         */
        $claimed = [];

        foreach (static::tiers() as $tier) {
            $key = $tier['key'];

            // Slot ini diisi paket mana? Nilai Selectnya yang menentukan, bukan
            // nomor slot. Kalau Select tidak terisi (form lama, atau disimpan
            // tanpa menyentuh Select), pakai default slot ini.
            $selected = $data["tier_{$key}_name"] ?? null;

            $target = is_string($selected) && $selected !== ''
                ? $definitions->first(fn (array $t): bool => $t['name'] === $selected)
                : null;

            $target ??= $tier;

            $price = $data["tier_{$key}_price"] ?? null;
            $description = $data["tier_{$key}_description"] ?? null;

            $hasInput = filled($price) || filled($description);

            if (! $hasInput) {
                continue;
            }

            if (in_array($target['name'], $claimed, true)) {
                logger()->warning('CatalogResource::saveTiers() â€” paket dipilih dua kali.', [
                    'catalog_id' => $record->getKey(),
                    'tier' => $target['name'],
                    'slots' => array_keys($data),
                ]);

                continue;
            }

            $claimed[] = $target['name'];

            // Paket yang dicari berdasarkan paket yang DIPILIH, bukan
            // berdasarkan slot -- supaya harga yang diketik ikut paket yang
            // benar, walau slotnya ditukar.
            $package = $existing[$target['key']] ?? null;

            if (! $package instanceof Package) {
                $package = new Package;

                /*
                 * Tier baru harus langsung menyimpan KEDUA nama, bukan cuma
                 * kolom `name`.
                 *
                 * Kolom `name` menyimpan versi Indonesia supaya accessor
                 * `getNameAttribute()` punya nilai untuk locale aktif tanpa
                 * perlu akses database tambahan. `name_translations.en`
                 * menyimpan kunci tier -- itulah yang dibaca `tiers()`,
                 * `scopeTiers()`, dan `tierPackagesFor()`. Tanpa `en`, paket yang baru
                 * dibuat ini langsung tidak terlihat di tab Pilihan Paket dan
                 * tidak muncul lagi di form katalog.
                 */
                $package->name = $target['display'] ?? $target['name'];
                $package->catalog_id = $record->getKey();
            }

            // Kunci tier ikut ditulis ulang setiap kali tier disimpan. Untuk
            // tier yang sudah ada ini nilai yang sama, jadi tidak mengubah
            // apa pun; untuk tier baru ini yang membuatnya dikenali.
            $package->name_translations = array_merge(
                (array) $package->name_translations,
                [self::TRANSLATION_KEY_LOCALE => $target['name']]
            );

            // Slug ikut tier yang dipilih, bukan nomor slot, supaya slug dan
            // nama paket selalu bergerak bersama. Kalau slug tetap memakai
            // nomor slot, menukar paket menghasilkan slug yang namanya tidak
            // cocok.
            $package->slug = str($record->slug)->slug()
                ->append('-'.str($target['key'])->slug())
                ->toString();

            $package->sort_order = $target['index'] ?? null;
            $package->price = filled($price) ? (float) $price : ($package->price ?? 0);
            $package->discount_price = filled($data["tier_{$key}_discount_price"] ?? null)
                ? (float) $data["tier_{$key}_discount_price"]
                : null;

            if (filled($description)) {
                $package->description = $description;
            }

            /*
             * Fitur (bullet di halaman detail katalog).
             *
             * Ditulis selama tier ini memang ikut disimpan -- yaitu yang punya
             * harga atau deskripsi terisi, pakai aturan `$hasInput` di atas.
             * Tier yang form-nya tidak disentuh tidak pernah sampai ke sini,
             * jadi bulletnya tidak ikut terhapus.
             *
             * Yang TIDAK pakai `filled()` seperti deskripsi: TagsInput mengirim
             * array, dan mengosongkan semua bullet adalah keputusan admin yang
             * sah. Kalau memakai `filled()`, bullet lama tidak akan pernah bisa
             * dihapus -- dan tidak ada tempat lain untuk mengeditnya.
             */
            if (array_key_exists("tier_{$key}_features", $data)) {
                $package->features = static::normalizeTierFeatures($data["tier_{$key}_features"]);
            }

            $package->save();
        }
    }

    /**
     * Lima paket dekorasi yang tersedia, sesuai urutan tampil.
     *
     * Satu sumber kebenaran untuk CatalogResource dan TiersRelationManager.
     * Duplikasi nama tier di dua tempat adalah cara pasti
     * sampai ke kondisi "paket standard" ada dan "paket standar" juga ada --
     * dan exatamente itu yang terjadi sebelum daftar ini dibuat.
     *
     * `name` adalah KUNCI tier -- nilai yang tersimpan di
     * `packages.name_translations.en`, bukan teks yang ditampilkan. Yang
     * ditampilkan adalah `display`, bahasa Indonesia yang mengisi kolom
     * `packages.name` dan dibaca accessor `Package::getNameAttribute()` lewat
     * `name_translations['id']`.
     *
     * `rename_package_tiers.php` tidak mengganti kolom `name`; ia mengisi
     * `name_translations`. Jadi seluruh 500 baris sudah punya `en` yang cocok
     * dengan `name` di bawah ini, 1:1. Yang harus dicocokkan ke mana pun --
     * query, form, validasi -- adalah `name`, bukan `display`.
     *
     * @return array<int, array{key: string, name: string, display: string, hint: string, index: int}>
     */
    public static function tiers(): array
    {
        return [
            [
                'key' => 'standard',
                'index' => 1,
                'name' => 'Standard Flower Decoration Package',
                'display' => 'Paket Standar',
                'hint' => __('Paket paling terjangkau: bunga segar pilihan, backdrop sederhana, dekorasi meja tamu.'),
            ],
            [
                'key' => 'medium',
                'index' => 2,
                'name' => 'Medium Flower Decoration Package',
                'display' => 'Paket Sedang',
                'hint' => __('Bunga lebih lengkap, backdrop custom, dan lighting yang lebih lengkap.'),
            ],
            [
                'key' => 'ceremony',
                'index' => 3,
                'name' => 'Ceremony or Wedding Flower Decoration Package',
                'display' => 'Paket Akad & Resepsi',
                'hint' => __('Untuk upacara atau pernikahan: bunga premium import dan pelaminan mewah.'),
            ],
            [
                'key' => 'engagement',
                'index' => 4,
                'name' => 'Engagement Flower Decoration Package',
                'display' => 'Paket Tunangan',
                'hint' => __('Untuk acara lamaran: bunga segar pilihan dan backdrop minimalis.'),
            ],
            [
                'key' => 'full',
                'index' => 5,
                'name' => 'Full Flower Decoration Package',
                'display' => 'Paket Lengkap (All-in-One)',
                'hint' => __('Paket paling lengkap: bunga premium eksklusif, flower wall, dan chandelier bunga.'),
            ],
        ];
    }

    /**
     * Opsi untuk Select "Nama Paket" di lima kartu tier.
     *
     * Nilai = KUNCI tier (nama Inggris yang tersimpan di
     * `name_translations.en`), label = nama yang ditampilkan. Memakai kunci
     * sebagai nilai, bukan `display`, supaya yang tersimpan tidak ikut berubah
     * kalau someday teks Indonesianya ditulis ulang.
     *
     * @return array<string, string>
     */
    public static function tierSelectOptions(): array
    {
        $options = [];

        foreach (static::tiers() as $tier) {
            $options[$tier['name']] = $tier['name'];
        }

        return $options;
    }

    /**
     * Satu field untuk satu tier: nama (Select), harga, diskon, dan deskripsi.
     *
     * State path-nya menyamar jadi `tier_<key>_price` dsb, lalu dipetakan
     * kembali ke model Package saat form disimpan. Field-nya sendiri tidak
     * pernah masuk ke fillable catalogs, jadi tidak ada kolom baru di DB.
     *
     * @param  array{key: string, name: string, display: string, hint: string, index: int}  $tier
     * @return array<int, mixed>
     */
    protected static function tierField(array $tier): array
    {
        $key = $tier['key'];

        return [
            Forms\Components\Group::make()
                ->schema([
                    /*
                     * Select "Nama Paket" -- ini yang dicari, bukan
                     * Placeholder statis seperti sebelumnya.
                     *
                     * Nilai Select adalah KUNCI tier, jadi admin yang salah
                     * menghitung urutan paket masih bisa menukar paket mana
                     * yang mengisi slot ini tanpa kehilangan harga, diskon, dan
                     * deskripsinya. `searchable()` karena kelimanya nama
                     * panjang berbahasa Inggris; dengan `hint` sebagai
                     * keterangan di bawahnya, orang tidak perlu menggulir
                     * untuk mencocokkan.
                     */
                    Forms\Components\Select::make("tier_{$key}_name")
                        ->label(__('Nama Paket'))
                        ->helperText($tier['hint'])
                        ->options(fn (): array => static::tierSelectOptions())
                        ->searchable()
                        ->required()
                        ->live(onBlur: true)
                        ->default($tier['name'])
                        ->native(false),

                    Forms\Components\TextInput::make("tier_{$key}_price")
                        ->label(__('Harga'))
                        ->numeric()
                        ->prefix('Rp')
                        ->minValue(0)
                        // Default mengikuti harga katalog, jadi admin tidak
                        // perlu mengetik ulang angka yang sama lima kali.
                        ->default(fn (?Catalog $record) => $record?->price),

                    Forms\Components\TextInput::make("tier_{$key}_discount_price")
                        ->label(__('Harga Diskon'))
                        ->numeric()
                        ->prefix('Rp')
                        ->minValue(0),

                    Forms\Components\RichEditor::make("tier_{$key}_description")
                        ->label(__('Deskripsi'))
                        ->toolbarButtons(['bold', 'italic', 'link', 'bulletList'])
                        ->columnSpanFull(),

                    /*
                     * Bullet fitur per tier.
                     *
                     * Satu baris = satu bullet, persis seperti yang tampil di
                     * halaman detail katalog ("Bunga segar pilihan standar",
                     * "Backdrop pelaminan sederhana", dan seterusnya). Dulu
                     * field ini dihapus dengan alasan isinya "sudah tercakup
                     * deskripsi" -- tapi bullet itu dibaca terpisah oleh
                     * halaman Welcome, halaman User, dan `CatalogHasTiers`
                     * untuk API. Menghapusnya dari form hanya membuat isinya
                     * tidak bisa diedit lagi, bukan menghapusnya dari sistem.
                     *
                     * Dipakai `TagsInput` supaya satu baris satu bullet.
                     */
                    Forms\Components\TagsInput::make("tier_{$key}_features")
                        ->label(__('Fitur Paket'))
                        ->helperText(__('Satu baris satu bullet. Tampil sebagai daftar centang di halaman detail katalog, di bawah harga.'))
                        ->columnSpanFull(),
                ])
                ->columnSpan(1)
                ->extraAttributes(['class' => 'rounded-xl border border-gray-200 dark:border-white/10 p-4'])
                // Judul di dalam kartu, bukan di luar -- supaya lima field ini
                // terbaca sebagai satu blok per paket.
                ->columnSpanFull(false),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            /*
             * Label "Catalog" di header tabel.
             *
             * Dua tabel lain di halaman ini punya heading sendiri --
             * `CategoryTable` -> "Category", `PackageTable` -> "Package".
             * Tanpa heading di sini, tabel katalog terlihat seperti bagian
             * dari tabel paket: tidak ada garis pemisah yang menyatakan
             * "ini tabel ketiga".
             *
             * `description()` sengaja tidak dipasang, sama seperti dua
             * tabel lainnya yang juga hanya heading.
             */
            ->heading(__('Catalog'))
            ->paginated(false)
            // Diurutkan NAIK: ID 1 jadi baris PERTAMA, sama seperti seluruh
            // tabel admin lain.
            ->defaultSort('id')
            /*
             * Kolom Foto memutar `gallery_urls`, yang membaca lima baris media
             * per katalog. Tanpa eager loading, satu halaman lima baris berarti
             * 25 query tambahan (N+1).
             *
             * NAMA KOLOMNYA HARUS `gallery_urls` -- itu accessor yang benar
             * (`Catalog::getGalleryUrlsAttribute()`, dan `gallery_urls` ada di
             * `$appends`). `catalog_image` itu NAMA COLLECTION media, bukan
             * atribut model: kolom ituÃ¨Â¯Â» jadi null, blade dapat array kosong,
             * dan selnya tampil kosong tanpa error.
             *
             * `SpatieMediaLibraryImageColumn` yang sebelumnya dipakai sudah
             * memanggil `applyEagerLoading()` sendiri, jadi penggantiannya ke
             * view di atas membuat eager loading ini wajib ditulis manual.
             */
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('media'))
            ->columns([
                /*
                 * ID DI PALING KIRI.
                 *
                 * Tabel ini diurutkan `->defaultSort('id')` (naik), jadi
                 * kolom ID membuat urutannya terbaca: 1, 2, 3... di baris
                 * paling atas. Tanpa kolom ini, admin tidak tahu baris
                 * yang sedang diedit itu yang ke-berapa.
                 */
                Tables\Columns\TextColumn::make('id')
                    ->label(__('ID'))
                    ->sortable()
                    ->searchable()
                    ->alignment('start')
                    ->width('60px'),
                Tables\Columns\ViewColumn::make('gallery_urls')
                    ->label(__('Foto'))
                    ->view('Admin.resources.catalog.photos-grid-column')
                    ->alignment('center'),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Nama Katalog'))
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-s-sparkles'),
                Tables\Columns\TextColumn::make('category.name')
                    ->label(__('Kategori'))
                    ->badge()
                    ->color('info')
                    ->sortable(),
                Tables\Columns\TextColumn::make('tiers_count')
                    ->label(__('Paket'))
                    ->badge()
                    ->color(fn ($state): string => (int) $state > 0 ? 'success' : 'gray')
                    ->sortable()
                    ->getStateUsing(fn (Catalog $record): int => $record->tiers()->count())
                    ->description(fn (Catalog $record): string => $record->tiers()->orderBy('sort_order')->limit(3)->pluck('sort_order')->implode(', ') !== ''
                        ? ''
                        : '')
                    ->alignment('center'),
                Tables\Columns\TextColumn::make('price')
                    ->label(__('Harga'))
                    ->money('IDR')
                    ->sortable()
                    ->alignment('end')
                    ->icon('heroicon-s-banknotes'),
                Tables\Columns\TextColumn::make('discount_price')
                    ->label(__('Harga Diskon'))
                    ->money('IDR')
                    ->sortable()
                    ->alignment('end')
                    ->color('success')
                    ->icon('heroicon-s-tag')
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('stock')
                    ->label(__('Stok'))
                    ->badge()
                    ->color(fn ($state): string => match (true) {
                        $state > 10 => 'success',
                        $state > 0 => 'warning',
                        default => 'danger',
                    })
                    ->sortable()
                    ->alignment('center'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Status'))
                    ->boolean()
                    ->sortable()
                    ->alignment('center'),
            ])
            ->headerActions([
                /*
                 * Tombol halaman DIHAPUS dari `ListCatalogs::getHeaderActions()`
                 * dan dipindah ke sini.
                 *
                 * Alasannya bukan sekadar tampilan: "Data Export" yang duduk di
                 * header halaman tidak menjelaskan data apa yang diekspor --
                 * Eindruck tombol itu milik seluruh halaman, padahal yang
                 * diekspor hanya tabel katalog. Di header tabel, tombolnya
                 * menempel pada tabel yang benar-benar diklik, dan dua tabel
                 * lain di halaman ini (Kategori, Paket) sudah begitu.
                 */
                Tables\Actions\ExportAction::make()
                    ->exporter(CatalogExporter::class)
                    ->label(__('Ekspor Data'))
                    ->icon('heroicon-s-arrow-down-tray')
                    ->color('success'),

                Tables\Actions\CreateAction::make()
                    ->label(__('Tambah Catalog'))
                    ->icon('heroicon-s-plus')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title(__('Catalog Ditambahkan'))
                            ->body(__('Catalog baru telah berhasil ditambahkan.'))
                    ),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name')
                    // `searchable()` wajib di sini: daftar kategori sudah
                    // melewati lima belas baris, jadi filter yang hanya bisa
                    // diklik tanpa mengetik membuat orang mencari kategori
                    // dengan cara menggulir -- atau bahkan tidak sempat.
                    ->searchable(['name', 'slug'])
                    ->preload(),
            ])
            ->actions([
                /*
                 * View, Edit, Delete: IKON SAJA -- tanpa teks terlihat,
                 * tanpa garis tepi, dalam ActionGroup.
                 *
                 * `label()` SENGAJA tidak disentuh. `iconButton()` sudah
                 * memindahkan teks label ke `<span class="sr-only">`, yang
                 * disembunyikan secara visual tapi tetap dibaca pembaca layar.
                 * Kalau `label('')` dipasang, span itu ikut kosong dan
                 * tombol kehilangan nama aksesibelnya -- tombol ikon tanpa
                 * nama tidak bisa dipakai buta layar, dan tidak ada yang
                 * gagal: tidak ada error, tidak ada CSS rusak.
                 *
                 * `border-radius:0` TIDAK dipakai lagi -- `ActionGroup`
                 * sudah meng-handle tata letak ikon.
                 */
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->url(fn (Catalog $record): string => static::getUrl('view', ['record' => $record]))
                        ->iconButton()
                        ->color('info')
                        ->size('lg'),
                    Tables\Actions\EditAction::make()
                        ->url(fn (Catalog $record): string => static::getUrl('edit', ['record' => $record]))
                        ->iconButton()
                        ->color('warning')
                        ->size('lg')
                        ->successNotification(
                            Notification::make()
                                ->success()
                                ->title(__('Catalog diperbarui'))
                                ->body(__('Catalog telah berhasil diperbarui.'))
                        ),
                    Tables\Actions\DeleteAction::make()
                        ->iconButton()
                        ->color('danger')
                        ->size('lg')
                        ->successNotification(
                            Notification::make()
                                ->success()
                                ->title(__('Catalog dihapus'))
                                ->body(__('Catalog telah berhasil dihapus.'))
                        ),
                ])->tooltip(__('Actions')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\ExportBulkAction::make()
                        ->exporter(CatalogExporter::class)
                        ->label(__('Ekspor Data Terpilih')),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // Dua halaman yang dihapus, dipindah ke sini sebagai tab:
            //   - `PackageResource` -> TiersRelationManager (lima tier milik
            //     katalog ini). Tidak ada lagi daftar 500 baris packages.
            //   - `CategoryResource` -> CategoriesRelationManager (semua
            //     kategori + tombol "Pakai untuk Katalog Ini").
            RelationManagers\TiersRelationManager::class,
            RelationManagers\CategoriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCatalogs\ListCatalogs::route('/'),
            'create' => Pages\CreateCatalog\CreateCatalog::route('/create'),
            'view' => Pages\ViewCatalog\ViewCatalog::route('/{record}'),
            'edit' => Pages\EditCatalog\EditCatalog::route('/{record}/edit'),
        ];
    }

    /**
     * Strip null / empty values from Spatie upload state so the vendor
     * getUploadedFileUsing closure never receives null as $file (PHP 8.5+
     * strict type enforcement would throw TypeError otherwise).
     */
    private static function sanitizeSpatieUploadState(mixed $state): array
    {
        return collect(is_array($state) ? $state : [$state])
            ->filter(fn (mixed $item): bool => is_string($item) && $item !== '')
            ->mapWithKeys(fn (string $item): array => [$item => $item])
            ->all();
    }

    private static function safeUploadedMediaFileData(Forms\Components\SpatieMediaLibraryFileUpload $component, mixed $file): ?array
    {
        if (! is_string($file) || $file === '' || ! $component->getRecord()) {
            return null;
        }

        /** @var Media|null $media */
        $media = $component->getRecord()->getRelationValue('media')?->firstWhere('uuid', $file);

        if (! $media) {
            return null;
        }

        $url = $component->getConversion() && $media->hasGeneratedConversion($component->getConversion())
            ? $media->getUrl($component->getConversion())
            : $media->getUrl();

        return [
            'name' => $media->getAttributeValue('name') ?? $media->getAttributeValue('file_name'),
            'size' => $media->getAttributeValue('size'),
            'type' => $media->getAttributeValue('mime_type'),
            'url' => $url,
        ];
    }
}
