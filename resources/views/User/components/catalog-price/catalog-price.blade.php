{{--
    Price block for a Catalog detail page.

    Catalog has no sell price. The displayed price always comes from the selected
    tier, or the first ordered tier until the visitor selects another tier.

    Expects:
      $tiers         array<int, array{id:int, name:string, price:float, listPrice:float, discounted:bool}>
      $catalogId     int
--}}
@php
    $tierPayload = $tiers ?? [];
    $firstTier = $tierPayload[0] ?? null;
@endphp

<div
    x-data="{
        tiers: {{ \Illuminate\Support\Js::from($tierPayload) }},
        selectedId: {{ $selectedTierId ?? ($firstTier['id'] ?? 'null') }},
        get selected() { return this.tiers.find(t => t.id === this.selectedId) ?? null; },
        get price() { return this.selected ? this.selected.price : null; },
        get listPrice() { return this.selected ? this.selected.listPrice : null; },
        get discounted() { return this.selected ? this.selected.discounted : false; },
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
    data-field="tiers.price"
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
></span>

    <span
        class="catalog-list-price text-sm text-gray-500 dark:text-gray-400 line-through opacity-50 ml-4"
        x-show="discounted"
        x-text="discounted ? format(listPrice) : ''"
    >{{ $firstTier ? 'Rp '.number_format((float) $firstTier['price'], 2, ',', '.') : __('Pilih tier') }}</span>
    </div>
    <span class="text-sm font-semibold {{ ($catalogStock ?? 0) > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}"
          data-field="catalogs.stock">
        {{ __('Stok Catalog') }}: {{ (int) ($catalogStock ?? 0) }}
    </span>
</div>
