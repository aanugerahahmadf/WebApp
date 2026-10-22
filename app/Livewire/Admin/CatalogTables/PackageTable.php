<?php

namespace App\Livewire\Admin\CatalogTables;

use App\Filament\Admin\Exports\PackageExporter\PackageExporter;
use App\Filament\Admin\Resources\CatalogResource\CatalogResource;
use App\Models\Package\Package;
use App\Support\AdminAccess\AdminAccess;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

/**
 * Tabel PAKET, dirender DI ANTARA tabel kategori dan tabel katalog di halaman
 * `/admin/flowerdecorationscatalog`.
 *
 * Bedanya dengan `TiersRelationManager` (masih ada sebagai tab di halaman
 * detail katalog): RelationManager itu memakai `$relationship = 'tiers'`, jadi
 * hanya menampilkan paket milik SATU katalog dan urutannya boleh diubah
 * lewat drag. Tabel ini GLOBAL -- seluruh paket milik seluruh katalog -- dan
 * karena itu tidak menyediakan pengurutan ulang: urutan paket di dalam satu
 * katalog adalah urusan tab tersebut, sedangkan di sini yang penting adalah
 * "paket mana yang jadi milik katalog mana".
 *
 * Tabel ini adalah DAFTAR NAMA paket. Satu-satunya kolom adalah nama --
 * harga, stok, katalog, dan kategori milik katalog tertentu dan sudah bisa
 * dilihat dari form Catalog. Yang tidak bisa dilihat di tempat lain adalah
 * apakah nama itu salah satu dari lima tier resmi, dan itu ditampilkan lewat
 * `statusHint()` sebagai description di bawah nama.
 *
 * Gate sama seperti dua tabel lain di halaman ini: `view_catalog` untuk
 * melihat, `manage_catalog` untuk menulis.
 */
