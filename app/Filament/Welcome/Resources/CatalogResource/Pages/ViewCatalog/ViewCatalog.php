<?php

namespace App\Filament\Welcome\Resources\CatalogResource\Pages\ViewCatalog;

use App\Filament\Welcome\Pages\Home\Home;
use App\Filament\Welcome\Resources\CatalogResource\CatalogResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCatalog extends ViewRecord
{
    protected static string $resource = CatalogResource::class;

    /**
     * Currently selected package tier (Standar / Sedang / Pelaminan /
     * Tunangan / Lengkap) for this catalog.
     *
     * Lives on the page so the tier pills, "Order now" and "Add to Cart" all
     * agree on which tier the customer picked.
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

        $exists = $this->record->tiers()->whereKey($value)->exists();

        if (! $exists) {
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
