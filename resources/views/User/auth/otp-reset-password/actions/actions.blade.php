@php
    $livewire = (isset($getLivewire) && is_callable($getLivewire)) ? $getLivewire() : ($this ?? null);
    $actions = $livewire ? $livewire->getCachedFormActions() : [];
@endphp

<div class="w-full flex flex-col gap-3 pt-3 border-t border-gray-100 dark:border-gray-800">
    @if (count($actions))
        <div class="w-full">
            <x-filament::actions
                :actions="$actions"
                :full-width="true"
            />
        </div>
    @endif

    <div class="w-full text-center mt-2">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('Sudah ingat kata sandi Anda?') }}
            <x-filament::link :href="filament()->getLoginUrl()" color="primary" class="font-semibold ml-1">
                {{ __('Masuk') }}
            </x-filament::link>
        </p>
    </div>
</div>
