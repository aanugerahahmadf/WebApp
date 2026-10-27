{{--
    Price block for a Catalog detail page.

    Defaults to the catalog's own price, then swaps to the selected tier's price
    as soon as the tier selector fires `tier-chosen`. Falls back to the catalog
    price whenever the catalog has no tiers, so the block is always meaningful.

    Expects:
      $price         float catalog list price
      $discountPrice float|null catalog discount price
      $tiers         array<int, array{id:int, name:string, price:float, listPrice:float, discounted:bool}>
      $catalogId     int
--}}
@php
    $final = ($discountPrice && $discountPrice > 0) ? (float) $discountPrice : (float) $price;
    $tierPayload = $tiers ?? [];
@endphp

<div
    x-data="{
        tiers: {{ \Illuminate\Support\Js::from($tierPayload) }},
        selectedId: {{ $selectedTierId ?? 'null' }},
        catalog: { price: {{ $final }}, listPrice: {{ (float) $price }}, discounted: {{ $discountPrice && $discountPrice > 0 ? 'true' : 'false' }} },
        get selected() { return this.tiers.find(t => t.id === this.selectedId) ?? null; },
        get price() { return this.selected ? this.selected.price : this.catalog.price; },
        get listPrice() { return this.selected ? this.selected.listPrice : this.catalog.listPrice; },
        get discounted() { return this.selected ? this.selected.discounted : this.catalog.discounted; },
        format(value) {
            return 'Rp ' + new Intl.NumberFormat('id-ID', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }).format(value);
        },
    }"
    x-on:tier-chosen.window="if ($event.detail && Number($event.detail.catalogId) === {{ (int) $catalogId }}) { selectedId = Number($event.detail.tierId); }"
    class="flex items-center justify-start gap-3 flex-wrap mb-4"
    data-testid="catalog-price"
>
    <div
        class="tracking-tight normal-case leading-tight text-2xl font-semibold !text-gray-950 dark:!text-white"
        data-typo="heading"
        data-field="catalogs.name"
    >{{ __($catalogName) }}</div>

    <div class="flex items-baseline flex-wrap">
    <span
        class="catalog-final-price text-2xl font-semibold drop-shadow-sm"
style="color: #facc15;"
        x-text="format(price)"
    >{{ 'Rp '.number_format($final, 2, ',', '.') }}</span>

    <span
        class="catalog-list-price text-sm text-gray-500 dark:text-gray-400 line-through opacity-50 ml-4"
        x-show="discounted"
        x-text="discounted ? format(listPrice) : ''"
    >{{ $discountPrice && $discountPrice > 0 ? 'Rp '.number_format((float) $price, 2, ',', '.') : '' }}</span>
    </div>
</div>
