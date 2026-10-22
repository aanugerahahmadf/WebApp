@php
    $allResults = session('cbir_mixed_results', []);
    $context    = session('cbir_context');

    $results = $context
        ? collect($allResults)->filter(fn ($r) => ($r['type'] ?? '') === $context)->values()->all()
        : $allResults;

    $packages = [];
    $catalogs = [];

    if (!empty($results)) {
        $packages = collect($results)->where('type', 'package')->all();
        $catalogs = collect($results)->where('type', 'catalog')->all();
    } else {
        foreach (\App\Models\Package\Package::all() as $pkg) {
            $packages[] = [
                'type' => 'package',
                'similarity' => 0,
                'data' => [
                    'id' => $pkg->id,
                    'name' => $pkg->name,
                    'price' => $pkg->price,
                    'discount_price' => $pkg->discount_price,
                    'image_url' => $pkg->image_url,
                    'category' => $pkg->category ? ['name' => $pkg->category->name] : null,
                ]
            ];
        }
        foreach (\App\Models\Catalog\Catalog::where('is_active', true)->get() as $prd) {
            $catalogs[] = [
                'type' => 'catalog',
                'similarity' => 0,
                'data' => [
                    'id' => $prd->id,
                    'name' => $prd->name,
                    'price' => $prd->price,
                    'discount_price' => $prd->discount_price,
                    'image_url' => $prd->image_url,
                    'category' => $prd->category ? ['name' => $prd->category->name] : null,
                ]
            ];
        }
    }
@endphp

<div class="mt-4">
    <div class="grid grid-cols-2 gap-4 items-start">
        
        {{-- Packages Column --}}
        <div class="space-y-4">
            <div class="flex items-center gap-2 px-1">
                <x-filament::icon icon="/images/icons/gift.svg" class="w-5 h-5 text-primary-500" />
                <h3 class="text-sm font-black text-gray-900 dark:text-white uppercase tracking-tight">{{ __('Paket') }}</h3>
            </div>
            
            <div class="space-y-3">
                @forelse($packages as $res)
                    @include('User.components.cbir-item-card.cbir-item-card', [
                        'res' => $res,
                        'orderId' => $orderId ?? null
                    ])
                @empty
                    <div class="p-4 text-center text-xs text-gray-400 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-dashed border-gray-200 dark:border-gray-700">
                        {{ __('Kosong') }}
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Catalogs Column --}}
        <div class="space-y-4">
            <div class="flex items-center gap-2 px-1">
                <x-filament::icon icon="/images/icons/bag.svg" class="w-5 h-5 text-amber-500" />
                <h3 class="text-sm font-black text-gray-900 dark:text-white uppercase tracking-tight">{{ __('Katalog') }}</h3>
            </div>

            <div class="space-y-3">
                @forelse($catalogs as $res)
                    @include('User.components.cbir-item-card.cbir-item-card', [
                        'res' => $res,
                        'orderId' => $orderId ?? null
                    ])
                @empty
                    <div class="p-4 text-center text-xs text-gray-400 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-dashed border-gray-200 dark:border-gray-700">
                        {{ __('Kosong') }}
                    </div>
                @endforelse
            </div>
        </div>

    </div>

    @if(!empty($allResults))
    <div class="mt-8">
        <x-filament::button
            wire:click="clearVisualSearch"
            color="gray"
            size="lg"
            icon="/images/icons/refresh.svg"
            class="w-full rounded-2xl font-bold"
        >
            {{ __('Bersihkan Filter') }}
        </x-filament::button>
    </div>
    @endif
</div>
