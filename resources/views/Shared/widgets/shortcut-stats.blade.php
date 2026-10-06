@php
    use App\Support\AppPlatform\AppPlatform;

    $heading = $this->getHeading();
    $description = $this->getDescription();
    $hasHeading = filled($heading);
    $hasDescription = filled($description);

    // Slide di SEMUA permukaan: aplikasi shell Android/iOS, browser mobile
    // Android/iOS, tablet, macOS, desktop, dan desktop app.
    //
    // Yang berbeda antarpermukaan hanya JUMLAH KARTU PER HALAMAN, bukan
    // mekanismenya:
    //
    //   HP (mobile shell + browser mobile) -> 1 kartu per halaman. Empat kolom
    //     di lebar 400px cuma berdesakan dan teksnya terpotong.
    //   Sisanya (tablet, macOS, desktop, desktop app) -> 2 kartu per halaman.
    //     Bukan 1: di lebar 1600-1920px satu kartu akan selebar ~1800px,
    //     jadi isinya jadi satu baris raksasa dan justru lebih buruk dari
    //     grid. Dua per halaman menjaga ukuran kartu seperti yang biasa
    //     terlihat, dan row tetap bisa digeser.
    //
    // AppPlatform::isAnyMobile() sudah mencakup mobile shell + browser
    // mobile, jadi tidak perlu UA sniffing sendiri di sini.
    $isPhone = AppPlatform::isAnyMobile();

    $stats = $this->getCachedStats();

    // Lebar satu kartu, ditulis sebagai custom property supaya aturan
    // `flex: 0 0 ...` di Shared.css cukup punya SATU bentuk untuk kedua mode
    // (lihat blok CARA MEMBAWA POSISI KE TITIK-TITIKNYA di bawah).
    //
    // `calc(50% - 0.375rem)` bukan `50%`: gap flex 0.75rem dihitung dua kali
    // kalau kartu selebar 50% penuh, sehingga halaman kedua bergeser 0.75rem
    // dan tidak pernah pas di titik snap. Setengah gap membuat dua kartu
    // memenuhi persis satu lebar track.
    $cardBasis = $isPhone
        ? '100%'
        : 'calc(50% - 0.375rem)';

    // Satu titik per HALAMAN, bukan per kartu. Di HP 4 kartu = 4 halaman,
    // di desktop 4 kartu = 2 halaman.
    $pageCount = max(1, (int) ceil(count($stats) / ($isPhone ? 1 : 2)));
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

        Di mobile: satu kartu per halaman, digeser dengan swipe (lihat
        .shortcut-stats-track di Shared.css). Di selain mobile: grid 4 kolom
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
