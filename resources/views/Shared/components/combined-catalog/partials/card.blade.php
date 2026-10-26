@php
    $catalogResource = $catalogResource ?? \App\Filament\Welcome\Resources\CatalogResource\CatalogResource::class;

    $originalPrice = (float) $item->price;
    $discountPrice = (float) ($item->discount_price ?? 0);
    $finalPrice = $discountPrice > 0 ? $discountPrice : (float) ($item->final_price ?? $originalPrice);
    $discountPercent = $discountPrice > 0 && $originalPrice > 0
        ? (int) round(($originalPrice - $discountPrice) / $originalPrice * 100)
        : null;

    $url = $catalogResource::getUrl('view', ['record' => $item]);

    $placeholderImg = \App\Support\AppPlatform\AppPlatform::normalizeUrl(asset('images/placeholders/image-placeholder.svg'));
    // Kartu grid cukup memakai konversi `thumb` (400x400). `image_url` adalah
    // versi 1600px untuk halaman detail, terlalu berat untuk puluhan kartu.
    $img = $item->thumbnail_url ?? $item->image_url ?? $placeholderImg;
    $img = filled($img) ? \App\Support\AppPlatform\AppPlatform::normalizeUrl($img) : $placeholderImg;

    $ratingValue = max(0.0, min(5.0, (float) ($item->reviews_avg_rating ?? 0)));
    $ratingLabel = number_format($ratingValue, 1, ',', '.');
    $stock = (int) ($item->stock ?? 0);
    $stockClass = $stock <= 0 ? 'catalog-stock-out' : ($stock <= 3 ? 'catalog-stock-low' : 'catalog-stock-ok');
    $stockLabel = $stock <= 0 ? __('Habis') : $stock.' '.__('Tersedia');

    $catName = $item->category?->name;
    $catName = $catName ? __($catName) : null;
    $catColors = ['#f87171', '#fb923c', '#fbbf24', '#34d399', '#38bdf8', '#818cf8', '#e879f9', '#f472b6', '#a3e635', '#2dd4bf'];
    $catColor = $catName ? $catColors[abs(crc32($catName)) % count($catColors)] : '#6b7280';
@endphp

<a
    href="{{ $url }}"
    wire:navigate
    wire:key="catalog-card-{{ $item->getKey() }}"
    class="catalog-card"
>
    <div class="catalog-img">
        <img
            src="{{ $img }}"
            alt="{{ $item->name }}"
            loading="lazy"
            onerror="this.src='{{ $placeholderImg }}'"
        >

        @if(! empty($item->is_featured))
            <span class="catalog-badge-featured">
                {{-- Glyph teks, bukan <img> ikon Filament: ikon path Filament
                     tidak punya ukuran intrinsik sehingga badge melebar jadi
                     lingkaran pink raksasa. --}}
                <span class="catalog-badge-icon" aria-hidden="true">&#10022;</span>
                {{ __('Unggulan') }}
            </span>
        @endif

        @if($discountPercent)
            <span class="catalog-badge-discount">-{{ $discountPercent }}%</span>
        @endif
    </div>

    <div class="catalog-info">
        <div style="height:18px;overflow:hidden;margin-bottom:2px;display:flex;align-items:center;">
            @if($catName)
                <span class="catalog-cat" style="border-left-color:{{ $catColor }};color:{{ $catColor }};">{{ $catName }}</span>
            @endif
        </div>
        <div style="height:2.6rem;overflow:hidden;margin-bottom:3px;">
            <p class="catalog-name">{{ __($item->name) }}</p>
        </div>
        <div style="height:2.2rem;display:flex;flex-direction:column;justify-content:flex-start;gap:0;margin-bottom:3px;">
            <span class="catalog-price">Rp{{ number_format($finalPrice, 0, ',', '.') }}</span>
            <span class="catalog-price-original" style="min-height:1rem;">
                @if($discountPercent)Rp{{ number_format($originalPrice, 0, ',', '.') }}@endif
            </span>
        </div>
        <div class="catalog-footer">
            <span
                class="catalog-rating"
                role="img"
                title="{{ __('Rating :rating dari 5', ['rating' => $ratingLabel]) }}"
                aria-label="{{ __('Rating :rating dari 5', ['rating' => $ratingLabel]) }}"
            >
                @include('Shared.components.combined-catalog.partials.stars', ['value' => $ratingValue])
            </span>
            <span class="{{ $stockClass }}">{{ $stockLabel }}</span>
        </div>
    </div>
</a>
