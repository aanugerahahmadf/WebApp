@php
    // Data default membuat view ini tetap bisa dirender langsung (mis. test
    // storefront yang hanya mengirim `items`), tanpa error undefined variable.
    $items = collect($items ?? []);
    $catalogResource = $catalogResource ?? \App\Filament\Welcome\Resources\CatalogResource\CatalogResource::class;

    $categories = collect($categories ?? []);
    $categoryCounts = collect($categoryCounts ?? []);
    $pricePresets = $pricePresets ?? [];
    $sortOptions = $sortOptions ?? ['newest' => __('Terbaru')];
    $ratingOptions = $ratingOptions ?? [
        '1' => 1.0,
        '2' => 2.0,
        '3' => 3.0,
        '4' => 4.0,
        '4.5' => 4.5,
        '5' => 5.0,
    ];
    $activeFilters = $activeFilters ?? [];

    $catalogCount = (int) ($catalogCount ?? $items->count());
    $filteredCount = (int) ($filteredCount ?? $items->count());
    $hasMore = (bool) ($hasMore ?? false);

    $search = (string) ($catalogSearch ?? '');
    $selectedCategoryIds = collect($catalogCategoryIds ?? [])->map(fn ($id) => (int) $id)->all();
    $priceRange = (string) ($catalogPriceRange ?? '');
    $minPrice = (string) ($catalogMinPrice ?? '');
    $maxPrice = (string) ($catalogMaxPrice ?? '');
    $minRating = (string) ($catalogMinRating ?? '');
    $discountOnly = (bool) ($catalogDiscountOnly ?? false);
    $inStockOnly = (bool) ($catalogInStockOnly ?? false);
    $featuredOnly = (bool) ($catalogFeaturedOnly ?? false);
    $sort = (string) ($catalogSort ?? 'newest');
    $filtersOpen = (bool) ($catalogFiltersOpen ?? true);
@endphp

<style>
/*
 * Instrument Sans: sudah dimuat untuk seluruh aplikasi oleh
 * `public/css/app-font.css` (variable font, bobot 100-1000) dan dipasang
 * lewat `AppFont::styles()`. Aturan di bawah hanya jaring pengaman kalau grid
 * ini dirender di luar layout panel (mis. test/render langsung).
 *
 * Bobot di UI katalog dibatasi 400 (regular) dan 600 (semibold): tidak ada
 * 700 ke atas.
 */
.combined-catalog,
.combined-catalog * {
    font-family: 'Instrument Sans', sans-serif;
}

.combined-catalog {
    --cc-surface: rgba(255, 255, 255, .88);
    --cc-surface-strong: #ffffff;
    --cc-soft: #f1f5f9;
    --cc-border: rgba(15, 23, 42, .10);
    --cc-border-strong: rgba(15, 23, 42, .16);
    --cc-text: #0f172a;
    --cc-muted: #64748b;
    /*
     * Judul grup filter ("Kategori", "Rentang harga", "Rating minimum",
     * "Penawaran & ketersediaan").
     *
     * Sebelumnya memakai --cc-muted dan HURUF KAPITAL. Pemilik meminta
     * judulnya tidak kapital, warnanya sedikit lebih terang, dan semibold
     * (600) -- di mode terang maupun gelap. Nilainya dipisah dari
     * --cc-muted supaya keterangan kecil lain (jumlah chip, "Urutkan")
     * tetap seperti semula.
     */
    --cc-heading: #6b7a90;
    --cc-accent: #be123c;
    --cc-accent-strong: #9f1239;
    --cc-accent-soft: rgba(190, 18, 60, .08);
    --cc-accent-border: rgba(190, 18, 60, .28);
    --cc-shadow: 0 12px 30px rgba(15, 23, 42, .08);

    --catalog-card-bg: #ffffff;
    --catalog-card-text: #111827;
    --catalog-card-border: rgba(15, 23, 42, .08);
    --catalog-img-bg: #f3f4f6;
    --catalog-name-color: #111827;
    --catalog-muted: #6b7280;

    margin-top: 0;
    margin-bottom: 2rem;
    color: var(--cc-text);
}
.dark .combined-catalog,
.dark.combined-catalog {
    /* Neutral dark glass: jangan gunakan tint ungu/biru pada panel katalog. */
    --cc-surface: rgba(18, 18, 18, .88);
    --cc-surface-strong: #1b1b1b;
    --cc-soft: #242424;
    --cc-border: rgba(255, 255, 255, .08);
    --cc-border-strong: rgba(255, 255, 255, .14);
    --cc-text: #e5e7eb;
    --cc-muted: #94a3b8;
    /* Judul grup filter di mode gelap: lebih terang dari --cc-muted. */
    --cc-heading: #b8c4d4;
    --cc-accent: #fb7185;
    --cc-accent-strong: #f43f5e;
    --cc-accent-soft: rgba(251, 113, 133, .12);
    --cc-accent-border: rgba(251, 113, 133, .32);
    --cc-shadow: 0 16px 40px rgba(0, 0, 0, .35);

    --catalog-card-bg: #1a1a2e;
    --catalog-card-text: #e5e7eb;
    --catalog-card-border: rgba(255, 255, 255, .07);
    --catalog-img-bg: #111827;
    --catalog-name-color: #e5e7eb;
    --catalog-muted: #9ca3af;
}
@media(max-width:640px) {
    .combined-catalog { margin-top: 0; }
}
.cc-sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

