@php
    $allResults = session('cbir_mixed_results', []);
    $results = $allResults;

    $packages = [];
    $products = [];

    if (!empty($results)) {
        $packages = collect($results)->where('type', 'package')->values()->all();
        $products = collect($results)->where('type', 'product')->values()->all();

        // Jika CBIR hanya return packages, load semua products sebagai fallback
        if (empty($products)) {
            foreach (\App\Models\Product\Product::where('is_active', true)->with('category')->get() as $prd) {
                $products[] = ['type' => 'product', 'similarity' => 0, 'data' => array_merge($prd->toArray(), [
                    'image_url' => $prd->image_url,
                    'category' => $prd->category?->toArray(),
                ])];
            }
        }

        // Jika CBIR hanya return products, load semua packages sebagai fallback
        if (empty($packages)) {
            foreach (\App\Models\Package\Package::with('category')->get() as $pkg) {
                $packages[] = ['type' => 'package', 'similarity' => 0, 'data' => array_merge($pkg->toArray(), [
                    'image_url' => $pkg->image_url,
                    'category' => $pkg->category?->toArray(),
                ])];
            }
        }
    } else {
        foreach (\App\Models\Package\Package::with('category')->get() as $pkg) {
            $packages[] = ['type' => 'package', 'similarity' => 0, 'data' => array_merge($pkg->toArray(), ['image_url' => $pkg->image_url, 'category' => $pkg->category?->toArray()])];
        }
        foreach (\App\Models\Product\Product::where('is_active', true)->with('category')->get() as $prd) {
            $products[] = ['type' => 'product', 'similarity' => 0, 'data' => array_merge($prd->toArray(), ['image_url' => $prd->image_url, 'category' => $prd->category?->toArray()])];
        }
    }
@endphp

<div class="mt-4 animate-in fade-in slide-in-from-bottom-4 duration-500">

    {{-- Top Match + Reset Button --}}
    @if(!empty($allResults))
        @php
            $topMatch = collect($allResults)->sortByDesc('similarity')->first();
        @endphp
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-1.5">
                @if($topMatch && ($topMatch['similarity'] ?? 0) > 0)
                    <x-filament::icon icon="heroicon-m-star" class="w-4 h-4" style="color:#facc15;" />
                    <span class="text-xs font-black" style="color:#facc15;">{{ __('Hasil Terbaik') }}</span>
                    <span class="px-2 py-0.5 rounded-full text-[9px] font-black text-gray-900" style="background:#facc15;">
                        {{ number_format($topMatch['similarity'], 1) }}%
                    </span>
                @endif
            </div>
            <x-filament::button
                wire:click="clearVisualSearch"
                color="danger"
                size="xs"
                icon="heroicon-m-arrow-path"
                class="rounded-lg font-bold"
            >
                {{ __('Reset CBIR') }}
            </x-filament::button>
        </div>

        @if($topMatch && ($topMatch['similarity'] ?? 0) > 0)
            <div class="mb-4 p-3 rounded-xl border-2" style="background:#1a1a2e;border-color:#eab308;">
                @include('User.components.cbir-catalog-item.cbir-catalog-item', ['res' => $topMatch, 'orderId' => $orderId ?? null])
            </div>
        @endif
    @endif

    {{-- Semua item digabung dalam satu grid --}}
    @php
        $allItems = array_merge($packages, $products);
        // Sort by similarity descending
        usort($allItems, fn($a, $b) => ($b['similarity'] ?? 0) <=> ($a['similarity'] ?? 0));
    @endphp

    <style>
        .cbir-grid{display:flex;flex-wrap:wrap;gap:6px;padding:4px;}
        .cbir-grid > *{
            flex-shrink:0;
            width:calc(50% - 3px);
            min-width:0;
            border-radius:8px;
            overflow:hidden;
            background:#1a1a2e;
            box-shadow:0 1px 4px rgba(0,0,0,.3);
            display:flex;
            flex-direction:column;
            border:1px solid rgba(255,255,255,.07);
        }
        @media(min-width:481px){.cbir-grid > *{width:calc(25% - 5px);}}
        @media(min-width:769px){.cbir-grid > *{width:calc(20% - 5px);}}
        @media(min-width:1025px){.cbir-grid > *{width:calc(16.666% - 5px);}}
        .cbir-empty{background:#1a1a2e;border:1px dashed rgba(255,255,255,.15);color:#6b7280;width:100%;}
    </style>
    <div class="cbir-grid items-start">
        @foreach($allItems as $res)
            @include('User.components.cbir-catalog-item.cbir-catalog-item', ['res' => $res, 'orderId' => $orderId ?? null])
        @endforeach
        @if(empty($allItems))
            <div class="cbir-empty col-span-2 lg:col-span-4 p-3 text-center text-[10px] rounded-xl">
                {{ __('Kosong') }}
            </div>
        @endif
    </div>

    {{-- Reset Button (bottom) --}}
    @if(!empty($allResults))
    <div class="mt-4">
        <x-filament::button
            wire:click="clearVisualSearch"
            color="gray"
            size="sm"
            icon="heroicon-m-arrow-path"
            class="w-full rounded-xl font-bold"
        >
            {{ __('Tampilkan Semua') }}
        </x-filament::button>
    </div>
    @endif

</div>