class PackageTable extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;

    public static string $view = 'Admin.livewire.catalog-tables.package-table';

    public function canManage(): bool
    {
        return AdminAccess::allows('manage_catalog');
    }

    public function canViewAny(): bool
    {
        return AdminAccess::allows('view_catalog');
    }

    /**
     * Lima nama paket resmi, dalam bahasa yang tampil.
     *
     * Dibaca dari `CatalogResource::tiers()` supaya daftar ini tidak pernah
     * melenceng dari form katalog.
     *
     * @return array<int, string>
     */
    public static function expectedTiers(): array
    {
        return array_column(CatalogResource::tiers(), 'display');
    }

    /**
     * Lima nama paket dalam BAHASA INGGRIS -- nilai yang benar-benar tersimpan
     * di `name_translations['en']`.
     *
     * Dipisahkan dari `expectedTiers()` karena keduanya menjawab pertanyaan
     * berbeda:
     *
     *  - `expectedTiers()` = `display`, bahasa Indonesia. Dipakai form untuk
     *    daftar pilihan, karena itulah isinya kolom `packages.name`.
     *  - `tierKeys()` = `name`, bahasa Inggris. Dipakai `statusHint()`, karena
     *    yang dibaca di sana adalah `name_translations['en']`.
     *
     * DULU keduanya jadi satu daftar `display`, lalu dibandingkan dengan
     * `name_translations['en']`. "Paket Standar" vs "Standard Flower
     * Decoration Package" tidak akan pernah sama, jadi SETIAP baris dari 500
     * paket dapat label "Di luar daftar paket standar".
     *
     * @return array<int, string>
     */
    public static function tierKeys(): array
    {
        return array_column(CatalogResource::tiers(), 'name');
    }

    /**
     * SATU BARIS PER NAMA PAKET -- bukan satu baris per record.
     *
     * Di database ada 500 baris paket, tapi hanya LIMA nama (lima tier
     * resmi): tiap nama diulang sekali untuk tiap katalog. Kalau tabelnya
     * menampilkan semua record, setiap baris terlihat sama persis dan
     * tabelnya jadi 500 baris dengan lima teks berbeda -- persis seperti
     * "teksnya terduplikasi".
     *
     * Jadi tabel ini adalah daftar NAMA paket yang ada, satu baris per nama.
     * Barisnya diambil dari paket dengan ID terkecil untuk nama itu -- jadi
     * salah satu record nyata (bukan baris rekaan), dan aksinya (ubah,
     * hapus) menyasar record itu.
     *
     * Urutan: "Urutan Tampil" (sort_order) dulu, baru nama.
     */
    public function getTableQuery(): Builder
    {
        return Package::query()
            ->whereIn('id', $this->distinctPackageIds())
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /**
     * ID satu paket untuk tiap nama yang ada.
     *
     * `MIN(id)` per `name` -- bukan `distinct name` -- supaya barisnya tetap
     * record sungguhan (bisa diedit/dihapus lewat aksi baris).
     *
     * @return array<int, int>
     */
    protected function distinctPackageIds(): array
    {
        return Package::query()
            ->selectRaw('MIN(id) as id')
            ->groupBy('name')
            ->pluck('id')
            ->all();
    }

    /**
 * Schema form paket.
 *
 * Mengembalikan ARRAY komponen, bukan `Form` -- itu keharusan teknis, bukan
 * pilihan gaya.
 *
 * `Filament\Actions\Concerns\HasForm::getForm()` hanya membaca schema dari
 * ACTION itu sendiri dan mengembalikan `null` kalau action tidak punya. Method
 * `form()` milik komponen Livewire tidak dipanggil di jalur itu, jadi modalnya
 * terbuka KOSONG -- tanpa error, karena memang tidak ada yang gagal.
 *
 * Setiap Create/EditAction wajib menyambungkan lewat `->form()`. Disimpan di
 * sini supaya keduanya tidak bisa berbeda isi.
 *
 * ISINYA CUMA "Nama Paket". Itu keputusan model data, bukan sekadar
 * pemangkasan form:
 *
 * Tabel ini adalah DAFTAR NAMA paket. Harga, diskon, deskripsi, fitur, dan
 * stok milik katalog tertentu, jadi sudah ada di form Catalog pada section
 * "Pilihan Paket". Meminta price/catalog/stok di sini berarti meminta data
 * yang belongs-nya bukan milik tabel ini -- dan membuat satu paket terisi
 * dua kali di dua tempat yang bisa berbeda.
 *
 * Kolom yang tetap terisi otomatis saat disimpan:
 *
 *   - `name_translations` <- kunci tier, ditulis `afterStateUpdated`
 *   - `sort_order`        <- indeks tier, ditulis `afterStateUpdated`
 *   - `slug`              <- hook `Package::boot()`; `packages.slug` punya
 *                            index UNIQUE, jadi hook itu menempelkan
 *                            `Str::random(5)`
 *   - `price`             <- 0, kolomnya NOT NULL tanpa default
 *   - `stock`, `is_active`<- default model
 *   - `catalog_id`, `category_id` <- NULL (boleh NULL di database). Paket
 *                            yang dibuat di sini belum menempel katalog
 *                            mana pun; katalog menunjuk lewat form-nya.
 *
 * @return array<int, mixed>
 */
    public function packageSchema(): array
    {
        return [
            Forms\Components\Section::make(__('Nama Paket'))
                ->icon('heroicon-s-gift')
                ->schema([
/*
                     * `TextInput`, bukan `Select`.
                     *
                     * Dropdown lima tier panjang tidak dipakai seperti yang
                     * dibayangkan: admin tidak memilih dari daftar, mereka
                     * MENGETIK. Select memaksa mereka menebak ejaan persis dari
                     * lima opsi, dan satu perbedaan spasi membuat opsi tampak
                     * tidak valid.
                     *
                     * Teks bebas tetap aman karena `afterStateUpdated` di bawah
                     * mencocokkan apa yang diketik terhadap lima tier resmi --
                     * cocok -> kunci tier tersimpan; tidak cocok ->
                     * `name_translations` dikosongkan dan `statusHint()`
                     * menandai baris itu "Di luar daftar paket standar". Jadi
                     * salah ketik terlihat, bukan diam-diam menghasilkan paket
                     * yang tidak terhubung ke form katalog.
                     */
                    Forms\Components\TextInput::make('name')
                        ->label(__('Nama Paket'))
                        ->placeholder(__('Contoh: Paket Standar'))
                        ->helperText(__('Isi nama paket saja. Harga, diskon, deskripsi, dan stok diisi dari form Catalog, di bagian Pilihan Paket.'))
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Forms\Set $set, ?string $state): void {
                            /*
                             * Cari tier yang cocok, di KEDUA bahasa.
                             *
                             * `display` = "Paket Standar" (Indonesia), `name` =
                             * "Standard Flower Decoration Package" (Inggris).
                             * Admin bisa mengetik yang mana pun; `strcasecmp`
                             * membuat huruf besar/kecil tidak masalah.
                             */
                            $tier = collect(CatalogResource::tiers())->first(
                                fn (array $candidate): bool => strcasecmp(trim((string) ($candidate['display'] ?? '')), trim($state)) === 0
                                    || strcasecmp(trim((string) ($candidate['name'] ?? '')), trim($state)) === 0
                            );

                            if ($tier === null) {
                                /*
                                 * Tidak cocok dengan lima tier resmi.
                                 *
                                 * `name_translations` dikosongkan, BUKAN diisi
                                 * dengan teks yang diketik. Kalau diisi, paket
                                 * ini akan punya kunci tier yang menunjuk ke tier
                                 * lain -- dan `tierPackagesFor()` di form katalog
                                 * akan memasangkannya ke slot yang salah.
                                 *
                                 * Mengosongkannya membuat `statusHint()` menandai
                                 * baris ini, jadi salah ketik terlihat.
                                 */
                                $set('name_translations', []);

                                return;
                            }

                            $set('name_translations', [
                                CatalogResource::TRANSLATION_KEY_LOCALE => $tier['name'],
                            ]);

                            $set('sort_order', $tier['index']);
                        })
                        ->prefixIcon('heroicon-s-tag'),

                    /*
                     * Field tersembunyi, WAJIB ada di schema.
                     *
                     * `afterStateUpdated` di field `name` menulis ke sini.
                     * Kalau field-nya tidak ada di schema, Filament membuang
                     * nilainya -- jadi kunci tier tidak pernah ikut tersimpan,
                     * dan `statusHint()` akan menandai paket sebagai "Di luar
                     * daftar paket standar" begitu saja.
                     *
                     * `dehydrated()` eksplisit karena `Hidden` secara bawaan
                     * ikut dehydrate.
                     */
                    Forms\Components\Hidden::make('name_translations')
                        ->dehydrated(),

                    /*
                     * Urutan ikut, disembunyikan. Ditulis `afterStateUpdated`
                     * dari indeks tier. `sort_order` nullable di database,
                     * jadi record lama yang nilainya kosong tetap aman.
                     */
                    Forms\Components\Hidden::make('sort_order')
                        ->dehydrated(),
                ])->columns(1),
        ];
    }

    public function table(Table $table): Table
    {
        // `tierKeys()`, bukan `expectedTiers()`.
        //
        // `statusHint()` di bawah membaca `name_translations['en']`, yaitu
        // nama tier BAHASA INGGRIS. `expectedTiers()` mengembalikan `display`
        // yang bahasa Indonesia -- jadi perbandingannya tidak akan pernah
        // sama dan setiap baris dapat label "Di luar daftar paket standar".
        $expected = static::tierKeys();

        return $table
            ->query($this->getTableQuery())
            ->recordTitleAttribute('name')
            ->heading(__('Paket'))
->columns([
                /*
                 * ID DI PALING KIRI.
                 *
                 * Tabel paket diurutkan `->defaultSort('sort_order')` --
                 * "Urutan Tampil" 1..5 (bukan urutan baris database), jadi
                 * nomor urutannya BUKAN id. Kolom ID di sini supaya admin
                 * tetap bisa menunjuk baris yang sedang dibahas
                 * ("paket id 37") tanpa menebak urutannya.
                 */
                Tables\Columns\TextColumn::make('id')
                    ->label(__('ID'))
                    ->sortable()
                    ->searchable()
                    ->alignment('start')
                    ->width('60px'),
                /*
                 * NAMA SAJA.
                 *
                 * Enam kolom lain -- Urutan, Kategori, Harga, Harga Diskon,
                 * Stok, Aktif -- semuanya dihapus.
                 *
                 * Bukan karena tidak berguna, tapi karena milik tabel lain.
                 * Harga, stok, dan status diisi dari form Catalog untuk setiap
                 * katalog; menampilkannya di sini berarti menampilkan angka
                 * yang bisa berbeda untuk nama yang sama, tanpa penjelasan di
                 * tabel ini tentang angka mana yang dimaksud.
                 *
                 * "Katalog" juga tidak ditampilkan: tabel ini sudah satu baris
                 * per NAMA paket (lima tier), jadi kolom katalog hanya
                 * menampilkan satu dari-seratus katalog yang memakai nama itu
                 * -- menyesatkan.
                 *
                 * Yang tersisa adalah nama, plus `statusHint()` sebagai
                 * description -- satu-satunya informasi yang milik tabel ini
                 * dan tidak bisa dilihat di tempat lain.
                 */
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Nama Paket'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-s-gift')
                    ->description(fn (?Package $record): string => $this->statusHint($record, $expected)),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('Tambah Paket'))
                    ->modalHeading(__('Paket Baru'))
                    // Ikon plus, sama dengan "Add Catalog" di tabel katalog.
                    ->icon('heroicon-s-plus')
                    ->visible(fn (): bool => $this->canManage())
                    // WAJIB: tanpa baris ini modalnya kosong. Lihat
                    // `packageSchema()` untuk penjelasan kenapa method
                    // `form()` milik komponen tidak otomatis dipakai.
                    ->form(fn (Forms\Form $form): Forms\Form => $form->schema($this->packageSchema()))
                    ->model(Package::class)
                    /*
                     * `price` NOT NULL di database dan TIDAK punya default,
                     * jadi paket yang dibuat dari form nama-saja akan gagal
                     * disimpan dengan "Field price doesn't have a default
                     * value" -- kesalahan database, bukan kesalahan validasi
                     * form, jadi pesannya tidak mengarah ke field yang salah.
                     *
                     * Nilainya 0, bukan harga acak: harga asli diisi dari form
                     * Catalog sesuai katalognya. Angka 0 di sini hanya
                     * placeholder supaya barisnya bisa lahir.
                     */
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['price'] ??= 0;
                        $data['stock'] ??= 10;
                        $data['is_active'] ??= true;

                        return $data;
                    })
                    ->successNotification(
                        Notification::make()->success()->title(__('Paket ditambahkan'))
                    ),

                Tables\Actions\ExportAction::make()
                    ->exporter(PackageExporter::class)
                    ->label(__('Ekspor Data'))
                    ->icon('heroicon-s-arrow-down-tray')
                    ->color('success'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->iconButton()
                        ->color('info')
                        ->tooltip(__('View'))
                        ->form(fn (Forms\Form $form): Forms\Form => $form->schema($this->packageSchema())),
                    Tables\Actions\EditAction::make()
                        // Ikon saja: tanpa teks, tanpa garis tepi.
                        ->iconButton()
                        ->visible(fn (): bool => $this->canManage())
                        ->tooltip(__('Edit'))
                        // Sama seperti CreateAction: tanpa ini modal edit kosong.
                        ->form(fn (Forms\Form $form): Forms\Form => $form->schema($this->packageSchema()))
                        ->successNotification(
                            Notification::make()->success()->title(__('Paket diperbarui'))
                        ),
                    Tables\Actions\DeleteAction::make()
                        ->iconButton()
                        ->visible(fn (): bool => $this->canManage())
                        ->tooltip(__('Delete'))
                        ->successNotification(
                            Notification::make()->success()->title(__('Paket dihapus'))
                        ),
                ])->tooltip(__('Actions')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order')
            ->paginated(false);
    }

    /**
     * Flag paket yang identitasnya tidak ada di lima tier resmi, supaya admin
     * langsung melihat salah ketik atau migrasi yang belum tuntas.
     *
     * Yang dibandingkan adalah `name_translations.en` -- kunci tier yang sama
     * dipakai `CatalogResource::tiers()`, `tierPackagesFor()`, dan
     * `CatalogResource::scopeTiers()`.
     *
     * DULU yang dibaca `$record->name`, yaitu accessor yang mengembalikan
     * `name_translations['id']` ("Paket Standar"), lalu dibandingkan dengan
     * daftar nama Inggris. Tidak pernah sama sekali, jadi SETIAP baris dari 500
     * diberi label merah padahal semuanya paket resmi.
     */
    public function statusHint(?Package $record, array $expected): string
    {
        if (! $record instanceof Package) {
            return __('Nama paket belum diisi');
        }

        /*
         * Nama dicek lebih dulu, dan lewat accessor -- jadi yang dibandingkan
         * adalah teks yang benar-benar tampil, bukan isi kolom mentah.
         */
        $name = $record->name;

        if (! is_string($name) || trim($name) === '') {
            return __('Nama paket belum diisi');
        }

        $translationKey = data_get($record->name_translations, CatalogResource::TRANSLATION_KEY_LOCALE);

        /*
         * Nama terisi, tapi kuncinya kosong.
         *
         * Itu terjadi ketika admin mengetik nama yang bukan salah satu dari
         * lima tier resmi (form sekarang `TextInput`, jadi bebas diketik).
         * Pesan "Nama paket belum diisi" untuk kasus ini menyesatkan --
         * namanya jelas sudah diisi, yang salah adalah isinya.
         */
        if (! is_string($translationKey) || $translationKey === '') {
            return __('Di luar daftar paket standar');
        }

        foreach ($expected as $option) {
            if (strcasecmp(trim($translationKey), trim($option)) === 0) {
                return '';
            }
        }

        return __('Di luar daftar paket standar');
    }

    public function render(): View
    {
        return view(static::$view, [
            'canView' => $this->canViewAny(),
        ]);
    }
}
