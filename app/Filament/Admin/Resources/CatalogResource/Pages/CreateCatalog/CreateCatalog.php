<?php

namespace App\Filament\Admin\Resources\CatalogResource\Pages\CreateCatalog;

use App\Filament\Admin\Pages\Home\Home;
use App\Filament\Admin\Resources\CatalogResource\CatalogResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateCatalog extends CreateRecord
{
    protected static string $resource = CatalogResource::class;

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title(__('Bunga Ditambahkan'))
            ->body(__('Bunga baru telah berhasil ditambahkan.'));
    }

    /**
     * Buang field `tier_*` sebelum query INSERT catalogs.
     *
     * Nama hook-nya berubah dari `mutateFormDataBeforeSave()` menjadi
     * `mutateFormDataBeforeCreate()` karena itu yang disediakan `CreateRecord`
     * di Filament v4 -- `mutateFormDataBeforeSave()` hanya ada di `EditRecord`.
     *
     * Dulu hook ini dipanggil dari `CatalogResource::form()`, yang tidak punya
     * method tersebut dan membuat halaman Create/Edit 500.
     *
     * Field `tier_*` bukan kolom di tabel `catalogs`; nilainya ditulis lewat
     * `afterCreate()` -> `CatalogResource::saveTiers()`. Kalau tidak dibuang,
     * Eloquent akan mencoba menulisnya ke kolom yang tidak ada dan gagal dengan
     * "Unknown column 'tier_standard_price'".
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        foreach (array_keys($data) as $key) {
            if (str_starts_with((string) $key, 'tier_')) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    /**
     * Tulis lima field `tier_*` ke tabel packages.
     *
     * Dipanggil setelah katalog tersimpan karena `saveTiers()` butuh record
     * yang sudah punya id: slug tiap paket disusun dari slug katalog, dan
     * packages.catalog_id tidak boleh menunjuk katalog yang belum ada.
     *
     * `getData()` dibaca di sini, bukan dari argumen `$data`, karena
     * `mutateFormDataBeforeCreate()` di atas sudah membuang semua key
     * berawalan `tier_` sebelum query INSERT dijalankan -- kalau tidak, PHP
     * akan error "Unknown column 'tier_standard_price'".
     */
    protected function afterCreate(): void
    {
        parent::afterCreate();

        CatalogResource::saveTiers($this->record, $this->form->getRawState());
    }

        public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label(__('Kembali'))
                ->url(fn () => static::getResource()::getUrl('index'))
                ->color('gray')->button()
                ->icon('/images/icons/arrow_left.svg'),
        ];
    }
}