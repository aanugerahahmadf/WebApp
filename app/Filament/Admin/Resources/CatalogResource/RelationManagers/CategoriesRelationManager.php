<?php

namespace App\Filament\Admin\Resources\CatalogResource\RelationManagers;

use App\Filament\Admin\Exports\CategoryExporter\CategoryExporter;
use App\Models\Catalog\Catalog;
use App\Models\Category\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Kategori, dikelola dari halaman katalog.
 *
 * Kenapa manager ini ada: resource `CategoryResource` dihapus. Kategori tidak
 * punya halaman sendiri lagi -- semua kategori dikelola dari dalam katalog, lewat
 * tab ini. Alasannya konsekuensi dari model datanya: `categories` bukan entitas
 * yang berdiri sendiri, melainkan label yang dipakai `catalogs.category_id` dan
 * `packages.category_id`. Satu daftar global dengan form terpisah hanya
 * menambah halaman yang harus dicari admin sebelum bisa memasang kategori ke
 * produk.
 *
 * Daftar yang ditampilkan SENTIJA seluruh kategori, bukan hanya kategori milik
 * katalog ini. Kalau hanya satu, tab ini tidak bisa dipakai buat membuat
 * kategori baru -- persis saat admin butuh. Kolom "Dipakai katalog ini" yang
 * menandai kategori milik katalog yang sedang dibuka.
 *
 * Kolom `slug` dan `type` tidak diedit di sini. keduanya NOT NULL, jadi
 * `Category::booted()` mengisinya otomatis dari nama: slug dijamin unik, dan
 * `type` memakai `Category::DEFAULT_TYPE`. Descripsi dan ikon juga tidak
 * diedit -- tidak ada di mana pun yang membacanya (lihat catatan di
 * `Category`).
 */
class CategoriesRelationManager extends RelationManager
{
    /**
     * `Catalog::category()` adalah `belongsTo`, jadi manager ini menampilkan
     * satu kategori milik katalog -- laluasque diperluas jadi daftar lengkap
     * lewat `getTableQuery()` di bawah.
     *
     * Relasi tetap dipakai (bukan di-hardcode ke query kosong) supaya Filament
     * tetap tahu tabel ini terikat pada katalog yang sedang dibuka.
     */
    protected static string $relationship = 'category';

    protected static ?string $title = 'Kategori';

    protected static ?string $modelLabel = 'Kategori';

    protected static ?string $pluralModelLabel = 'Kategori';

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * Katalog yang sedang dibuka, dipakai closure di `table()` supaya tidak
     * perlu memanggil `getOwnerRecord()` berulang kali.
     */
    public function getCatalog(): Catalog
    {
        return $this->getOwnerRecord();
    }

