<?php

namespace App\Filament\Admin\Resources\CatalogResource\RelationManagers;

use App\Filament\Admin\Resources\CatalogResource\CatalogResource;
use App\Models\Package\Package;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The five package tiers of a catalog, edited in place.
 *
 * This is the only place tiers are managed from: the customer-facing pages
 * (User / Welcome) render them read-only above the Order now / Add to Cart
 * buttons, and the standalone package pages no longer exist.
 */
class TiersRelationManager extends RelationManager
{
    protected static string $relationship = 'tiers';

    protected static ?string $title = 'Pilihan Paket';

    protected static ?string $modelLabel = 'Paket';

    protected static ?string $pluralModelLabel = 'Pilihan Paket';

    /**
     * Nama tier yang diharapkan DALAM BAHASA YANG TAMPIL, dalam urutan tampil.
     *
     * Dibaca dari {@see CatalogResource::tiers()} supaya daftar ini tidak
     * pernah melenceng dari form katalog, dan memakai `display` karena itu yang
     * tersimpan di kolom `packages.name`.
     *
     * DULU daftar ditulis ulang di sini sebagai ['Standar', 'Sedang',
     * 'Pelaminan / Pernikahan', 'Tunjungan', 'Lengkap'] sementara data memakai
     * nama yang sama persis tapi berbeda ejaan -- hasil kolom "Di luar daftar
     * paket standar" merah di setiap baris.
     *
     * @return array<int, string>
     */
    protected static function expectedTiers(): array
    {
        return array_column(CatalogResource::tiers(), 'display');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('Paket & Keterkaitan'))
                ->description(__('Nama, deskripsi, dan harga paket. Paket ini muncul sebagai pilihan di halaman detail Catalog, tepat di atas tombol Order now / Add to Cart.'))
                ->icon('heroicon-s-gift')
                ->schema([
                    Forms\Components\Select::make('name')
                        ->label(__('Nama Paket'))
                        ->helperText(__('Lima nama di bawah ini sudah dipakai di form katalog. Paket dengan nama lain tidak muncul sebagai pilihan.'))
                        ->searchable()
                        /*
                         * Opsi memakai `display` (Indonesia), bukan `name`
                         * (Inggris), karena nilai yang disimpan di kolom
                         * `packages.name` -- dan yang dibaca accessor `name`
                         * lewat `name_translations['id']` -- adalah versi
                         * Indonesia. Kalau opsinya nama Inggris, setiap select
                         * akan menandai nilainya sendiri sebagai tidak valid.
                         */
                        ->options(fn () => collect(CatalogResource::tiers())
                            ->mapWithKeys(fn (array $tier): array => [$tier['display'] => $tier['display']])
                            ->all())
                        // Record yang namanya bukan salah satu dari lima
                        // tetap bisa dibuka & disimpan, supaya tidak ada paket
                        // lama yang terkunci dan tidak bisa diperbaiki.
                        ->in(fn (?Model $record): array => $record === null
                            ? self::expectedTiers()
                            : array_values(array_unique([$record->name, ...self::expectedTiers()])))
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Forms\Set $set, ?string $state): void {
                            $set('slug', str($state)->slug());

                            /*
                             * Kunci tier ikut dipindahkan. Tanpa ini, mengganti
                             * nama paket jadi membuat `name_translations.en`
                             * menunjuk tier yang berbeda dari yang tampil --
                             * paketnya hilang dari halaman Paket dan dari form
                             * katalog, lalu muncul sebagai tier baru saat
                             * katalog disimpan lagi.
                             */
                            $tier = collect(CatalogResource::tiers())
                                ->first(fn (array $candidate): bool => ($candidate['display'] ?? null) === $state);

                            // Dibaca dari record, bukan dari `$this->getState()`: RelationManager tidak
                            // punya accessor state yang sama bentuknya dengan
                            // komponen form, dan yang dibutuhkan di sini hanya
                            // nilai tersimpan sebelum form ini dibuka.
                            $record = $this->getOwnerRecord()->getChildRecord();
                            $translations = $record instanceof Package
                                ? (array) $record->name_translations
                                : [];

                            if ($tier === null) {
                                unset($translations[CatalogResource::TRANSLATION_KEY_LOCALE]);
                            } else {
                                $translations[CatalogResource::TRANSLATION_KEY_LOCALE] = $tier['name'];

                                if ($tier['index'] !== null) {
                                    $set('sort_order', $tier['index']);
                                }
                            }

                            $set('name_translations', $translations);
                        })
                        ->prefixIcon('heroicon-s-tag'),

