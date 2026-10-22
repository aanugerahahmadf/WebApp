{{--
    Tier selector for a Catalog detail page.

    Packages are no longer browsable on their own: they exist as tiers of the
    catalog (Standar / Sedang / Pelaminan / Tunangan / Lengkap). This component
    renders them as pills above the Order now / Add to Cart / Admin Chat /
    Add to Favorites buttons. Clicking a pill swaps the description and the
    price, and syncs the choice into Livewire so cart/checkout can use it.

    Expects:
      $record        the Catalog model
      $tiers         ordered Collection<App\Models\Package\Package>
      $selectedTierId currently selected tier id (int|null)
--}}
@php
    $tiers = $tiers ?? collect();
    $selected = $tiers->firstWhere('id', $selectedTierId ?? null) ?? $tiers->first();

    $locale = app()->getLocale();

    // `features` bisa berupa array of STRING (['Bunga segar', 'Backdrop']) atau
    // array of OBJECT hasil translasi otomatis
    // ([['id' => 'Bunga segar', 'en' => 'Fresh flowers'], ...]).
    //
    // DULU nilainya diteruskan apa adanya ke Alpine, dan `x-text="feature"`
    // untuk objek akan menghasilkan literal "[object Object]" -- persis yang
    // terlihat di halaman detail katalog. Jadi bentuknya dinormalkan KE STRING
    // sebelum masuk ke payload Alpine.
    //
    // Function ini dipisah ke closure terpisah (bukan ditulis inline di dalam
    // array) karena ekspresi `fn ($tier) => [...]` tidak boleh memuat statement.
    $featureText = static function ($feature) use ($locale): string {
        if (is_string($feature)) {
            // `__()` supaya teks ini ikut berubah saat language switcher
            // dipakai. Untuk kata yang tidak ada di lang, hasilnya tetap
            // teks aslinya -- tidak ada yang rusak.
            return (string) __($feature);
        }

        if (is_array($feature)) {
            // Untuk bentuk translasi `{id: ..., en: ...}` nominal-nya pilih
            // bahasa aktif, lalu fallback. Untuk yang tidak punya terjemahan,
            // pilih string apa pun yang ada, lalu lewat `__()` juga supaya
            // Lang file punya kesempatan mencarinya.
            $text = (string) ($feature[$locale] ?? $feature['id'] ?? $feature['en'] ?? array_values($feature)[0] ?? '');

            return __($text);
        }

        if (is_object($feature)) {
            $array = (array) $feature;

            return (string) ($array[$locale] ?? $array['id'] ?? $array['en'] ?? '');
        }

        return '';
    };

    $tierPayload = $tiers->map(fn ($tier) => [
        'id' => $tier->id,
        'name' => $tier->name,
        'description' => strip_tags((string) $tier->description),
        'price' => (float) ($tier->discount_price > 0 ? $tier->discount_price : $tier->price),
        'listPrice' => (float) $tier->price,
        'discounted' => $tier->discount_price > 0,
        // Buang entri kosong: kalau hanya baris '{"en": ""}' yang tersimpan,
        // daftar bullet akan berisi spasi yang tak terlihat.
        'features' => collect($tier->features ?? [])->map($featureText)->filter()->values()->all(),
    ])->values();
@endphp

@if ($tiers->isNotEmpty())
    <div
        x-data="{
            tiers: {{ \Illuminate\Support\Js::from($tierPayload) }},
            selectedId: {{ $selected?->id ?? 'null' }},
            get selected() { return this.tiers.find(t => t.id === this.selectedId) ?? this.tiers[0] ?? null; },
            format(value) {
                return 'Rp ' + new Intl.NumberFormat('id-ID', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                }).format(value);
            },
            choose(id) {
                this.selectedId = id;
                // Let the price block (and anything else listening) re-render
                // with this tier's price.
                window.dispatchEvent(new CustomEvent('tier-chosen', {
                    detail: { catalogId: {{ $record->getKey() }}, tierId: id },
                }));
            },
        }"
        x-on:tier-chosen.window="if ($event.detail && $event.detail.catalogId === {{ $record->getKey() }}) { selectedId = $event.detail.tierId; }"
        class="catalog-tier-selector rounded-xl border border-gray-200 dark:border-white/10 p-4 sm:p-5"
        style="
            background-color: var(--fi-glass-bg);
            background-image: linear-gradient(var(--fi-glass-tint), var(--fi-glass-tint));
            border-color: var(--fi-glass-ring);
            -webkit-backdrop-filter: var(--fi-glass-blur);
            backdrop-filter: var(--fi-glass-blur);
        "
        data-testid="catalog-tier-selector"
    >
        <div class="flex items-center gap-2 mb-3">
            <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400">
                <img src="/images/icons/squares.svg" class="w-4 h-4" />
            </span>
            <h3 class="text-sm font-bold text-gray-900 dark:text-white">
                {{ __('Pilih Paket Dekorasi') }}
            </h3>
        </div>

        {{-- Tier pills --}}
        <div class="flex flex-wrap gap-2" role="tablist" aria-label="{{ __('Pilih Paket Dekorasi') }}">
            <template x-for="tier in tiers" :key="tier.id">
                <button
                    type="button"
                    role="tab"
                    @click="choose(tier.id); $wire.set('selectedTierId', tier.id)"
                    @keydown.enter.prevent="choose(tier.id); $wire.set('selectedTierId', tier.id)"
                    @keydown.space.prevent="choose(tier.id); $wire.set('selectedTierId', tier.id)"
                    :aria-selected="selectedId === tier.id"
                    :class="selectedId === tier.id
                        ? 'ring-2 ring-primary-500 bg-primary-50 dark:bg-primary-500/15 text-primary-700 dark:text-primary-300'
                        : 'bg-gray-100 dark:bg-white/5 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-white/10'"
                    class="tier-pill rounded-full px-4 py-2 text-sm font-semibold transition"
                >
                    <span x-text="tier.name"></span>
                </button>
            </template>
        </div>

        {{-- Selected tier detail --}}
        <div class="mt-4 pt-4 border-t border-gray-100 dark:border-white/10" x-show="selected">
            <template x-if="selected">
                <div>
                    <div class="flex items-start justify-between gap-3 flex-wrap">
                        <div class="min-w-0">
                            <p class="text-xl font-bold text-gray-900 dark:text-white leading-tight" x-text="selected.name"></p>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 leading-relaxed" x-text="selected.description || '—'"></p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-xl font-black" style="color: #facc15;" x-text="format(selected.price)"></p>
                            <p
                                x-show="selected.discounted"
                                class="text-xs text-gray-400 line-through"
                                x-text="format(selected.listPrice)"
                            ></p>
                        </div>
                    </div>

                    <ul
                        x-show="(selected.features || []).length"
                        class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1.5"
                    >
                        <template x-for="(feature, i) in selected.features" :key="i">
                            <li class="flex items-start gap-1.5 text-xs text-gray-600 dark:text-gray-300">
                                <img src="/images/icons/check.svg" class="w-3.5 h-3.5 mt-0.5 text-success-500 shrink-0" />
                                <span x-text="feature"></span>
                            </li>
                        </template>
                    </ul>
                </div>
            </template>
        </div>
    </div>
@endif