/* ---------- Toolbar ---------- */
.catalog-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
    padding: 8px;
    background: var(--cc-surface);
    border: 1px solid var(--cc-border);
    border-radius: 14px;
    box-shadow: var(--cc-shadow);
    -webkit-backdrop-filter: blur(12px);
    backdrop-filter: blur(12px);

    /*
     * Toolbar HARUS berada di atas panel filter.
     *
     * `backdrop-filter` (di sini dan di .catalog-filters) membuat masing-
     * masing elemen menjadi stacking context sendiri. Panel filter ada
     * SESUDAH toolbar di DOM, jadi tanpa urutan eksplisit ia dicat di
     * atas toolbar -- dan dropdown "Urutkan" (yang menggantung ke bawah,
     * menimpa panel filter) ikut tertutup. `z-index` pada
     * .catalog-sort-menu tidak menolong: ia terkunci di dalam stacking
     * context toolbar.
     */
    position: relative;
    z-index: 2;
}
.catalog-search {
    position: relative;
    display: flex;
    align-items: center;
    flex: 1 1 220px;
    min-width: 0;
}
.catalog-search input {
    width: 100%;
    height: 38px;
    padding: 0 36px 0 36px;
    border: 1px solid var(--cc-border);
    border-radius: 10px;
    background: var(--cc-surface-strong);
    color: var(--cc-text);
    font-size: 12px;
    outline: none;
    transition: border-color .15s ease, box-shadow .15s ease;
}
.catalog-search input::placeholder { color: var(--cc-muted); }
.catalog-search input:focus {
    border-color: var(--cc-accent-border);
    box-shadow: 0 0 0 3px var(--cc-accent-soft);
}
.catalog-search-icon {
    position: absolute;
    left: 11px;
    width: 15px;
    height: 15px;
    color: var(--cc-muted);
    pointer-events: none;
}
.catalog-search-clear {
    position: absolute;
    right: 5px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 27px;
    height: 27px;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: var(--cc-muted);
    font-size: 16px;
    line-height: 1;
    cursor: pointer;
}
.catalog-search-clear:hover {
    background: var(--cc-soft);
    color: var(--cc-text);
}
.catalog-filter-toggle {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    height: 38px;
    padding: 0 12px;
    border: 1px solid var(--cc-border);
    border-radius: 10px;
    background: var(--cc-surface-strong);
    color: var(--cc-text);
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: background .15s ease, border-color .15s ease, color .15s ease;
}
.catalog-filter-toggle:hover { border-color: var(--cc-border-strong); }
.catalog-filter-toggle.is-active {
    background: var(--cc-accent-soft);
    border-color: var(--cc-accent-border);
    color: var(--cc-accent);
}
.catalog-filter-toggle svg,
.catalog-filter-toggle .catalog-toggle-icon {
    width: 15px;
    height: 15px;
}
.catalog-filter-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    border-radius: 999px;
    background: var(--cc-accent);
    color: #fff;
    font-size: 10px;
    font-weight: 600;
}
.catalog-sort {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: var(--cc-muted);
    font-size: 11px;
    font-weight: 500;
}
.catalog-sort-label {
    white-space: nowrap;
}
.catalog-sort-control {
    position: relative;
    display: inline-flex;
    min-width: 0;
}
/*
 * Dropdown kustom, bukan <select>. Popup <select> digambar oleh OS/browser
 * sehingga sudutnya selalu lancip dan tidak bisa diberi radius; menu ini
 * memakai panel membulat (12px) di semua browser.
 */
