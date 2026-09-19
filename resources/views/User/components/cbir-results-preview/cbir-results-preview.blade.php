@php
    $results = session('cbir_mixed_results', []);
@endphp

<style>
.cbir-grid{display:flex;flex-wrap:wrap;gap:6px;padding:4px 0;}
.cbir-card{
    flex-shrink:0;border-radius:8px;overflow:hidden;background:#1a1a2e;
    box-shadow:0 1px 4px rgba(0,0,0,.3);text-decoration:none;
    display:flex;flex-direction:column;border:1px solid rgba(255,255,255,.07);
    width:calc(50% - 3px);
    position: relative;
    transition: transform 0.2s;
}
.cbir-card:active { transform: scale(0.96); }
@media(min-width:481px){.cbir-card{width:calc(25% - 5px);}}
@media(min-width:769px){.cbir-card{width:calc(20% - 5px);}}
@media(min-width:1025px){.cbir-card{width:calc(16.666% - 5px);}}

.cbir-img{position:relative;width:100%;aspect-ratio:1/1;overflow:hidden;background:#111827;flex-shrink:0;}
.cbir-img img{width:100%;height:100%;object-fit:cover;display:block;}

.cbir-similarity-badge {
    position: absolute;
    top: 4px;
    left: 4px;
    background: rgba(16, 185, 129, 0.9);
    color: white;
    font-size: 8px;
    font-weight: 800;
    padding: 2px 5px;
    border-radius: 4px;
    backdrop-filter: blur(4px);
    z-index: 5;
}

.cbir-type-badge {
    position: absolute;
    bottom: 4px;
    right: 4px;
    background: rgba(0, 0, 0, 0.6);
    color: #9ca3af;
    font-size: 7px;
    text-transform: uppercase;
    font-weight: 700;
    padding: 1px 4px;
    border-radius: 3px;
    backdrop-filter: blur(2px);
}

.cbir-info{padding:6px;flex:1;display:flex;flex-direction:column;gap:2px;overflow:hidden;}
.cbir-cat-container{height:18px;overflow:hidden;margin-bottom:2px;display:flex;align-items:center;}
.cbir-cat{display:inline-block;font-size:9px;font-weight:700;line-height:1;padding:2px 4px;border-left-width:2px;border-left-style:solid;background:transparent;color:#9ca3af;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.cbir-name{font-size:10px;font-weight:500;line-height:1.3;color:#e5e7eb;margin:0;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;height: 2.6rem;}
.cbir-price{font-size:11px;font-weight:700;color:#eab308;margin:1px 0;line-height:1.2;}
.cbir-organizer{font-size:8px;color:#6b7280;margin-top:auto;display:flex;align-items:center;gap:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
</style>

@if(count($results) > 0)
    <div class="mt-4">
        <div class="flex items-center justify-between mb-3 px-1">
            <div class="flex items-center gap-2">
                <x-filament::icon icon="heroicon-s-sparkles" class="w-4 h-4 text-amber-500" />
                <span class="text-sm font-bold text-gray-300">{{ __('Hasil Pencarian') }}</span>
                <x-filament::badge color="gray" size="xs">
                    {{ count($results) }}
                </x-filament::badge>
            </div>
            <x-filament::link
                wire:click="clearVisualSearch"
                color="danger"
                size="xs"
                class="cursor-pointer font-bold no-underline hover:no-underline focus:underline select-none outline-none"
                style="-webkit-tap-highlight-color: transparent;"
            >
                {{ __('Reset') }}
            </x-filament::link>
        </div>

        <div class="cbir-grid">
            @foreach($results as $res)
                @php
                    $type = $res['type'] ?? 'product';
                    $data = $res['data'] ?? [];
                    $score = $res['similarity'] ?? 0;
                    $pct_match = number_format($score, 1);
                    
                    $price = $data['price'] ?? 0;
                    $discountPrice = $data['discount_price'] ?? 0;
                    $finalPrice = $discountPrice > 0 ? $discountPrice : $price;
                    $pct = $discountPrice > 0 && $price > 0 ? round(($price - $discountPrice) / $price * 100) : null;
                    
                    $url = $type === 'package'
                        ? \App\Filament\User\Resources\PackageResource\PackageResource::getUrl('view', ['record' => $data['id'] ?? 0])
                        : \App\Filament\User\Resources\ProductResource\ProductResource::getUrl('view', ['record' => $data['id'] ?? 0]);
                        
                    $img = str_starts_with($data['image_url'] ?? '', 'http') 
                        ? $data['image_url'] 
                        : asset('storage/' . ($data['image_url'] ?? ''));
                        
                    $catName = $data['category']['name'] ?? $data['category'] ?? null;
                    $catColors = ['#f87171','#fb923c','#fbbf24','#34d399','#38bdf8','#818cf8','#e879f9','#f472b6','#a3e635','#2dd4bf'];
                    $catColor = $catName ? $catColors[abs(crc32($catName)) % count($catColors)] : '#6b7280';
                @endphp
                
                <a href="{{ $url }}" wire:navigate class="cbir-card">
                    <div class="cbir-img">
                        @php
                            $matchColor = $score >= 80 ? '#10b981' : ($score >= 60 ? '#eab308' : '#ef4444');
                        @endphp
                        <span class="cbir-similarity-badge" style="background:{{ $matchColor }};">{{ __('Match') }}</span>
                        <img src="{{ $img }}" alt="{{ $data['name'] ?? '' }}" loading="lazy"
                             onerror="this.src='{{ asset('images/placeholders/image-placeholder.svg') }}'">
                        @if($pct)<span class="cbir-badge-discount" style="position:absolute;top:3px;right:3px;background:#eab308;color:#000;font-size:9px;font-weight:900;padding:1px 4px;border-radius:3px;line-height:1.4;">-{{ $pct }}%</span>@endif
                        <span class="cbir-type-badge">{{ __($type) }}</span>
                    </div>
                    
                    <div class="cbir-info">
                        <div class="cbir-cat-container">
                            @if($catName)
                                <span class="cbir-cat" style="border-left-color:{{ $catColor }};color:{{ $catColor }};">
                                    {{ __($catName) }}
                                </span>
                            @endif
                        </div>
                        
                        <p class="cbir-name">{{ $data['name'] ?? '' }}</p>
                        
                        <div style="height:2.2rem;display:flex;flex-direction:column;justify-content:flex-start;gap:0;margin-bottom:3px;">
                            <span class="cbir-price">Rp{{ number_format($finalPrice, 0, ',', '.') }}</span>
                            @if($pct)
                                <span style="font-size:9px;color:#6b7280;text-decoration:line-through;line-height:1.2;">
                                    Rp{{ number_format($price, 0, ',', '.') }}
                                </span>
                            @endif
                        </div>
                        <div class="cbir-footer" style="display:flex;align-items:center;justify-content:space-between;margin-top:auto;padding-top:3px;">
                            <span style="display:flex;align-items:center;gap:2px;font-size:9px;color:#9ca3af;">
                                <svg width="9" height="9" viewBox="0 0 24 24" style="fill:#facc15;flex-shrink:0;"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                                {{ $data['rating'] ?? '0.0' }}
                            </span>
                            @php
                                $stock = $data['stock'] ?? 0;
                                $stockLabel = $stock <= 0 ? __('Habis') : $stock.' '.__('Tersedia');
                                $stockColor = $stock <= 0 ? '#ef4444' : ($stock <= 3 ? '#f59e0b' : '#6b7280');
                            @endphp
                            <span style="font-size:9px;color:{{ $stockColor }}; font-weight: {{ $stock <= 3 ? '700' : '400' }};">
                                {{ $stockLabel }}
                            </span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
@endif