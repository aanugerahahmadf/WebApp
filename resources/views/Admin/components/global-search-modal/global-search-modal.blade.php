{{--
    PENCARIAN GLOBAL ADMIN SEBAGAI MODAL (2026-10-26)

    Permintaan pemilik: saat kotak pencarian di sidebar diklik, hasilnya
    harus muncul di MODAL -- bukan dropdown -- karena dropdown bawaan
    menimpa item navigasi sidebar sehingga teksnya bertumpuk.

    Cara kerja:
      - Tombol pemicu ada di sidebar (lihat override sidebar/index.blade.php,
        `[data-admin-global-search-trigger]`). Tombol itu mengirim event
        Alpine `open-modal` dengan id `admin-global-search`.
      - Modal ini didaftarkan di AdminPanelProvider lewat hook BODY_END.
        BODY_END dipakai supaya modal berada langsung di bawah <body>,
        BUKAN di dalam <aside class="fi-sidebar">. Sidebar punya
        `transform` (translate-x) sehingga anak `position: fixed` akan
        diposisikan relatif terhadap sidebar, bukan viewport.
      - Isi modal adalah komponen Livewire GlobalSearch bawaan Filament:
        field + hasil. Jadi debounce, pencarian per resource, dan navigasi
        panah tetap sama seperti sebelumnya.

    POSISI DI MOBILE (2026-10-27):
    Pemilik meminta modalnya "pas di Search" -- bukan kotak kecil di
    tengah layar. Karena itu, di layar <=640px jendela modal diturunkan
    dari tengah ke posisi kotak Search di sidebar (lihat
    public/css/panel-glass.css, blok "Pencarian global Admin di ponsel").
    Padding atas 5rem = header sidebar (4rem) + jarak atas kotak Search
    (1rem), dan padding samping 1.5rem sama dengan padding sidebar.
    Hasilnya jendela modal menutupi kotak Search dengan ukuran yang sama.

    TIDAK ADA tombol close di dalam modal (permintaan pemilik). Menutup
    modal: klik area gelap di luar jendela, atau tekan Escape. Itu
    perilaku bawaan `close-by-clicking-away` / `close-by-escaping`
    milik Filament.

    Fokus otomatis: event `open-modal` ditangkap lagi di sini untuk
    memfokuskan input setelah transisi modal (300ms) selesai. Tanpa ini
    pengguna harus mengklik input sekali lagi.

    Catatan: jangan menambahkan `@livewire(Filament\Livewire\GlobalSearch::class)`
    di tempat lain. Satu panel cukup satu instance; dua instance akan
    mendengarkan event yang sama dan hasilnya bisa dobel.
--}}
<x-filament::modal
    id="admin-global-search"
    width="2xl"
    class="fi-admin-global-search-dialog"
    :extra-modal-window-attribute-bag="new \Illuminate\View\ComponentAttributeBag(['class' => 'fi-admin-global-search-window'])"
    :close-button="false"
    :close-by-clicking-away="true"
    :close-by-escaping="true"
>
    <div
        class="fi-admin-global-search-modal relative"
        x-on:open-modal.window="
            if ($event.detail?.id !== 'admin-global-search') {
                return
            }

            setTimeout(() => {
                $el.querySelector('.fi-global-search-field input')?.focus()
            }, 350)
        "
    >
        @livewire(Filament\Livewire\GlobalSearch::class)
    </div>
</x-filament::modal>