.catalog-sort-trigger {
    display: inline-flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    min-width: 158px;
    max-width: 200px;
    height: 38px;
    padding: 0 10px;
    border: 1px solid var(--cc-border);
    border-radius: 10px;
    background: var(--cc-surface-strong);
    color: var(--cc-text);
    font-family: inherit;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    outline: none;
    transition: border-color .15s ease, box-shadow .15s ease;
}
.catalog-sort-trigger:focus-visible,
.catalog-sort-trigger[aria-expanded="true"] {
    border-color: var(--cc-accent-border);
    box-shadow: 0 0 0 3px var(--cc-accent-soft);
}
.catalog-sort-value {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.catalog-sort-caret {
    flex: 0 0 auto;
    width: 0;
    height: 0;
    border-left: 4px solid transparent;
    border-right: 4px solid transparent;
    border-top: 5px solid var(--cc-muted);
    transition: transform .15s ease;
}
.catalog-sort-trigger[aria-expanded="true"] .catalog-sort-caret {
    transform: rotate(180deg);
}
.catalog-sort-menu {
    position: absolute;
    top: calc(100% + 6px);
    right: 0;
    z-index: 40;
    display: grid;
    gap: 2px;
    width: max-content;
    min-width: 100%;
    max-width: min(260px, 80vw);
    padding: 6px;
    border: 1px solid var(--cc-border-strong);
    border-radius: 12px;
    background: var(--cc-surface-strong);
    box-shadow: 0 18px 44px rgba(15, 23, 42, .18);
}
.catalog-sort-menu[x-cloak] {
    display: none !important;
}
.catalog-sort-option {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    width: 100%;
    padding: 8px 10px;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: var(--cc-text);
    font-family: inherit;
    font-size: 12px;
    font-weight: 400;
    text-align: left;
    white-space: nowrap;
    cursor: pointer;
}
.catalog-sort-option:hover,
.catalog-sort-option:focus-visible {
    background: var(--cc-soft);
    outline: none;
}
.catalog-sort-option.is-active {
    background: var(--cc-accent-soft);
    color: var(--cc-accent);
    font-weight: 600;
}
.catalog-sort-check {
    flex: 0 0 auto;
    font-size: 11px;
    line-height: 1;
}

/* ---------- Filter panel ---------- */
.catalog-filters {
    display: none;
    margin-bottom: 12px;
    padding: 14px;
    background: var(--cc-surface);
    border: 1px solid var(--cc-border);
    border-radius: 14px;
    box-shadow: var(--cc-shadow);
    -webkit-backdrop-filter: blur(12px);
    backdrop-filter: blur(12px);

    /* Satu lapisan DI BAWAH toolbar supaya dropdown "Urutkan" yang
       menggantung ke area panel ini tetap terlihat (lihat penjelasan di
       .catalog-toolbar). */
    position: relative;
    z-index: 1;
}
.catalog-filters.is-open {
    display: grid;
    grid-template-columns: 1fr;
    gap: 14px;
}
.catalog-filter-section {
    display: grid;
    gap: 8px;
    min-width: 0;
}
.catalog-filter-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    color: var(--cc-heading);
    font-size: 11px;
    font-weight: 600;
    /* Bukan HURUF KAPITAL lagi: judul tampil apa adanya, jadi tracking
       lebar yang dulu dipakai untuk teks kapital ikut dilepas. */
    letter-spacing: 0;
    text-transform: none;
}
.catalog-filter-hint {
    font-size: 10px;
    font-weight: 500;
    letter-spacing: 0;
    text-transform: none;
    /* Keterangan kecil ("Bisa pilih lebih dari satu") tetap lebih redup
       daripada judulnya. */
    color: var(--cc-muted);
}
.catalog-filter-options {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.catalog-filter-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 30px;
    padding: 4px 9px;
    border: 1px solid var(--cc-border);
    border-radius: 999px;
    background: var(--cc-surface-strong);
    color: var(--cc-text);
    font-size: 11px;
    font-weight: 500;
    line-height: 1.2;
    cursor: pointer;
    user-select: none;
    transition: background .15s ease, border-color .15s ease, color .15s ease;
}
.catalog-filter-chip:hover { border-color: var(--cc-border-strong); }
.catalog-filter-chip.is-active {
    background: var(--cc-accent-soft);
    border-color: var(--cc-accent-border);
    color: var(--cc-accent);
}
.catalog-filter-chip:focus-within {
    outline: 2px solid var(--cc-accent-border);
    outline-offset: 1px;
}
.catalog-filter-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 17px;
    padding: 0 4px;
    border-radius: 999px;
    background: var(--cc-soft);
    color: var(--cc-muted);
    font-size: 9px;
    font-weight: 600;
}
.catalog-filter-chip.is-active .catalog-filter-count {
    background: var(--cc-accent);
    color: #fff;
}
/* Pilihan "Rating minimum" memakai bintang, bukan angka. Tanda "+" di
   belakang bintang sudah dilepas (permintaan pemilik 2026-10-27): tiga
   bintang berarti "3 ke atas", jadi plusnya hanya menambah kebisingan. */
