@php
    $name = $name ?? '';
    $price = $price ?? '';
    $url = $url ?? '';
@endphp

<div class="space-y-4">
    <div class="rounded-xl border border-white/10 bg-white/5 p-4">
        <div class="text-lg font-bold text-gray-900 dark:text-white">{{ $name }}</div>
        <div class="text-sm font-semibold text-success-600 dark:text-success-400 mt-0.5">{{ $price }}</div>
    </div>

    <div>
        <label class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Link') }}</label>
        <div class="flex items-center gap-2 mt-1">
            <input
                type="text"
                readonly
                value="{{ $url }}"
                class="flex-1 rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-gray-700 dark:text-gray-200 outline-none"
                onclick="this.select()"
            >
            <button
                type="button"
                onclick="navigator.clipboard.writeText('{{ $url }}').then(() => { this.textContent = '{{ __("Copied!") }}'; setTimeout(() => { this.textContent = '{{ __("Copy") }}'; }, 1500); })"
                class="shrink-0 rounded-lg bg-primary-600 hover:bg-primary-500 px-3 py-2 text-sm font-medium text-white transition-colors"
            >
                {{ __('Copy') }}
            </button>
        </div>
    </div>

    <a
        href="{{ $url }}"
        target="_blank"
        rel="noopener noreferrer"
        class="flex items-center justify-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm font-medium text-primary-600 dark:text-primary-400 hover:bg-white/10 transition-colors"
    >
        <span>@svg('heroicon-m-arrow-top-right-on-square', 'w-4 h-4')</span>
        {{ __('Buka di Tab Baru') }}
    </a>
</div>