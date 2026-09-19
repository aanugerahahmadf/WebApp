@props([
    'category' => '',
    'name'     => '',
    'weddingFlowersDecorasi'       => null,
    'price'    => '',
])

<div class="flex flex-col gap-0.5">
    @if($category)
        <p class="text-[10px] font-bold text-primary-500 uppercase tracking-wider">
            {{ $category }}
        </p>
    @endif

    <p class="font-bold text-sm text-gray-900 dark:text-white leading-tight">
        {{ $name }}
    </p>

    @if($weddingFlowersDecorasi)
        <p class="text-[10px] text-gray-400 flex items-center gap-1 mt-0.5">
            🏢 {{ $weddingFlowersDecorasi }}
        </p>
    @endif

    <p class="font-extrabold text-primary-600 text-sm mt-1">
        {{ $price }}
    </p>
</div>