.catalog-filter-chip-rating {
    gap: 4px;
}
.catalog-filter-chip-rating .catalog-stars {
    font-size: 11px;
}
.catalog-price-custom {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 2px;
}
.catalog-price-custom input {
    width: 100%;
    min-width: 0;
    height: 34px;
    padding: 0 9px;
    border: 1px solid var(--cc-border);
    border-radius: 9px;
    background: var(--cc-surface-strong);
    color: var(--cc-text);
    font-size: 11px;
    outline: none;
}
.catalog-price-custom input:focus {
    border-color: var(--cc-accent-border);
    box-shadow: 0 0 0 3px var(--cc-accent-soft);
}
.catalog-price-custom span {
    color: var(--cc-muted);
    font-size: 11px;
    flex-shrink: 0;
}
.catalog-filter-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding-top: 2px;
    border-top: 1px dashed var(--cc-border);
}
.catalog-reset-button {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    height: 34px;
    padding: 0 12px;
    border: 1px solid var(--cc-border);
    border-radius: 9px;
    background: var(--cc-surface-strong);
    color: var(--cc-text);
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
}
.catalog-reset-button:hover {
    border-color: var(--cc-accent-border);
    color: var(--cc-accent);
}
.catalog-filter-total {
    color: var(--cc-muted);
    font-size: 11px;
}

/* ---------- Active chips + meta ---------- */
.catalog-active-filters {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
    margin-bottom: 10px;
}
.catalog-active-label {
    color: var(--cc-muted);
    font-size: 11px;
    font-weight: 600;
    margin-right: 1px;
}
.catalog-active-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    height: 27px;
    padding: 0 9px;
    border: 1px solid var(--cc-accent-border);
    border-radius: 999px;
    background: var(--cc-accent-soft);
    color: var(--cc-accent);
    font-size: 10px;
    font-weight: 600;
    cursor: pointer;
}
.catalog-active-chip:hover { background: var(--cc-accent-soft); filter: brightness(.97); }
.catalog-active-chip .catalog-stars {
    font-size: 9px;
}
.catalog-active-chip-remove {
    font-size: 13px;
    line-height: 1;
    opacity: .75;
}
.catalog-active-reset {
    border: 0;
    background: transparent;
    color: var(--cc-muted);
    font-size: 10px;
    font-weight: 600;
    text-decoration: underline;
    cursor: pointer;
}
.catalog-results-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    margin: 0 2px 8px;
    padding: 7px 10px;
    border-radius: 8px;
    background: var(--cc-surface);
    color: var(--cc-text);
    border: 1px solid var(--cc-border);
    font-size: 11px;
}
.catalog-results-meta strong {
    color: var(--cc-text);
    font-weight: 600;
}

/* Hilangkan spinner bawaan browser pada input harga numerik. */
.combined-catalog input[type='number'] {
    -moz-appearance: textfield;
    appearance: textfield;
}

.combined-catalog input[type='number']::-webkit-inner-spin-button,
.combined-catalog input[type='number']::-webkit-outer-spin-button {
    margin: 0;
    -webkit-appearance: none;
}

/* ---------- Grid + cards ---------- */
.catalog-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 4px;
}
.catalog-card {
    flex-shrink: 0;
    border-radius: 8px;
    overflow: hidden;
    background: var(--catalog-card-bg, #ffffff);
    color: var(--catalog-card-text, #111827);
    box-shadow: 0 1px 4px rgba(0, 0, 0, .1);
    text-decoration: none;
    display: flex;
    flex-direction: column;
    border: 1px solid var(--catalog-card-border, rgba(0, 0, 0, .08));
    width: calc(50% - 3px);
    transition: background .2s, border-color .2s, transform .2s, box-shadow .2s;
}
.catalog-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, .12);
}
@media(min-width:481px) { .catalog-card { width: calc(25% - 5px); } }
@media(min-width:769px) { .catalog-card { width: calc(20% - 5px); } }
@media(min-width:1025px) { .catalog-card { width: calc(16.666% - 5px); } }
.catalog-img {
    position: relative;
    width: 100%;
    aspect-ratio: 1/1;
    overflow: hidden;
    background: var(--catalog-img-bg, #f3f4f6);
    flex-shrink: 0;
}
.catalog-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform .3s ease;
}
.catalog-card:hover .catalog-img img { transform: scale(1.05); }
.catalog-badge-discount {
    position: absolute;
    top: 3px;
    right: 3px;
    background: #eab308;
    color: #000;
    font-size: 9px;
    font-weight: 600;
    padding: 1px 4px;
    border-radius: 3px;
    line-height: 1.4;
    z-index: 10;
}
.catalog-badge-featured {
    position: absolute;
    top: 3px;
    left: 3px;
    display: inline-flex;
    align-items: center;
    gap: 2px;
    max-width: calc(100% - 6px);
    background: var(--cc-accent);
    color: #fff;
    font-size: 8px;
    font-weight: 600;
    letter-spacing: .02em;
    padding: 2px 5px;
    border-radius: 999px;
    line-height: 1.3;
    white-space: nowrap;
    z-index: 10;
}
/*
 * Ikon Filament berbasis path dirender sebagai <img>, bukan <svg>. Aturan
 * `svg` saja tidak pernah kena, sehingga gambar sparkle melebar memenuhi
 * badge dan -- karena border-radius:999px -- berubah jadi lingkaran pink
 * raksasa. Batasi keduanya.
 */
