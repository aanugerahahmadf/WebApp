@if (auth()->check())
    <div class="flex items-center px-3 py-1">
        <x-filament::badge
            color="warning"
            icon="/images/icons/wallet.svg"
            size="lg"
            class="rounded-full font-bold"
        >
            Rp {{ number_format(auth()->user()->balance ?? 0, 0, ',', '.') }}
        </x-filament::badge>
    </div>
@endif