                    /*
                     * Field tersembunyi untuk `name_translations`.
                     *
                     * `afterStateUpdated` di field `name` menulis kunci tier ke
                     * sini. Kalau field-nya tidak ada di schema, Filament
                     * membuang nilainya -- jadi kuncinya tidak pernah ikut
                     * tersimpan, dan paket yang namanya diganti akan hilang
                     * dari halaman Paket begitu disimpan.
                     *
                     * `dehydrated()` dipanggil eksplisit karena `Hidden` secara
                     * bawaan ikut dehydrate, tapi itu SLOT yang tidak boleh
                     *tetap tersimpan sebagai bagian dari state form.
                     */
                    Forms\Components\Hidden::make('name_translations')
                        ->dehydrated(),

                    Forms\Components\TextInput::make('sort_order')
                        ->label(__('Urutan Tampil'))
                        ->helperText(__('Paket dengan urutan lebih kecil tampil lebih dulu.'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(99)
                        ->step(1)
                        ->default(1)
                        ->prefixIcon('heroicon-s-bars-3'),

                    Forms\Components\TextInput::make('slug')
                        ->label(__('Slug'))
                        ->required()
                        ->unique(ignorable: fn (?Model $record) => $record)
                        ->maxLength(255)
                        ->prefixIcon('heroicon-s-link'),

                    /*
                     * DESKRIPSI TIDAK DIEDIT DI SINI -- sengaja.
                     *
                     * Kolom `packages.description` masih ada dan masih dibaca
                     * aplikasi mobile, gRPC, GraphQL, dan REST API, jadi tidak
                     * ada yang dihapus dari database maupun kontrak API.
                     *
                     * Yang dihapus hanya kolom editor-nya, karena deskripsi tiap
                     * tier sudah punya satu tempat yang jelas: lima field
                     * deskripsi di section "Pilihan Paket" pada
                     * `CatalogResource::form()`, tepat di atas tab ini.
                     * Dua form deskripsi untuk data yang sama berarti tidak ada
                     * yang tahu yang mana yang berlaku terakhir kalau keduanya
                     * diisi berbeda.
                     *
                     * Halaman `PackageResource` yang tadinya juga punya kolom ini
                     * sudah dihapus; alasannya sama persis.
                     */
                ])->columns(2),

