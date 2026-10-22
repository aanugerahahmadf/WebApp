<?php

namespace App\Livewire\Admin\CatalogTables;

use App\Filament\Admin\Exports\CategoryExporter\CategoryExporter;
use App\Models\Category\Category;
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
use Livewire\Component;

/**
 * Tabel KATEGORI, dirender DI ATAS tabel katalog di halaman
 * `/admin/flowerdecorationscatalog`.
 *
 * Kenapa Livewire component, bukan relation manager: relation manager butuh
 * `getOwnerRecord()`, yaitu satu katalog tertentu. Tabel ini justru GLOBAL --
 * seluruh kategori -- dan sekarang tidak lagi terikat ke katalog mana pun yang
 * sedang dibuka. Itu juga alasan action "Pakai untuk Katalog Ini" TIDAK ada di
 * sini: tidak ada katalog yang jadi target. Action itu masih ada di tab
 * kategori pada halaman detail katalog, di situ ia punya tujuan yang jelas.
 *
 * Gate memakai `AdminAccess`, sama seperti `CatalogResource`, supaya ketiga
 * tabel di halaman ini membaca permission yang sama.
 *
 * Pemeriksaan hak akses dilakukan di `render()`, bukan lewat
 * `canViewAny()` seperti di resource Filament -- komponen ini bukan resource,
 * jadi tidak punya titik gate itu. Kalau tidak berwenang, tabelnya tidak
 * dirender sama sekali; menampilkan tabel kosong justru membocorkan
 * ada/tidaknya data.
 */
class CategoryTable extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;

    public static string $view = 'Admin.livewire.catalog-tables.category-table';

    /**
     * Does this table allow the current admin to write to it?
     *
     * Same source as `CatalogResource::canCreate()/canEdit()/canDelete()`:
     * `manage_catalog`. Held in a property so the blade view and the
     * `table()` method agree -- one calls the other, and neither can drift.
     */
    public function canManage(): bool
    {
        return AdminAccess::allows('manage_catalog');
    }

    public function canViewAny(): bool
    {
        return AdminAccess::allows('view_catalog');
    }

    /**
     * Default query: all categories.
     *
     * Tabel ini adalah DAFTAR NAMA. Tidak ada kolom lain selain nama --
     * angka "dipakai di mana" milik halaman katalog, bukan milik daftar ini.
     */
    public function getTableQuery(): Builder
    {
        // Diurutkan ID NAIK: ID 1 di baris paling atas.
        //
        // Dulu `orderBy('name')` -- jadi baris pertama bukan ID 1, dan
        // nomor urutannya tidak terbaca. Permintaan pemilik: "di tabel
        // urutan ID 1 nya jangan di bawah, seharusnya di atas".
        return Category::query()->orderBy('id');
    }

    /**
 * Schema form kategori.
 *
 * Mengembalikan ARRAY komponen, bukan `Form` -- dan itu bukan pilihan gaya.
 *
 * `Filament\Actions\Concerns\HasForm::getForm()` hanya membaca schema dari
 * ACTION itu sendiri (`$this->form`), lalu mengembalikan `null` kalau action
 * tidak punya. Method `form()` milik komponen Livewire TIDAK dipanggil di
 * jalur itu. Hasilnya: modal terbuka tapi KOSONG -- tanpa satu pun input, dan
 * tanpa error apa pun karena memang tidak ada yang gagal.
 *
 * Jadi setiap action wajib menyambungkan schema-nya lewat `->form()`. Schema-nya
 * disimpan di sini supaya Create dan Edit tidak bisa berbeda isi.
 *
 * `slug` dan `type` TIDAK offer: keduanya NOT NULL dan diisi
 * `Category::booted()` dari nama, jadi mengeditnya hanya memberi kesempatan
 * admin menyimpan nilai yang langsung ditimpa model.
 *
 * @return array<int, mixed>
 */
