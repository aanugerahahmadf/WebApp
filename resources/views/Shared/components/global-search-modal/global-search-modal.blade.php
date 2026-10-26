{{--
    MODAL GLOBAL SEARCH — WELCOME & USER (2026-10-27)

    Permintaan pemilik: ketika kotak pencarian di topbar diklik, pencarian
    harus terbuka sebagai MODAL, dan kotak pencarian di dalamnya harus
    tampil sama persis dengan kotak di topbar (kapsul gelap, ikon kaca
    pembesar cyan, ikon kamera biru).

    Isi modal adalah komponen Livewire GlobalSearch bawaan Filament, jadi
    debounce, pencarian per resource, navigasi keyboard, dan tombol kamera
    CBIR tetap sama. Yang berubah hanya permukaannya: hasil tidak lagi
    berupa dropdown absolut di bawah topbar, melainkan bagian dari modal
    (lihat public/css/panel-glass.css, blok "Global Search sebagai modal").

    Kenapa dirender dari hook BODY_END: topbar memakai `backdrop-filter`,
    yang menjadikan dirinya containing block untuk anak `position: fixed`.
    Modal yang diletakkan di dalam topbar akan menyempit/terpotong. Di
    BODY_END ia berdiri langsung di bawah <body>, seperti modal Filament
    lain.

    HANYA SATU instance GlobalSearch: topbar memakai partial
    Shared.components.global-search-trigger (tombol), bukan komponen
    Livewire. Dua instance akan mendengarkan event kamera CBIR yang sama.
--}}
@if (filament()->isGlobalSearchEnabled())
    <x-filament::modal
        id="app-global-search"
        width="lg"
        :close-button="false"
        :close-by-clicking-away="true"
        :close-by-escaping="true"
        :extra-modal-window-attribute-bag="new \Illuminate\View\ComponentAttributeBag(['class' => 'fi-global-search-modal-window'])"
    >
        <div
            class="fi-global-search-modal"
            x-on:open-modal.window="
                if ($event.detail?.id !== 'app-global-search') {
                    return
                }

                setTimeout(() => {
                    $el.querySelector('.fi-global-search-field input')?.focus()
                }, 320)
            "
        >
            @livewire(Filament\Livewire\GlobalSearch::class)
        </div>
    </x-filament::modal>
@endif
