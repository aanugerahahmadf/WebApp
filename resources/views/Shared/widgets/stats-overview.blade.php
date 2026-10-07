@php
    $heading = $this->getHeading();
    $description = $this->getDescription();
    $hasHeading = filled($heading);
    $hasDescription = filled($description);

    $stats = $this->getCachedStats();

    // SAMA persis seperti shortcut-stats: 1 kartu per halaman,
    // carousel swipe di SEMUA platform (mobile, tablet, desktop,
    // desktop app). Kacanya ikut token --fi-glass-* dari
    // panel-glass.css, sama dengan widget ShortcutStats: kartu Stat ini
    // memakai kelas .fi-wi-stats-overview-stat yang persis sama dengan
    // kartu ShortcutStats, jadi tidak ada aturan kaca terpisah per
    // widget. Angka kacanya (bg 20% + tint 50% + blur 20px) hanya ada
    // di panel-glass.css -- jangan ditulis di sini.
    $cardBasis = '100%';
    $pageCount = max(1, count($stats));
@endphp

<x-filament-widgets::widget class="fi-wi-stats-overview user-home-stats grid gap-y-4">
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

    <div>
        <div
            @if ($pollingInterval = $this->getPollingInterval())
                wire:poll.{{ $pollingInterval }}
            @endif
            class="fi-wi-stats-overview-stats-ctn grid gap-6 shortcut-stats-slide shortcut-stats-track"
            style="--shortcut-stats-page: {{ $cardBasis }}; display: flex; width: 100%; gap: 0.75rem; overflow-x: auto; overscroll-behavior-x: contain; scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch;"
            @scroll="$dispatch('shortcut-snap', { index: Math.round($event.target.scrollLeft / ($event.target.clientWidth || 1)) })"
        >
            @foreach ($stats as $stat)
                {{ $stat }}
            @endforeach
        </div>

        @if ($pageCount > 1)
        <div
            class="mt-2.5 flex items-center justify-center gap-1.5"
            aria-hidden="true"
            x-data="{ snap: 0 }"
            @shortcut-snap.window="snap = $event.detail.index"
        >
            @for ($page = 0; $page < $pageCount; $page++)
                <span
                    class="h-1.5 rounded-full transition-all duration-200"
                    :class="typeof snap !== 'undefined' && snap === {{ $page }}
                        ? 'w-4 bg-primary-500'
                        : 'w-1.5 bg-gray-300 dark:bg-gray-600'"
                ></span>
            @endfor
        </div>
        @endif
    </div>
</x-filament-widgets::widget>