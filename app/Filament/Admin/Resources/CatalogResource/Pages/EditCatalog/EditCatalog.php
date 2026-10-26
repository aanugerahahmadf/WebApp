<?php

namespace App\Filament\Admin\Resources\CatalogResource\Pages\EditCatalog;

use App\Filament\Admin\Pages\Home\Home;
use App\Filament\Admin\Resources\CatalogResource\CatalogResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCatalog extends EditRecord
{
    protected static string $resource = CatalogResource::class;

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title(__('Bunga Diperbarui'))
            ->body(__('Bunga telah berhasil diperbarui.'));
    }

    /**
     * Muat nilai tier yang sudah ada ke field `tier_<key>_*`.
     *
     * Dulu hook ini dipanggil dari `CatalogResource::form()` lewat
     * `->mutateFormDataBeforeFill(...)`. Filament v4 tidak punya method itu di
     * `Filament\Forms\Form`, jadi setiap kali Edit dibuka hasilnya 500:
     *
     *   BadMethodCallException: Method
     *   Filament\Forms\Form::mutateFormDataBeforeFill does not exist.
     *
     * Hook-nya memang masih ada, tapi di PAGINA -- `EditRecord` yang
     * providenya. Dipindah ke sini, dan `$this->record` menggantikan argumen
     * `$record` yang dulu diterima closure-nya.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return array_merge($data, CatalogResource::tierStateFromRecord($this->record));
    }

    /**
     * Buang field `tier_*` sebelum query UPDATE catalogs.
     *
     * Field itu bukan kolom di tabel `catalogs`; nilainya ditulis lewat
     * `afterSave()` -> `CatalogResource::saveTiers()`. Kalau tidak dibuang,
     * Eloquent akan mencoba把它们写入 kolom yang tidak ada dan gagal dengan
     * "Unknown column 'tier_standard_price'".
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
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
     * Catatan sama seperti di CreateCatalog: state dibaca dari
     * `$this->form->getRawState()` karena `mutateFormDataBeforeSave()` sudah
     * membuang key `tier_*` sebelum query UPDATE catalogs.
     */
    protected function afterSave(): void
    {
        parent::afterSave();

        CatalogResource::saveTiers($this->record, $this->form->getRawState());
    }

    public function getTitle(): string
    {
        return $this->record->name;
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

            Actions\DeleteAction::make(),
        ];
    }
}