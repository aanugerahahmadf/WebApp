@php
    $type  = $res['type'] ?? 'product';
    $data  = $res['data'] ?? [];
    $score = $res['similarity'] ?? 0;
    $pct   = number_format($score, 1);

    $category = is_array($data['category'] ?? null) ? ($data['category']['name'] ?? null) : ($data['category'] ?? null);

    $url = $type === 'package'
        ? route('filament.user.resources.packages.view', ['record' => $data['id'] ?? 0])
        : route('filament.user.resources.products.view', ['record' => $data['id'] ?? 0]);

    $imgSrc = str_starts_with($data['image_url'] ?? '', 'http')
        ? $data['image_url']
        : asset('storage/' . ($data['image_url'] ?? ''));

    // Price & discount
    $price         = $data['price'] ?? 0;
    $discountPrice = $data['discount_price'] ?? 0;
    $fp            = $discountPrice > 0 ? $discountPrice : $price;
    $discountPct   = ($discountPrice > 0 && $price > 0)
        ? round(($price - $discountPrice) / $price * 100)
        : null;

    // Stock
    $stock      = $data['stock'] ?? 0;
    $stockClass = $stock <= 0 ? 'catalog-stock-out' : ($stock <= 3 ? 'catalog-stock-low' : 'catalog-stock-ok');
    $stockLabel = $stock <= 0 ? __('Habis') : $stock . ' ' . __('Tersedia');

    // Rating (package only)
    $rating = null;
    if ($type === 'package' && isset($data['id'])) {
        $pkg    = \App\Models\Package\Package::find($data['id']);
        $rating = $pkg ? number_format($pkg->reviews()->avg('rating') ?: 0, 1) : null;
    }

    // Category color
    $catColors = ['#f87171','#fb923c','#fbbf24','#34d399','#38bdf8','#818cf8','#e879f9','#f472b6','#a3e635','#2dd4bf'];
    $catColor  = $category ? $catColors[abs(crc32($category)) % count($catColors)] : '#6b7280';

    $isMessages = $this instanceof \App\Livewire\Admin\Messages\Messages\Messages
        || $this instanceof \App\Livewire\User\Messages\Messages\Messages;
@endphp

{{-- Wrapper: sama persis dengan .catalog-card tapi tanpa <a> kalau di messages --}}
@if($isMessages)
<div wire:click="selectNewItem('{{ $type }}', {{ $data['id'] ?? 0 }}, {{ $orderId ?? 0 }})"
     style="cursor:pointer;">
@else
<a href="{{ $url }}" wire:navigate>
@endif

    {{-- Gambar square --}}
    <div class="catalog-img" style="position:relative;width:100%;aspect-ratio:1/1;overflow:hidden;background:#111827;flex-shrink:0;">
        <img src="{{ $imgSrc }}"
             alt="{{ $data['name'] ?? '' }}"
             loading="lazy"
             style="width:100%;height:100%;object-fit:cover;display:block;"
             onerror="this.src='{{ asset('images/placeholders/image-placeholder.svg') }}'">

        {{-- Badge similarity --}}
        @if($score > 0)
            <span style="position:absolute;top:3px;left:3px;background:#eab308;color:#000;font-size:8px;font-weight:900;padding:1px 4px;border-radius:3px;line-height:1.4;">
                {{ $pct }}%
            </span>
        @endif

        {{-- Badge discount --}}
        @if($discountPct)
            <span style="position:absolute;top:3px;right:3px;background:#ef4444;color:#fff;font-size:8px;font-weight:900;padding:1px 4px;border-radius:3px;line-height:1.4;">
                -{{ $discountPct }}%
            </span>
        @endif
    </div>

    {{-- Info — fixed height agar semua card sama --}}
    <div class="catalog-info" style="padding:5px 5px 6px;flex:1;display:flex;flex-direction:column;gap:0;overflow:hidden;">

        {{-- Category badge — fixed height 14px --}}
        <div style="height:18px;overflow:hidden;margin-bottom:2px;display:flex;align-items:center;">
            @if($category)
                <span class="catalog-cat"
                      style="display:inline-block;font-size:9px;font-weight:700;line-height:1;padding:2px 4px;border-left:2px solid {{ $catColor }};color:{{ $catColor }};background:transparent;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    {{ __($category) }}
                </span>
            @endif
        </div>

        {{-- Name — fixed 2 baris = ~2.6rem --}}
        <div style="height:2.6rem;overflow:hidden;margin-bottom:3px;">
            <p style="font-size:10px;font-weight:500;line-height:1.3;color:#e5e7eb;margin:0;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                {{ $data['name'] ?? '' }}
            </p>
        </div>

        {{-- Price — fixed height 2.2rem (harga + harga coret) --}}
        <div style="height:2.2rem;display:flex;flex-direction:column;justify-content:flex-start;gap:0;margin-bottom:3px;">
            <span style="font-size:11px;font-weight:700;color:#eab308;line-height:1.2;display:block;">
                Rp{{ number_format($fp, 0, ',', '.') }}
            </span>
            <span style="font-size:9px;color:#6b7280;text-decoration:line-through;line-height:1.2;display:block;min-height:1rem;">
                @if($discountPct)Rp{{ number_format($price, 0, ',', '.') }}@endif
            </span>
        </div>

        {{-- Footer: rating/weddingFlowersDecorasi + stock — selalu di bawah --}}
        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:auto;padding-top:2px;">
            @if($type === 'package' && $rating !== null)
                <span style="display:flex;align-items:center;gap:2px;font-size:9px;color:#9ca3af;">
                    <svg width="9" height="9" viewBox="0 0 24 24" style="fill:#facc15;flex-shrink:0;"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                    {{ $rating }}
                </span>
            @else
                <span style="font-size:9px;color:#6b7280;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:60%;"></span>
            @endif
            <span class="{{ $stockClass }}" style="font-size:9px;white-space:nowrap;">{{ $stockLabel }}</span>
        </div>
    </div>

@if($isMessages)
</div>
@else
</a>
@endif
