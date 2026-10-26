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

        $this->selectedTierId = $this->resolveInitialTierId();
    }

    /**
     * Tier awal: `?tier=` dari breadcrumb/tautan checkout kalau valid, lalu
     * tier standar, lalu tier pertama. Selalu ada supaya breadcrumb di halaman
     * ini maupun checkout tidak pernah kehilangan nama paket.
     */
    protected function resolveInitialTierId(): ?int
    {
        $requested = (int) request()->query('tier', 0);

        if ($requested > 0 && $this->record->tiers()->whereKey($requested)->exists()) {
            return $requested;
        }

        $standard = $this->record->tiers()
            ->where('name_translations->en', 'Standard Flower Decoration Package')
            ->value('id')
            ?? $this->record->tiers()->value('id');

        return $standard !== null ? (int) $standard : null;
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
        $crumbs = [
            Home::getUrl() => __('Beranda'),
            CatalogResource::getUrl() => CatalogResource::getBreadcrumb(),
        ];

        $tier = $this->selectedTier();

        if ($tier === null) {
            $crumbs[] = __($this->record->name);

            return $crumbs;
        }

        $crumbs[CatalogResource::getUrl('view', ['record' => $this->record])] = __($this->record->name);

        // Nama tier jadi crumb terakhir (halaman saat ini) dan ikut berubah
        // saat pengguna memilih pill tier lain.
        $crumbs[] = $tier->name;

        return $crumbs;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
