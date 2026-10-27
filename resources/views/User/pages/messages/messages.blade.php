@php
    /*
     * Breadcrumb diambil lewat $this->getBreadcrumbs(), bukan lewat komponen
     * halaman Filament. Lihat catatan panjang di bawah.
     */
    $messagesBreadcrumbs = $this->getBreadcrumbs();
@endphp

{{--
    HALAMAN MESSAGES -- PENUH, TANPA CHROME HALAMAN FILAMENT.

    Dulu blade ini dibungkus `<x-filament-panels::page>`. Komponen itu
    merender tiga hal yang tidak ada artinya buat halaman percakapan:

      1. `<section class="fi-section">` -- kartu: background, border, radius,
         shadow, dan `py-8` di sekeliling. Di sinilah "latar Filament" yang
         ingin dihilang berada.
      2. `<x-filament-panels::header>` dengan `<h1 class="fi-header-heading">`
         sized `text-3xl`. Judul "Messages" memakan lebar penuh tapi tidak
         menambah informasi apa pun -- panel ini sudah punya topbar dan
         sidebar yang menunjuk ke tempat yang sama.
      3. Bungkus max-width + padding yang membuat halaman terasa seperti
         halaman, bukan seperti aplikasi.

    Yang DIGANTI, bukan hilang:

      - Breadcrumb tetap ada, dirender sendiri lewat komponen resmi
        `<x-filament::breadcrumbs>` -- komponen publik Filament, bukan
        markup racikan sendiri, jadi tampilan dan navigasinya tetap sama
        dengan halaman lain, termasuk alias ikon dari panel.
      - Tombol "Kembali ke Pengaturan" di panel Welcome tetap ada; lihat
        `backToSettingsUrl()` di MessagesPage panel itu.
      - `<x-filament-actions::modals />` TIDAK perlu ditambah di sini.
        Komponen Filament v3 merendernya di dalam `<x-filament-panels::page>`,
        tapi masing-masing komponen Livewire (fm-user-messages,
        fm-welcome-inbox, ...) sudah merendernya sendiri -- dan hanya satu
        yang boleh ada per layar, karena dua salinan akan menghasilkan dua
        modal identik untuk aksi yang sama.

    CSS untuk benar-benar menempel ke tepi (padding horizontal `.fi-main` dan
    dismiss kartu) TIDAK ditulis di sini. Ia ada di resources/css/User/User.css,
    di sebelah aturan `.messages-container` yang sudah ada -- supaya tidak
    ada dua sumber kebenaran untuk halaman yang sama.
--}}
<div class="messages-page">
    {{--
        Baris kepala: breadcrumb.

        TIDAK memakai `hidden sm:block` lagi. Semula itu ikut menyalin perilaku
        header Filament -- yang menyembunyikan breadcrumb di layar sempit -- tapi
        untuk halaman ini itu salah: breadcrumb adalah satu-satunya penanda
        tempat pengguna berada setelah kartu halaman dibuang, dan justru di HP
        paling dibutuhkan. Yang dihemat di sana adalah tinggi, sementara tinggi
        yang hilang cuma satu baris teks tipis.

        TIDAK ada judul halaman di sini. Sempat ditambahkan label "Inbox" untuk
        menggantikan judul besar yang ikut hilang bersama kartu halaman, tapi
        breadcrumb sudah berakhir dengan kata yang sama ("Inbox"), jadi dua-duanya
        hanya mengulang teks yang sama dalam dua ukuran berbeda.
    --}}
        <div
            class="messages-page-head w-full"
            style="box-sizing: border-box; padding-inline: 200px;"
        >
        <div class="messages-page-crumbs-row">
            @if (filled($messagesBreadcrumbs))
                <div class="messages-page-crumbs">
                    <x-filament::breadcrumbs :breadcrumbs="$messagesBreadcrumbs" />
                </div>
            @endif
        </div>
    </div>

    <div id="messages-container" class="messages-container flex flex-col lg:flex-row gap-6 w-full overflow-hidden">
        <div class="flex-1 min-w-0 h-full min-h-0">
            @if ($selectedConversation)
                <livewire:fm-user-messages :selectedConversation="$selectedConversation" />
            @else
                <livewire:fm-user-inbox />
            @endif
        </div>
    </div>



{{--
    Registrasi Alpine untuk gelembung aksi pesan (tahan atau klik kanan pada
    sebuah pesan). Dimuat di level HALAMAN, bukan di dalam komponen Livewire:
    daftar pesan di-poll tiap 2-3 detik, dan script yang ikut di-render ulang
    akan terus dibakar oleh polling itu. `data-navigate-once` bersama penjaga
    di dalam file membuat registrasi ini benar-benar sekali per halaman.
--}}
@include('Shared.components.message-bubble.message-bubble-script')

{{-- Hitung ulang tinggi container mengikuti sisa tinggi layar yang sebenarnya. --}}
@include('Shared.components.messages-page.messages-page-fit')

{{-- 
    Modal kamera (foto & video). Ditaruh di level HALAMAN, bukan di dalam
    komponen Livewire percakapan, karena komponen itu polling tiap 2-3 detik:
    setiap render ulang menduplikasi isi <template x-teleport> sehingga dialog
    kamera menumpuk di <body> dan x-show-nya berebut scope yang sama.
--}}
@include('Shared.components.messages-camera-modal.messages-camera-modal')

{{-- 
    Modal Audio (voice note) & Contact (.vcf), plus satu helper yang dipakai
    ketiganya (kamera, audio, kontak) untuk menaruh berkas hasil tangkapan ke
    FilePond field ttachments di composer. Semua ditaruh di level HALAMAN
    dengan alasan yang sama seperti modal kamera di atas.
--}}
@include('Shared.components.messages-attach-inject.messages-attach-inject')
@include('Shared.components.messages-audio-modal.messages-audio-modal')
@include('Shared.components.messages-contact-modal.messages-contact-modal')
@include('Shared.components.messages-poll-modal.messages-poll-modal')
@include('Shared.components.messages-sticker-modal.messages-sticker-modal')
</div>
