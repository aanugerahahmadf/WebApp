@php
    $items = collect();
    if (isset($records) && $records->isNotEmpty()) {
        $items = $records->map(function($item) {
            $item->catalog_type = 'package';
            return $item;
        })->concat(
            \App\Models\Product\Product::where('is_active', true)->with(['category'])->get()->map(function($item) {
                $item->catalog_type = 'product';
                return $item;
            })
        );
    }
    
    $totalCount = count($items);
    $packageCount = $records?->count() ?? 0;
    $productCount = \App\Models\Product\Product::where('is_active', true)->count();
@endphp

<style>
.combined-catalog {
    margin-top: 0;
    margin-bottom: 2rem;
}
@media(max-width:640px){
    .combined-catalog {
        margin-top: 0;
    }
}
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
    box-shadow: 0 1px 4px rgba(0,0,0,.1);
    text-decoration: none;
    display: flex;
    flex-direction: column;
    border: 1px solid var(--catalog-card-border, rgba(0,0,0,.08));
    width: calc(50% - 3px);
    transition: background 0.2s, border-color 0.2s, transform 0.2s, box-shadow 0.2s;
}
.catalog-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,.12);
}
@media(min-width:481px){.catalog-card{width:calc(25% - 5px);}}
@media(min-width:769px){.catalog-card{width:calc(20% - 5px);}}
@media(min-width:1025px){.catalog-card{width:calc(16.666% - 5px);}}
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
    transition: transform 0.3s ease;
}
.catalog-card:hover .catalog-img img {
    transform: scale(1.05);
}
.catalog-badge-discount {
    position: absolute;
    top: 3px;
    right: 3px;
    background: #eab308;
    color: #000;
    font-size: 9px;
    font-weight: 900;
    padding: 1px 4px;
    border-radius: 3px;
    line-height: 1.4;
    z-index: 10;
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
    font-weight: 700;
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
.catalog-price-row {
    display: flex;
    align-items: baseline;
    gap: 3px;
    flex-wrap: wrap;
    margin: 0;
}
.catalog-price {
    font-size: 11px;
    font-weight: 700;
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
    display: flex;
    align-items: center;
    gap: 2px;
    font-size: 9px;
    color: var(--catalog-muted, #9ca3af);
}
.catalog-stock-ok {
    font-size: 9px;
    color: #10b981;
    font-weight: 500;
}
.catalog-stock-low {
    font-size: 9px;
    color: #f59e0b;
    font-weight: 700;
}
.catalog-stock-out {
    font-size: 9px;
    color: #ef4444;
    font-weight: 700;
}
.catalog-empty {
    width: 100%;
    padding: 32px 16px;
    text-align: center;
    color: var(--catalog-muted, #6b7280);
    border: 1px dashed var(--catalog-tab-border, rgba(0,0,0,.08));
    border-radius: 12px;
    font-size: 13px;
}
@media(prefers-color-scheme: light){
    :root {
        --catalog-card-bg: #ffffff;
        --catalog-card-text: #111827;
        --catalog-card-border: rgba(0,0,0,.08);
        --catalog-img-bg: #f3f4f6;
        --catalog-name-color: #111827;
        --catalog-muted: #6b7280;
    }
}
@media(prefers-color-scheme: dark){
    :root {
        --catalog-card-bg: #1a1a2e;
        --catalog-card-text: #e5e7eb;
        --catalog-card-border: rgba(255,255,255,.07);
        --catalog-img-bg: #111827;
        --catalog-name-color: #e5e7eb;
        --catalog-muted: #9ca3af;
    }
}
.dark .catalog-card {
    background: #1a1a2e !important;
    border-color: rgba(255,255,255,.07) !important;
}
.dark .catalog-name { color: #e5e7eb !important; }
.dark .catalog-img { background: #111827 !important; }
.dark .catalog-price { color: #eab308 !important; }
.dark .catalog-muted { color: #9ca3af !important; }
.light .catalog-card, html:not(.dark) .catalog-card {
    background: #ffffff;
    border-color: rgba(0,0,0,.08);
}
.light .catalog-name, html:not(.dark) .catalog-name { color: #111827; }
.light .catalog-img, html:not(.dark) .catalog-img { background: #f3f4f6; }
.light .catalog-price, html:not(.dark) .catalog-price { color: #d97706; }
.light .catalog-muted, html:not(.dark) .catalog-muted { color: #6b7280; }
</style>

<div class="combined-catalog">
    <div class="catalog-grid">
        @foreach($items as $item)
            @php
                $isPackage = $item->catalog_type === 'package';
                $originalPrice = $item->price;
                $discountPrice = $item->discount_price;
                $finalPrice = $discountPrice > 0 ? $discountPrice : ($isPackage ? $item->price : ($item->final_price ?? $item->price));
                $pct = $discountPrice > 0 && $originalPrice > 0 ? round(($originalPrice - $discountPrice) / $originalPrice * 100) : null;
                
                $url = $isPackage 
                    ? \App\Filament\User\Resources\PackageResource\PackageResource::getUrl('view', ['record' => $item])
                    : \App\Filament\User\Resources\ProductResource\ProductResource::getUrl('view', ['record' => $item]);
                    
                $normalizeImageUrl = fn (string $url) => \App\Providers\NativeServiceProvider\NativeServiceProvider::normalizeUrl($url);
                $placeholderImg = $normalizeImageUrl(asset('images/placeholders/image-placeholder.svg'));
                $imgUrl = $item->image_url ?? '';
                if (! filled($imgUrl)) {
                    $img = $placeholderImg;
                } elseif (str_starts_with($imgUrl, 'http://') || str_starts_with($imgUrl, 'https://') || str_starts_with($imgUrl, 'data:image')) {
                    $img = $normalizeImageUrl($imgUrl);
                } elseif (str_starts_with($imgUrl, '/')) {
                    $img = $normalizeImageUrl(str_starts_with($imgUrl, '/storage/') ? asset(ltrim($imgUrl, '/')) : asset('storage/' . ltrim($imgUrl, '/')));
                } else {
                    $img = $normalizeImageUrl(asset('storage/' . $imgUrl));
                }
                $rating = number_format($item->reviews()->avg('rating') ?: 0, 1);
                $stock = $item->stock ?? 0;
                $stockClass = $stock <= 0 ? 'catalog-stock-out' : ($stock <= 3 ? 'catalog-stock-low' : 'catalog-stock-ok');
                $stockLabel = $stock <= 0 ? __('Habis') : $stock . ' ' . __('Tersedia');
                
                $catName = __($item->category?->name) ?? null;
                $catColors = ['#f87171','#fb923c','#fbbf24','#34d399','#38bdf8','#818cf8','#e879f9','#f472b6','#a3e635','#2dd4bf'];
                $catColor = $catName ? $catColors[abs(crc32($catName)) % count($catColors)] : '#6b7280';
            @endphp
            
            <a 
                href="{{ $url }}" 
                wire:navigate 
                class="catalog-card"
            >
                <div class="catalog-img">
                    <img 
                        src="{{ $img }}" 
                        alt="{{ $item->name }}" 
                        loading="lazy"
                        onerror="this.src='{{ $placeholderImg }}'"
                    >
                    
                    @if($pct)
                        <span class="catalog-badge-discount">-{{ $pct }}%</span>
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
                            @if($pct)Rp{{ number_format($originalPrice, 0, ',', '.') }}@endif
                        </span>
                    </div>
                    <div class="catalog-footer">
                        <span class="catalog-rating">
                            <svg width="9" height="9" viewBox="0 0 24 24" style="fill:#facc15;flex-shrink:0;">
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                            </svg>
                            {{ $rating }}
                        </span>
                        <span class="{{ $stockClass }}">{{ $stockLabel }}</span>
                    </div>
                </div>
            </a>
        @endforeach
        
        @if($totalCount === 0)
            <div class="catalog-empty">
                {{ __('Belum ada produk atau paket tersedia.') }}
            </div>
        @endif
    </div>
</div>
