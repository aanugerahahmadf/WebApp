@php
    use App\Support\AppPlatform\AppPlatform;

    $heading = $this->getHeading();
    $description = $this->getDescription();
    $hasHeading = filled($heading);
    $hasDescription = filled($description);

    // SEMUA platform (mobile, tablet, desktop, desktop app) pakai
    // 1 kartu per halaman + carousel swipe. Sama persis seperti mobile app.
    $isPhone = true; // selalu true -> 1 kartu per halaman

    $stats = $this->getCachedStats();

    // Lebar kartu selalu 100% (1 per halaman)
    $cardBasis = '100%';

    // 1 halaman = 1 kartu, jadi pageCount = jumlah kartu (4)
    $pageCount = count($stats);
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
        Track-nya bukan carousel berbasis JS — scroll-snap CSS yang
        menggesernya, jadi tetap jalan walau Alpine belum hydrate.

        KACA KARTU — sama persis dengan widget StatsOverview. Kartu di
        sini memakai kelas .fi-wi-stats-overview-stat yang persis sama,
        jadi keduanya ditentukan oleh satu aturan di panel-glass.css
        (bg 20% + tint 50% + blur 20px). Tidak ada aturan kaca terpisah
        per widget; angka kacanya hanya ada di panel-glass.css.

        Di mobile: satu kartu per halaman, digeser dengan swipe (lihat
        .shortcut-stats-track di Shared.css). Di luar mobile: grid 4 kolom
        seperti biasa.

        CARA MEMBAWA POSISI KE TITIK-TITIKNYA — dan kenapa bukan `x-data`
        bersama seperti biasa.

        Versi lama meletakkan satu `x-data="{ snap: 0 }"` di elemen paling luar
        dan menulisnya dari `@scroll` di track, sementara titik-titiknya
        membacanya dari `:class`. Itu bikin `snap` dibaca dari scope
        KAKEK-nya.

        Livewire morph menambah/mengganti node lalu memanggil
        `Alpine.mutateDom`, yang menginisialisasi ULANG hanya subtree yang
        berubah. Kalau subtree itu adalah titik-titiknya, walk-nya turun dari
        situ -- elemen `x-data` di kakeknya tidak ikut di-walk, jadi
        `_x_dataStack` yang diwarisi kosong dan `snap` jadi tidak ter-resolve:

            Uncaught ReferenceError: snap is not defined

        Yakinkan itu yang terjadi di perangkat Anda: error-nya muncul dari
        `wl @ morph.js:65` -> `supportMorphDom.js:13`, yaitu dari dalam
        morph -- bukan dari render awal.

        Perbaikannya dua lapis, keduanya di bawah:
          1. Titik-titik punya `x-data`-NYA SENDIRI, jadi scope-nya ikut
             dibawa saat markup-nya dimorph, bukan bergantung pada leluhur.
          2. `@scroll` mengirim event window, bukan menulis variabel Alpine
             milik elemen lain.
        Dan pembacanya dijaga `typeof`, jadi kalau ada morph yang managesih
        lolos, akibatnya hanya "titik belum ter-highlight" sesaat -- bukan
        exception yang berulang.
    --}}
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

        {{-- Titik penanda posisi: satu-satunya petunjuk bahwa row itu bisa
             digeser, dan juga penanda halaman mana yang sedang tampil.

             Satu titik per HALAMAN ($pageCount), bukan per kartu: di desktop
             4 kartu jadi 2 halaman, jadi 4 titik akan menyesatkan -- satu
             titik untuk setiap kartu yang tidak bisa jadi satu halaman.

             `.window` wajib: posisinya SEBERANG track, jadi event dari
             track tidak akan sampai ke sini tanpa bubbles -- dan
             `$dispatch` memang memakai CustomEvent yang bubbles. --}}
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
    </div>
</x-filament-widgets::widget>