    /**
     * Seluruh kategori, bukan hanya milik katalog ini.
     *
     * Method parent mengembalikan `null` supaya tabel jatuh ke relasi
     * (yaitu satu kategori). Di sini diganti supaya daftar yang tampil
     * benar-benar daftar kategori.
     */
    protected function getTableQuery(): Builder
    {
        return Category::query()
            ->withCount('catalogs')
            ->orderBy('name');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label(__('Nama Kategori'))
                    ->placeholder(__('Contoh: Buket Bunga'))
                    ->helperText(__('Cukup tulis namanya. URL slug dan tipe kategori diisi otomatis dari nama ini.'))
                    ->required()
                    ->maxLength(255)
                    ->prefixIcon('heroicon-s-bookmark'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Nama Kategori'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-s-bookmark')
                    ->description(fn (Category $record): string => (string) $record->slug),

                Tables\Columns\TextColumn::make('catalogs_count')
                    ->label(__('Dipakai Katalog'))
                    ->badge()
                    ->color(fn (Category $record): string => $record->catalogs_count > 0 ? 'info' : 'gray')
                    ->sortable()
                    ->alignment('center'),

                // Penanda kategori milik katalog yang sedang dibuka. Tanpa ini,
                // admin tidak tahu kategori mana yang sudah terpasang.
                Tables\Columns\IconColumn::make('dipakai')
                    ->label(__('Kategori Katalog Ini'))
                    ->icon(fn (Category $record): string => $record->is($this->getCatalog()->category) ? 'heroicon-s-check-badge' : 'heroicon-s-minus')
                    ->color(fn (Category $record): string => $record->is($this->getCatalog()->category) ? 'success' : 'gray')
                    ->getStateUsing(fn (Category $record): bool => $record->is($this->getCatalog()->category)),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('Tambah Kategori'))
                    ->modalHeading(__('Kategori Baru'))
                    ->modalDescription(__('Cukup isi namanya. Slug dan tipe diisi otomatis.'))
                    // `CreateAction` bawaan menulis lewat `$relationship->save()`,
                    // dan relasi di sini `belongsTo` yang tidak punya method itu --
                    // akan berakhir "Call to undefined method BelongsTo::save()".
                    // Karena itu pembuatan ditulis eksplisit.
                    ->using(fn (array $data): Category => Category::create($data))
                    ->successNotification(
                        Notification::make()->success()->title(__('Kategori ditambahkan'))
                    ),
                // Ekspor kategori ikut pindah ke sini, karena halaman kategori
                // yang punya tombolnya sudah dihapus. Eksportirnya sama dan
                // masih dipakai Pusat Unduhan Data, jadi tidak ada duplikasi.
                Tables\Actions\ExportAction::make()
                    ->exporter(CategoryExporter::class)
                    ->label(__('Ekspor Data'))
                    ->icon('heroicon-s-arrow-down-tray')
                    ->color('success'),
            ])
            ->actions([
                Tables\Actions\Action::make('pakai')
                    ->label(__('Pakai untuk Katalog Ini'))
                    ->icon('heroicon-s-link')
                    ->color('primary')
                    ->button()
                    ->size('lg')
                    ->visible(fn (Category $record): bool => ! $record->is($this->getCatalog()->category))
                    ->action(function (Category $record): void {
                        $catalog = $this->getCatalog();

                        $catalog->forceFill(['category_id' => $record->getKey()])->save();

                        Notification::make()
                            ->success()
                            ->title(__('Kategori katalog diperbarui'))
                            ->body(__(':name sekarang dipakai katalog ini.', ['name' => $record->name]))
                            ->send();
                    }),
                Tables\Actions\EditAction::make()
                    ->label(__('Ubah Nama'))
                    ->successNotification(
                        Notification::make()->success()->title(__('Kategori diperbarui'))
                    ),
                Tables\Actions\DeleteAction::make()
                    ->label(__('Hapus'))
                    ->successNotification(
                        Notification::make()->success()->title(__('Kategori dihapus'))
                    )
                    ->before(function (Category $record, Tables\Actions\DeleteAction $action): void {
                        // Kategori yang masih dipakai tidak boleh hilang:
                        // `catalogs.category_id` dan `packages.category_id`
                        // menunjuk ke sini, termasuk katalog yang sedang dibuka.
                        if ($record->catalogs()->exists() || $record->categoryPackages()->exists()) {
                            Notification::make()
                                ->warning()
                                ->title(__('Tidak dapat dihapus'))
                                ->body(__('Kategori masih dipakai produk. Pindahkan produknya dulu, atau ganti kategorinya.'))
                                ->send();

                            $action->halt();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading(__('Belum ada kategori'))
            ->emptyStateDescription(__('Tambah kategori lewat tombol di atas, lalu pasang ke katalog ini dengan tombol "Pakai untuk Katalog Ini".'))
            ->paginated(false)
            // Diurutkan NAIK: ID 1 jadi baris PERTAMA, sama seperti seluruh
            // tabel admin lain.
            ->defaultSort('id');
    }
}