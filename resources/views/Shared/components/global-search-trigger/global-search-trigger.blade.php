{{--
    PEMICU GLOBAL SEARCH — WELCOME & USER (2026-10-27)

    Kotak ini tampil SAMA PERSIS dengan field pencarian di topbar (kapsul
    gelap transparan, ikon kaca pembesar cyan, ikon kamera biru), tetapi ia
    BUKAN input. Klik di mana pun membuka modal `app-global-search` yang
    berisi komponen Livewire GlobalSearch asli milik Filament.

    Kenapa input-nya dipindah ke modal: hasil pencarian bawaan Filament
    berupa dropdown absolut yang menimpa konten di bawah topbar. Pemilik
    meminta hasilnya tampil di MODAL, dengan kotak pencarian yang identik
    dengan tampilan di topbar.

    HANYA SATU instance GlobalSearch di halaman: yang di dalam modal.
    Kalau komponen ini juga me-render Livewire GlobalSearch, akan ada dua
    kolom search yang mendengarkan event yang sama — dan dua tombol kamera
    CBIR, sehingga memilih "Galeri"/"File" bisa membuka dua dialog file
    sekaligus.

    Tombol (bukan input) dipakai supaya seluruh kotak bisa diklik, termasuk
    ikon kamera, dan tetap bisa dioperasikan dengan keyboard (Enter/Space).
--}}
<button
    type="button"
    data-app-global-search-trigger
    x-data
    x-on:click="$dispatch('open-modal', { id: 'app-global-search' })"
    aria-label="{{ __('filament-panels::global-search.field.label') }}"
    aria-haspopup="dialog"
    class="fi-global-search fi-global-search-trigger"
>
    <span class="fi-global-search-trigger-box">
        <span class="fi-global-search-trigger-icon">
            <x-filament::icon
                alias="panels::global-search.field"
                icon="/images/icons/search.svg"
                class="h-5 w-5"
            />
        </span>

        <span class="fi-global-search-trigger-placeholder">
            {{ __('filament-panels::global-search.field.label') }}
        </span>

        <span class="fi-global-search-trigger-camera" aria-hidden="true">
            <x-filament::icon
                icon="/images/icons/camera.svg"
                class="w-[18px] h-[18px]"
            />
        </span>
    </span>
</button>
