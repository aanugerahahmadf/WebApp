@php
    $heading = $this->getHeading();
    $description = $this->getDescription();
    $hasHeading = filled($heading);
    $hasDescription = filled($description);
@endphp

<x-filament-widgets::widget class="fi-wi-stats-overview user-home-shortcuts grid gap-y-4">
    @if ($hasHeading || $hasDescription)
        <div class="fi-wi-stats-overview-header grid gap-y-1">
            @if ($hasHeading)
                <h3
                    class="fi-wi-stats-overview-header-heading col-span-full text-base font-semibold leading-6 text-gray-950 dark:text-white"
                >
                    {{ $heading }}
                </h3>
            @endif

            @if ($hasDescription)
                <p
                    class="fi-wi-stats-overview-header-description overflow-hidden break-words text-sm text-gray-500 dark:text-gray-400"
                >
                    {{ $description }}
                </p>
            @endif
        </div>
    @endif

    {{--
        Kolom ditulis inline (bukan via CSS file) supaya selalu 4 sebaris
        | My Orders | Favorite | Active Voucher | Cart |
        di semua platform tanpa tergantung stylesheet yang ke-load.
    --}}
    <div
        @if ($pollingInterval = $this->getPollingInterval())
            wire:poll.{{ $pollingInterval }}
        @endif
        class="fi-wi-stats-overview-stats-ctn grid gap-6"
        style="display: grid; width: 100%; gap: 0.875rem; grid-template-columns: repeat(4, minmax(0, 1fr)) !important;"
    >
        @foreach ($this->getCachedStats() as $stat)
            {{ $stat }}
        @endforeach
    </div>
</x-filament-widgets::widget>
