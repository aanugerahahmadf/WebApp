<?php

namespace App\Filament\User\Resources\CatalogResource\Pages\ViewCatalog;

use App\Filament\User\Pages\Home\Home;
use App\Filament\User\Resources\CatalogResource\CatalogResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCatalog extends ViewRecord
{
    protected static string $resource = CatalogResource::class;

    /**
     * Currently selected package tier (Paket Bunga Dekorasi Standar / Sedang /
     * Pelaminan atau Pernikahan / Tunangan / Lengkap) for this catalog.
     *
     * Lives on the page so the tier pills, "Pesan Sekarang" and "Masukkan ke
     * Keranjang" all agree on which tier the customer picked.
     */
    public ?int $selectedTierId = null;

    /**
     * Default the selection to the first tier so checkout/cart never has to
     * guess, and drop it when the tier disappears.
     */
    public function updatedSelectedTierId($value): void
    {
        if ($value === null) {
            return;
        }

        if (! $this->record->tiers()->whereKey($value)->exists()) {
            $this->selectedTierId = null;
        }
    }

    public function mount(string|int $record): void
    {
        parent::mount($record);

        $this->selectedTierId = $this->record->tiers()->value('id');
    }

    /**
     * The tier the customer is currently buying, or null when the catalog has
     * no tiers / none was picked.
     */
    public function selectedTier()
    {
        if ($this->selectedTierId === null) {
            return null;
        }

        return $this->record->tiers()->whereKey($this->selectedTierId)->first();
    }

    public function getTitle(): string
    {
        return $this->record->name;
    }

    public function getBreadcrumbs(): array
    {
        return [
            Home::getUrl() => __('Beranda'),
            ...parent::getBreadcrumbs(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