            Forms\Components\Section::make(__('Harga'))
                ->icon('heroicon-s-currency-dollar')
                ->schema([
                    Forms\Components\TextInput::make('price')
                        ->label(__('Harga Paket'))
                        ->helperText(__('Setiap pilihan paket wajib punya harga yang berbeda.'))
                        ->required()
                        ->formatStateUsing(fn ($state) => $state ? number_format((float) $state, 2, ',', '.') : null)
                        ->dehydrateStateUsing(fn ($state) => $state ? (float) str_replace(',', '.', str_replace(['Rp', '.', ' '], '', $state)) : null)
                        ->prefix('Rp')
                        ->extraInputAttributes(['class' => 'font-bold text-lg text-primary-600']),

                    Forms\Components\TextInput::make('discount_price')
                        ->label(__('Harga Diskon'))
                        ->formatStateUsing(fn ($state) => $state ? number_format((float) $state, 2, ',', '.') : null)
                        ->dehydrateStateUsing(fn ($state) => $state ? (float) str_replace(',', '.', str_replace(['Rp', '.', ' '], '', $state)) : null)
                        ->prefix('Rp')
                        ->validationAttribute('price')
                        ->rules(['nullable']),

                    Forms\Components\TextInput::make('stock')
                        ->label(__('Stok / Kuota'))
                        ->numeric()
                        ->default(10)
                        ->minValue(0)
                        ->prefixIcon('heroicon-s-archive-box'),

                    /*
                     * TagsInput "Fitur Paket" DIHAPUS -- sama dengan field
                     * `tier_<key>_features` di CatalogResource, supaya tidak
                     * ada dua tempat untuk satu data. Kolom `packages.features`
                     * sendiri dibiarkan: masih dibaca CatalogHasTiers (API),
                     * halaman Welcome, dan halaman User.
                     */
                ])->columns(2),
        ]);
    }

    public function table(Table $table): Table
    {
        $expected = static::expectedTiers();

        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('Urutan'))
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->alignment('center'),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('Nama Paket'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-s-gift')
                    ->extraAttributes(['data-typo' => 'heading', 'data-field' => 'catalogs.tier_name'])
                    ->description(fn ($record): string => $this->statusHint($record, $expected)),

                Tables\Columns\TextColumn::make('price')
                    ->label(__('Harga'))
                    ->money('IDR')
                    ->sortable()
                    ->alignment('end')
                    ->extraAttributes(['data-field' => 'catalogs.tier_price'])
                    ->icon('heroicon-s-banknotes'),

                Tables\Columns\TextColumn::make('discount_price')
                    ->label(__('Harga Diskon'))
                    ->money('IDR')
                    ->placeholder('—')
                    ->extraAttributes(['data-field' => 'catalogs.tier_discount_price'])
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('stock')
                    ->label(__('Stok'))
                    ->badge()
                    ->color(fn ($state): string => match (true) {
                        (int) $state <= 0 => 'danger',
                        (int) $state <= 5 => 'warning',
                        default => 'success',
                    })
                    ->numeric()
                    ->sortable()
                    ->alignment('center')
                    ->extraAttributes(['data-field' => 'catalogs.tier_stock']),

                Tables\Columns\TextColumn::make('description')
                    ->label(__('Deskripsi'))
                    ->limit(60)
                    ->extraAttributes(['data-typo' => 'description', 'data-field' => 'catalogs.tier_description'])
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('is_active')
                    ->label(__('Aktif'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state ? __('Aktif') : __('Nonaktif'))
                    ->colors(['success', 'danger'])
                    ->alignment('center'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('Tambah Pilihan Paket'))
                    ->modalHeading(__('Pilihan Paket Baru'))
                    ->successNotification(
                        Notification::make()->success()->title(__('Paket ditambahkan'))
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->successNotification(
                        Notification::make()->success()->title(__('Paket diperbarui'))
                    ),
                Tables\Actions\DeleteAction::make()
                    ->successNotification(
                        Notification::make()->success()->title(__('Paket dihapus'))
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->paginated(false);
    }

    /**
     * Flag a tier yang identitasnya tidak ada di lima tier resmi, supaya admin
     * langsung melihat salah ketik atau migrasi yang belum tuntas.
     *
     * Yang dibandingkan adalah `name_translations.en` -- kunci tier yang sama
     * dipakai `CatalogResource::tiers()`, `tierPackagesFor()`, dan
     * `PackageResource::getEloquentQuery()`.
     *
     * DULU di sini yang dibaca `$record->name`, yaitu accessor yang
     * mengembalikan `name_translations['id']` ("Paket Standar"), lalu
     * dibandingkan dengan daftar nama Inggris ("Standard Flower Decoration
     * Package"). Tidak pernah sama sekali, jadi SETIAP baris dari 500 diberi
     * label merah "Di luar daftar paket standar" padahal semuanya paket resmi.
     */
    protected function statusHint(?Package $record, array $expected): string
    {
        if (! $record instanceof Package) {
            return __('Nama paket belum diisi');
        }

        $translationKey = data_get($record->name_translations, CatalogResource::TRANSLATION_KEY_LOCALE);

        if (! is_string($translationKey) || $translationKey === '') {
            return __('Nama paket belum diisi');
        }

        foreach ($expected as $option) {
            if (strcasecmp(trim($translationKey), trim($option)) === 0) {
                return '';
            }
        }

        return __('Di luar daftar paket standar');
    }
}
