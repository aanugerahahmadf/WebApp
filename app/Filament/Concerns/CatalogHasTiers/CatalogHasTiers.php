<?php

namespace App\Filament\Concerns\CatalogHasTiers;

use App\Models\Catalog\Catalog;
use App\Models\Package\Package;
use Filament\Infolists\Components\ViewEntry;

/**
 * Wires the package-tier picker into a Catalog detail page.
 *
 * The picker renders the five tiers (Paket Bunga Dekorasi Standar / Sedang /
 * Pelaminan atau Pernikahan / Tunangan / Lengkap) directly above the
 * Order now / Add to Cart / Admin Chat / Add to Favorites buttons. Picking a
 * tier swaps in that tier's description and broadcasts `tier-chosen` so the
 * price block below re-renders with the tier price.
 *
 * The page holding the infolist must expose `public ?int $selectedTierId`
 * (see the Welcome/User `ViewCatalog` pages).
 */
trait CatalogHasTiers
{
    /** Filament panel folder name, e.g. `Welcome` or `User`. */
    abstract protected static function catalogPanel(): string;

    /**
     * Tier picker entry, placed immediately before the CTA action group.
     */
    protected static function tierSelectorEntry(): ViewEntry
    {
        $panel = static::catalogPanel();

        return ViewEntry::make('tier_selector')
            ->hidden(fn (Catalog $record): bool => $record->tiers()->count() === 0)
            ->view("{$panel}.components.catalog-tier-selector.catalog-tier-selector", fn (Catalog $record) => [
                'record' => $record,
                'tiers' => $record->tiers()->get(),
                'selectedTierId' => static::selectedTierId(),
            ]);
    }

    /**
     * Alpine-backed price block: shows the selected/first tier price.
     */
    protected static function catalogPriceEntry(): ViewEntry
    {
        $panel = static::catalogPanel();

        return ViewEntry::make('catalog_price')
            ->label('')
            ->hiddenLabel()
            ->view("{$panel}.components.catalog-price.catalog-price", fn (Catalog $record) => [
                'catalogName' => (string) $record->name,
                'selectedTierId' => static::selectedTierId(),
                'tiers' => static::tierPayload($record),
                'catalogId' => (int) $record->getKey(),
                'catalogStock' => (int) $record->stock,
            ]);
    }

    /**
     * JS-safe payload shared by the picker and the price block.
     *
     * @return array<int, array{id:int, name:string, description:string, price:float, listPrice:float, discounted:bool, stock:int, features:array<int, string>}>
     */
    public static function tierPayload(Catalog $record): array
    {
        return $record->tiers()
            ->get()
            ->map(fn ($tier): array => [
                'id' => (int) $tier->getKey(),
                'name' => $tier->name,
                'description' => strip_tags((string) $tier->description),
                'price' => (float) $tier->final_price,
                'listPrice' => (float) $tier->price,
                'discounted' => (float) $tier->price > (float) $tier->final_price,
                'stock' => (int) $tier->stock,
                'features' => array_values($tier->features ?? []),
            ])
            ->values()
            ->all();
    }

    /** Return the first ordered tier for display/preview purposes only. */
    protected static function firstTier(Catalog $record): ?Package
    {
        return $record->tiers()->orderBy('sort_order')->orderBy('id')->first();
    }

    /** Tier currently selected on the detail page, or its first tier. */
    protected static function selectedTier(Catalog $record): ?Package
    {
        $selectedId = static::selectedTierId();

        return ($selectedId
            ? $record->tiers()->whereKey($selectedId)->first()
            : null) ?? static::firstTier($record);
    }

    /**
     * The currently selected tier id from the hosting Livewire page.
     */
    protected static function selectedTierId(): ?int
    {
        $page = static::$livewire ?? null;

        return $page && property_exists($page, 'selectedTierId')
            ? ($page->selectedTierId !== null ? (int) $page->selectedTierId : null)
            : null;
    }
}