.catalog-badge-featured svg,
.catalog-badge-featured img,
.catalog-badge-featured .catalog-badge-icon {
    width: 8px;
    height: 8px;
    max-width: 8px;
    max-height: 8px;
    flex: 0 0 auto;
    display: inline-block;
    object-fit: contain;
}
.catalog-badge-icon {
    font-size: 8px;
    line-height: 1;
}
.catalog-info {
    padding: 6px 6px 8px;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
    overflow: hidden;
}
.catalog-cat {
    display: inline-block;
    font-size: 9px;
    font-weight: 600;
    line-height: 1;
    padding: 2px 4px;
    border-left-width: 2px;
    border-left-style: solid;
    background: transparent;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.catalog-name {
    font-size: 10px;
    font-weight: 500;
    line-height: 1.3;
    color: var(--catalog-name-color, #111827);
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.catalog-price {
    font-size: 11px;
    font-weight: 600;
    color: #d97706;
    margin: 0;
    line-height: 1.2;
}
.catalog-price-original {
    font-size: 9px;
    color: var(--catalog-muted, #9ca3af);
    text-decoration: line-through;
    margin: 0;
    line-height: 1.2;
}
.catalog-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: auto;
    padding-top: 4px;
}
.catalog-rating {
    display: inline-flex;
    align-items: center;
    line-height: 1;
    color: var(--catalog-muted, #9ca3af);
}
/* Rating dirender sebagai bintang (komponen partial `stars`), bukan angka.
   Angka aslinya tetap ada di `title`/`aria-label` untuk pembaca layar. */
.catalog-stars {
    display: inline-flex;
    align-items: center;
    gap: 1px;
    font-size: 10px;
    line-height: 1;
    font-family: 'Segoe UI Symbol', 'Noto Sans Symbols', sans-serif;
}
.catalog-star {
    font-size: 1em;
    line-height: 1;
    color: rgba(148, 163, 184, .55);
}
.catalog-star.is-filled {
    color: #f59e0b;
}
.catalog-star.is-half {
    background: linear-gradient(90deg, #f59e0b 50%, rgba(148, 163, 184, .55) 50%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    color: transparent;
}
.dark .catalog-star {
    color: rgba(148, 163, 184, .45);
}
.dark .catalog-star.is-filled {
    color: #fbbf24;
}
.dark .catalog-star.is-half {
    background: linear-gradient(90deg, #fbbf24 50%, rgba(148, 163, 184, .45) 50%);
    -webkit-background-clip: text;
    background-clip: text;
}
.catalog-stock-ok {
    font-size: 9px;
    color: #10b981;
    font-weight: 500;
}
.catalog-stock-low {
    font-size: 9px;
    color: #f59e0b;
    font-weight: 600;
}
.catalog-stock-out {
    font-size: 9px;
    color: #ef4444;
    font-weight: 600;
}
.catalog-empty {
    width: 100%;
    padding: 34px 18px;
    text-align: center;
    color: var(--cc-muted);
    border: 1px dashed var(--cc-border-strong);
    border-radius: 12px;
    font-size: 13px;
}
.catalog-empty strong {
    display: block;
    margin-bottom: 4px;
    color: var(--cc-text);
    font-size: 14px;
    /* Elemen <strong> default-nya bold (700); UI ini hanya memakai 400/600. */
    font-weight: 600;
}
.catalog-empty button {
    margin-top: 10px;
}
.catalog-load-more {
    display: flex;
    justify-content: center;
    margin-top: 14px;
}
.catalog-load-more button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-width: 180px;
    height: 40px;
    padding: 0 18px;
    border: 1px solid var(--cc-border-strong);
    border-radius: 10px;
    background: var(--cc-surface-strong);
    color: var(--cc-text);
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: border-color .15s ease, color .15s ease, background .15s ease;
}
.catalog-load-more button:hover {
    border-color: var(--cc-accent-border);
    color: var(--cc-accent);
}
.catalog-load-more button:disabled {
    cursor: wait;
    opacity: .65;
}

@media(min-width:769px) {
    .catalog-filters.is-open {
        grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr);
        align-items: start;
    }
    .catalog-filter-section.is-wide,
    .catalog-filter-footer {
        grid-column: 1 / -1;
    }
    .catalog-sort { margin-left: auto; }
}
@media(max-width:640px) {
    .catalog-toolbar { padding: 7px; }
    .catalog-search { flex-basis: 100%; }
    .catalog-filter-toggle { flex: 1 1 auto; justify-content: center; }
    .catalog-sort { flex: 1 1 auto; justify-content: flex-end; }
    .catalog-sort-control { flex: 1 1 auto; }
    .catalog-sort-trigger { width: 100%; max-width: none; min-width: 0; }
    .catalog-sort-menu {
        left: 0;
        right: auto;
        width: 100%;
        min-width: 100%;
        max-width: 100%;
    }
}

.dark .catalog-card {
    background: #1a1a2e !important;
    border-color: rgba(255, 255, 255, .07) !important;
}
.dark .catalog-name { color: #e5e7eb !important; }
.dark .catalog-img { background: #111827 !important; }
.dark .catalog-price { color: #eab308 !important; }
.dark .catalog-muted { color: #9ca3af !important; }
.light .catalog-card, html:not(.dark) .catalog-card {
    background: #ffffff;
    border-color: rgba(0, 0, 0, .08);
}
.light .catalog-name, html:not(.dark) .catalog-name { color: #111827; }
.light .catalog-img, html:not(.dark) .catalog-img { background: #f3f4f6; }
.light .catalog-price, html:not(.dark) .catalog-price { color: #d97706; }
.light .catalog-muted, html:not(.dark) .catalog-muted { color: #6b7280; }
</style>

<div class="combined-catalog">
    {{-- Toolbar: pencarian, tombol filter, dan urutan. --}}
    <div class="catalog-toolbar">
        <div class="catalog-search">
            <x-filament::icon icon="/images/icons/search.svg" class="catalog-search-icon" />
            <input
                type="search"
                wire:model.live.debounce.400ms="catalogSearch"
                value="{{ $search }}"
                placeholder="{{ __('Cari katalog, kategori, atau kata kunci...') }}"
                aria-label="{{ __('Cari katalog') }}"
            >
            @if($search !== '')
                <button
                    type="button"
                    class="catalog-search-clear"
                    wire:click="removeCatalogFilter('search')"
                    aria-label="{{ __('Hapus pencarian') }}"
                >&times;</button>
            @endif
        </div>

        <button
            type="button"
            class="catalog-filter-toggle {{ $filtersOpen ? 'is-active' : '' }}"
            wire:click="toggleCatalogFilters"
            aria-expanded="{{ $filtersOpen ? 'true' : 'false' }}"
        >
            <x-filament::icon icon="/images/icons/filter.svg" class="catalog-toggle-icon" />
            <span>{{ __('Filter') }}</span>
            @if(count($activeFilters) > 0)
                <span class="catalog-filter-badge">{{ count($activeFilters) }}</span>
            @endif
        </button>

        <div
            class="catalog-sort"
            x-data="{ open: false }"
            x-on:click.outside="open = false"
            x-on:keydown.escape.window="open = false"
        >
            <span class="catalog-sort-label">{{ __('Urutkan') }}</span>
            <div class="catalog-sort-control">
                <button
                    type="button"
                    class="catalog-sort-trigger"
                    x-on:click="open = ! open"
                    x-bind:aria-expanded="open ? 'true' : 'false'"
                    aria-haspopup="listbox"
                >
                    <span class="catalog-sort-value">{{ $sortOptions[$sort] ?? reset($sortOptions) }}</span>
                    <span class="catalog-sort-caret" aria-hidden="true"></span>
                </button>

                {{-- Panel membulat; <select> bawaan browser tidak bisa
                     diatur sudutnya dan tampil lancip di Windows. --}}
                <div class="catalog-sort-menu" x-cloak x-show="open" role="listbox" aria-label="{{ __('Urutkan katalog') }}">
                    @foreach($sortOptions as $value => $label)
                        <button
                            type="button"
                            role="option"
                            aria-selected="{{ $sort === $value ? 'true' : 'false' }}"
                            class="catalog-sort-option {{ $sort === $value ? 'is-active' : '' }}"
                            wire:click="setCatalogSort(@js($value))"
                            x-on:click="open = false"
                        >
                            <span>{{ $label }}</span>
                            @if($sort === $value)
                                <span class="catalog-sort-check" aria-hidden="true">&#10003;</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Panel filter lengkap. --}}
    <div class="catalog-filters {{ $filtersOpen ? 'is-open' : '' }}">
        <div class="catalog-filter-section is-wide">
            <div class="catalog-filter-heading">
                <span>{{ __('Kategori') }}</span>
                <span class="catalog-filter-hint">{{ __('Bisa pilih lebih dari satu') }}</span>
            </div>
            <div class="catalog-filter-options">
                <button
                    type="button"
                    class="catalog-filter-chip {{ $selectedCategoryIds === [] ? 'is-active' : '' }}"
                    wire:click="clearCatalogCategories"
                >
                    <span>{{ __('Semua kategori') }}</span>
                    <span class="catalog-filter-count">{{ $catalogCount }}</span>
                </button>
                @foreach($categories as $category)
                    @php($isSelected = in_array((int) $category->id, $selectedCategoryIds, true))
                    <label class="catalog-filter-chip {{ $isSelected ? 'is-active' : '' }}">
                        <input
                            type="checkbox"
                            class="cc-sr-only"
                            name="catalog-categories[]"
                            value="{{ $category->id }}"
                            wire:model.live="catalogCategoryIds"
                            @checked($isSelected)
                        >
                        <span>{{ __($category->name) }}</span>
                        <span class="catalog-filter-count">{{ $categoryCounts[$category->id] ?? 0 }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="catalog-filter-section">
            <div class="catalog-filter-heading">
                <span>{{ __('Rentang harga') }}</span>
            </div>
            <div class="catalog-filter-options">
                <label class="catalog-filter-chip {{ $priceRange === '' ? 'is-active' : '' }}">
                    <input type="radio" class="cc-sr-only" name="catalog-price-range" value="" wire:model.live="catalogPriceRange" @checked($priceRange === '')>
                    <span>{{ __('Semua harga') }}</span>
                </label>
                @foreach($pricePresets as $key => $preset)
                    <label class="catalog-filter-chip {{ $priceRange === $key ? 'is-active' : '' }}">
                        <input type="radio" class="cc-sr-only" name="catalog-price-range" value="{{ $key }}" wire:model.live="catalogPriceRange" @checked($priceRange === $key)>
                        <span>{{ $preset['label'] }}</span>
                        <span class="catalog-filter-count">{{ $preset['count'] }}</span>
                    </label>
                @endforeach
            </div>
            <div class="catalog-price-custom">
                <input
                    type="number"
                    min="0"
                    step="100000"
                    inputmode="numeric"
                    wire:model.live.debounce.600ms="catalogMinPrice"
                    value="{{ $minPrice }}"
                    placeholder="{{ __('Harga min. (Rp)') }}"
                    aria-label="{{ __('Harga minimum') }}"
                >
                <span>&ndash;</span>
                <input
                    type="number"
                    min="0"
                    step="100000"
                    inputmode="numeric"
                    wire:model.live.debounce.600ms="catalogMaxPrice"
                    value="{{ $maxPrice }}"
                    placeholder="{{ __('Harga maks. (Rp)') }}"
                    aria-label="{{ __('Harga maksimum') }}"
                >
            </div>
        </div>

        <div class="catalog-filter-section">
            <div class="catalog-filter-heading">
                <span>{{ __('Rating minimum') }}</span>
            </div>
            <div class="catalog-filter-options">
                <label class="catalog-filter-chip {{ $minRating === '' ? 'is-active' : '' }}">
                    <input type="radio" class="cc-sr-only" name="catalog-min-rating" value="" wire:model.live="catalogMinRating" @checked($minRating === '')>
                    <span>{{ __('Semua rating') }}</span>
                </label>
                @foreach($ratingOptions as $value => $stars)
                    <label
                        class="catalog-filter-chip catalog-filter-chip-rating {{ $minRating === (string) $value ? 'is-active' : '' }}"
                        title="{{ __('Rating minimum :rating dari 5', ['rating' => $stars]) }}"
                    >
                        <input type="radio" class="cc-sr-only" name="catalog-min-rating" value="{{ $value }}" wire:model.live="catalogMinRating" @checked($minRating === (string) $value)>
                        @include('Shared.components.combined-catalog.partials.stars', ['value' => $stars, 'max' => (int) ceil($stars)])
                        <span class="cc-sr-only">{{ __('Rating minimum :rating bintang dari 5', ['rating' => $stars]) }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="catalog-filter-section is-wide">
            <div class="catalog-filter-heading">
                <span>{{ __('Penawaran & ketersediaan') }}</span>
            </div>
            <div class="catalog-filter-options">
                <label class="catalog-filter-chip {{ $discountOnly ? 'is-active' : '' }}">
                    <input type="checkbox" class="cc-sr-only" name="catalog-discount-only" wire:model.live="catalogDiscountOnly" @checked($discountOnly)>
                    <span>{{ __('Sedang diskon') }}</span>
                </label>
                <label class="catalog-filter-chip {{ $inStockOnly ? 'is-active' : '' }}">
                    <input type="checkbox" class="cc-sr-only" name="catalog-in-stock-only" wire:model.live="catalogInStockOnly" @checked($inStockOnly)>
                    <span>{{ __('Stok tersedia') }}</span>
                </label>
                <label class="catalog-filter-chip {{ $featuredOnly ? 'is-active' : '' }}">
                    <input type="checkbox" class="cc-sr-only" name="catalog-featured-only" wire:model.live="catalogFeaturedOnly" @checked($featuredOnly)>
                    <span>{{ __('Katalog unggulan') }}</span>
                </label>
            </div>
        </div>

        <div class="catalog-filter-footer">
            <button type="button" class="catalog-reset-button" wire:click="resetCatalogFilters">
                <x-filament::icon icon="/images/icons/x_circle.svg" style="width:13px;height:13px;" />
                {{ __('Reset semua filter') }}
            </button>
            <span class="catalog-filter-total">
                {{ __(':count aktif', ['count' => $catalogCount]) }}
            </span>
        </div>
    </div>

    {{-- Chip filter yang sedang aktif. --}}
    @if(count($activeFilters) > 0)
        <div class="catalog-active-filters">
            <span class="catalog-active-label">{{ __('Filter aktif:') }}</span>
            @foreach($activeFilters as $filter)
                <button
                    type="button"
                    class="catalog-active-chip"
                    wire:click="removeCatalogFilter(@js($filter['type']), @js($filter['value'] ?? null))"
                    title="{{ __('Hapus filter') }}"
                >
                    <span>{{ $filter['label'] }}</span>
                    @if(isset($filter['stars']))
                        @include('Shared.components.combined-catalog.partials.stars', ['value' => $filter['stars'], 'max' => (int) ceil($filter['stars'])])
                    @endif
                    <span class="catalog-active-chip-remove">&times;</span>
                </button>
            @endforeach
            <button type="button" class="catalog-active-reset" wire:click="resetCatalogFilters">
                {{ __('Reset semua') }}
            </button>
        </div>
    @endif

    <div class="catalog-results-meta" aria-live="polite">
        <span>
            {{ __('Menampilkan') }}
            <strong>{{ $items->count() }}</strong>
            {{ __('dari') }}
            <strong>{{ $filteredCount }}</strong>
            @if(count($activeFilters) > 0)
                <span>&middot; {{ __('hasil filter dari :total', ['total' => $catalogCount]) }}</span>
            @endif
        </span>
        @if($hasMore)
            <span>{{ __(':remaining lagi', ['remaining' => max(0, $filteredCount - $items->count())]) }}</span>
        @endif
    </div>

    <div class="catalog-grid">
        @forelse($items as $item)
            @include('Shared.components.combined-catalog.partials.card', [
                'item' => $item,
                'catalogResource' => $catalogResource,
            ])
        @empty
            <div class="catalog-empty">
                @if($catalogCount === 0)
                    <strong>{{ __('Belum ada katalog tersedia.') }}</strong>
                    {{ __('Silakan cek kembali nanti.') }}
                @else
                    <strong>{{ __('Tidak ada katalog yang cocok.') }}</strong>
                    {{ __('Coba ubah atau hapus beberapa filter.') }}
                    <br>
                    <button type="button" class="catalog-reset-button" wire:click="resetCatalogFilters">
                        {{ __('Reset semua filter') }}
                    </button>
                @endif
            </div>
        @endforelse
    </div>

    @if($hasMore)
        <div class="catalog-load-more">
            <button
                type="button"
                wire:click="loadMoreCatalogs"
                wire:loading.attr="disabled"
                wire:target="loadMoreCatalogs"
            >
                <span wire:loading.remove wire:target="loadMoreCatalogs">{{ __('Muat lebih banyak') }}</span>
                <span wire:loading wire:target="loadMoreCatalogs">{{ __('Memuat...') }}</span>
            </button>
        </div>
    @endif
</div>