public function categorySchema(): array
{
    return [
        Forms\Components\TextInput::make('name')
            ->label(__('Nama Kategori'))
            ->placeholder(__('Contoh: Buket Bunga'))
            ->helperText(__('Cukup tulis namanya. URL slug dan tipe kategori diisi otomatis dari nama ini.'))
            ->required()
            ->maxLength(255)
            ->prefixIcon('heroicon-s-bookmark'),
    ];
}

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->recordTitleAttribute('name')
            ->heading(__('Kategori'))
            ->columns([
                /*
                 * ID DI PALING KIRI.
                 *
                 * Tabel ini diurutkan `->defaultSort('id')` (naik), jadi kolom
                 * ID membuat urutannya terbaca: 1, 2, 3... di baris paling
                 * atas.
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
                 * Dulu di sini ada dua kolom badge lagi -- "Dipakai Catalog" dan
                 * "Dipakai Paket". Keduanya dihapus karena jawabannya sudah
                 * ada di tempat lain: halaman detail katalog menampilkan
                 * kategori beserta pakai berapa kali. Menghitungnya di sini juga
                 * berarti menjalankan dua `withCount` untuk angka yang tidak
                 * pernah dilihat admin di tabel ini.
                 */
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Nama Kategori'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-s-bookmark'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('Tambah Kategori'))
                    ->modalHeading(__('Kategori Baru'))
                    ->modalDescription(__('Cukup isi namanya. Slug dan tipe diisi otomatis.'))
                    // Ikon plus, sama dengan "Add Catalog" di tabel katalog.
                    // Tanpa ini tombol ini jadi satu-satunya di ketiga tabel
                    // yang tidak punya ikon, dan tidak terbaca sebagai bagian
                    // dari kelompok yang sama.
                    ->icon('heroicon-s-plus')
                    ->visible(fn (): bool => $this->canManage())
                    ->using(fn (array $data): Category => Category::create($data))
                    // WAJIB: tanpa baris ini modalnya kosong. Lihat
                    // `categorySchema()` untuk penjelasan kenapa method
                    // `form()` milik komponen tidak otomatis dipakai.
                    ->form(fn (Forms\Form $form): Forms\Form => $form->schema($this->categorySchema()))
                    ->model(Category::class)
                    ->successNotification(
                        Notification::make()->success()->title(__('Kategori ditambahkan'))
                    ),

                Tables\Actions\ExportAction::make()
                    ->exporter(CategoryExporter::class)
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
                        ->form(fn (Forms\Form $form): Forms\Form => $form->schema($this->categorySchema())),
                    Tables\Actions\EditAction::make()
                        // Ikon saja: tanpa teks, tanpa garis tepi.
                        ->iconButton()
                        ->visible(fn (): bool => $this->canManage())
                        ->tooltip(__('Edit'))
                        // Sama seperti CreateAction: tanpa ini modal edit kosong.
                        ->form(fn (Forms\Form $form): Forms\Form => $form->schema($this->categorySchema()))
                        ->successNotification(
                            Notification::make()->success()->title(__('Kategori diperbarui'))
                        ),
                    Tables\Actions\DeleteAction::make()
                        ->iconButton()
                        ->visible(fn (): bool => $this->canManage())
                        ->tooltip(__('Delete'))
                        ->successNotification(
                            Notification::make()->success()->title(__('Kategori dihapus'))
                        )
                        ->before(function (Category $record, Tables\Actions\DeleteAction $action): void {
                        // Kategori yang masih dipakai tidak boleh hilang:
                        // `catalogs.category_id` dan `packages.category_id`
                        // menunjuk ke sini. Melewati ini berakhir dengan baris
                        // produk yang kategorinya menunjuk id yang sudah tidak
                        // ada -- dan tidak ada error yang mengatakannya.
                        if ($record->catalogs()->exists() || $record->categoryPackages()->exists()) {
                            Notification::make()
                                ->warning()
                                ->title(__('Tidak dapat dihapus'))
                                ->body(__('Kategori masih dipakai produk. Pindahkan produknya dulu, atau ganti kategorinya.'))
                                ->send();

                            $action->halt();
                        }
                    }),
                ])->tooltip(__('Actions')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading(__('Belum ada kategori'))
            ->emptyStateDescription(__('Tambah kategori lewat tombol di atas, lalu pilih dari form Catalog atau Paket.'))
            ->paginated(false)
            // Diurutkan NAIK: ID 1 jadi baris PERTAMA, sama seperti seluruh
            // tabel admin lain (yang semuanya `->defaultSort('id')`).
            ->defaultSort('id');
    }

    public function render(): View
    {
        return view(static::$view, [
            'canView' => $this->canViewAny(),
        ]);
    }
}
