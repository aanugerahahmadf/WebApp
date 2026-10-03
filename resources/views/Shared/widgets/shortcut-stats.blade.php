@php
    use App\Support\AppPlatform\AppPlatform;

    $heading = $this->getHeading();
    $description = $this->getDescription();
    $hasHeading = filled($heading);
    $hasDescription = filled($description);

    // Animasi slide hanya di permukaan mobile: aplikasi shell Android/iOS dan
    // browser mobile Android/iOS. Di tablet/macOS/desktop/desktop app kartu
    // muncul diam -- dipoles per platform, bukan sekali untuk semua.
    //
    // AppPlatform::isAnyMobile() sudah mencakup keduanya (mobile shell +
    // browser mobile), jadi tidak perlu UA sniffing sendiri di sini.
    $isMobile = AppPlatform::isAnyMobile();

    $stats = $this->getCachedStats();
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
        x-data hanya punya tugas satu:DACAT card aktif untuk titik-titik di
        bawahnya. Track-nya sendiri bukan carousel berbasis JS — scroll-snap
        CSS yang menggesernya, jadi tetap jalan walau Alpine belum hydrate.

        Di mobile: satu kartu per halaman, digeser dengan swipe (lihat
        .shortcut-stats-track di Shared.css). Di selain mobile: grid 4 kolom
        seperti biasa.
    --}}
    <div x-data="{ snap: 0 }">
        <div
            @if ($pollingInterval = $this->getPollingInterval())
                wire:poll.{{ $pollingInterval }}
            @endif
            class="fi-wi-stats-overview-stats-ctn grid gap-6{{ $isMobile ? ' shortcut-stats-slide shortcut-stats-track' : '' }}"
            style="{{ $isMobile
                ? 'display: flex; width: 100%; gap: 0.75rem; overflow-x: auto; overscroll-behavior-x: contain; scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch;'
                : 'display: grid; width: 100%; gap: 0.875rem; grid-template-columns: repeat(4, minmax(0, 1fr)) !important;' }}"
            @scroll="snap = Math.round($event.target.scrollLeft / ($event.target.clientWidth || 1))"
        >
            @foreach ($stats as $stat)
                {{ $stat }}
            @endforeach
        </div>

        @if ($isMobile)
            {{-- Titik penanda posisi: satu-satunya petunjuk bahwa row itu bisa
                 digeser, dan juga penanda halaman mana yang sedang tampil. --}}
            <div class="mt-2.5 flex items-center justify-center gap-1.5" aria-hidden="true">
                @foreach ($stats as $index => $stat)
                    <span
                        class="h-1.5 rounded-full transition-all duration-200"
                        :class="snap === {{ $index }} ? 'w-4 bg-primary-500' : 'w-1.5 bg-gray-300 dark:bg-gray-600'"
                    ></span>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
