{{--
    MENU LAMPIRAN ala WhatsApp Web.

    Dulu ada DUA tombol terpisah di baris composer: paperclip (pilih file) dan
    kamera. Keduanya sekarang hidup di dalam satu menu yang muncul di atas
    tombol "+" -- persis seperti WhatsApp, dan seperti yang sudah biasa dipakai
    pengguna HP yang jadi target utama halaman ini.

    Menu ini sengaja TIDAK memakai komponen Filament, karena Filament tidak
    punya padanan untuk "menu yang menempel ke tombol di dalam baris composer".
    Bentuknya ditulis sendiri, dan styling-nya sengaja gelap di mode terang
    maupun gelap: menu ini tidak pernah terang di WhatsApp, dan konsistensi
    lebih berharga daripada ikut warna panel.

    Semua item di sini sekarang AKTIF: Document, Photos & videos, Camera
    Photo, Camera Video, Audio, Contact, Poll, New sticker -- plus aksi yang
    tadinya ada di header percakapan (Share Catalog, Share Orders, Experience
    Value) untuk panel User dan Welcome.

    Membuka menu: tombol "+" memanggil
    `window.dispatchEvent(new CustomEvent('messages-attach-menu-toggle'))`.
    Peristiwa, bukan state bersama, karena tombolnya dirender oleh Filament
    Forms di dalam `{{ $this->form }}` -- jauh dari elemen ini di DOM.
--}}

{{--
    CATATAN penting soal penulisan atribut Alpine di file ini: SEMUA memakai
    `x-on:`, bukan `@nama-acara`. Bentuk `@nama-acara.window="..."` dimakan
    Blade sebagai direktif PHP -- `@messages-attach-menu-toggle` berakhir
    dikompil menjadi pemanggilan komponen Livewire dengan argumen kosong, dan
    halaman mati dengan "Too few arguments to function ... closure()".

    Prefix `x-on:` menghindari itu sepenuhnya karena Blade tidak mengenali `x-on:`
    sebagai direktif. Aturan yang sama berlaku untuk `@click.outside` dan
    `@keydown.escape`: keduanya kebetulan lolos karena tidak diikuti tanda
    kurung, tapi bentuk `x-on:` dipakai konsisten di sini supaya tidak ada yang
    harus ditebak.
--}}

@php
    /*
     * Panel admin tidak punya aksi "Bagikan Katalog / Bagikan Pesanan / Nilai
     * Pengalaman" (aksinya cuma ada di komponen Messages milik panel User dan
     * Welcome). Karena itu item-item itu hanya ditampilkan kalau pemanggil
     * mengirim `showSharedActions => true`.
     */
    $showSharedActions = $showSharedActions ?? false;

    $attachmentMenuItems = [
        [
            'key' => 'document',
            'label' => __('Document'),
            'icon' => '/images/icons/document.svg',
            'color' => '#60a5fa',
            'event' => 'cbir-browse-open',
        ],
        [
            'key' => 'photos',
            'label' => __('Photos & videos'),
            'icon' => '/images/icons/photo.svg',
            'color' => '#a78bfa',
            // Event terpisah dari `cbir-browse-open`: yang ini langsung
            // membuka pemilih file dengan filter image/*, bukan memunculkan
            // modal jelajah (file/folder/drive/cloud) yang tidak berfilter.
            'event' => 'cbir-browse-pick-image',
        ],
        [
            'key' => 'camera',
            'label' => __('Camera Photo'),
            'icon' => '/images/icons/camera.svg',
            'color' => '#f87171',
            // detail.mode menentukan isi viewfinder: 'photo' = tombol jepret,
            // 'video' = tombol rekam. Satu modal, dua mode -- sama seperti
            // viewfinder di global search.
            'click' => "attachOpen = false; window.dispatchEvent(new CustomEvent('messages-camera-open', { detail: { mode: 'photo' } }))",
        ],
        [
            'key' => 'camera-video',
            'label' => __('Camera Video'),
            'icon' => '/images/icons/video.svg',
            'color' => '#fb923c',
            'click' => "attachOpen = false; window.dispatchEvent(new CustomEvent('messages-camera-open', { detail: { mode: 'video' } }))",
        ],
        [
            'key' => 'audio',
            'label' => __('Audio'),
            'icon' => '/images/icons/microphone.svg',
            'color' => '#34d399',
            // Rekam voice note lewat MediaRecorder, lalu masuk ke FilePond.
            'click' => "attachOpen = false; window.dispatchEvent(new CustomEvent('messages-audio-open'))",
        ],
        [
            'key' => 'contact',
            'label' => __('Contact'),
            'icon' => '/images/icons/user.svg',
            'color' => '#fbbf24',
            // Bagikan kartu kontak (.vcf) lewat FilePond.
            'click' => "attachOpen = false; window.dispatchEvent(new CustomEvent('messages-contact-open'))",
        ],
        [
            'key' => 'poll',
            'label' => __('Poll'),
            'icon' => '/images/icons/chart.svg',
            'color' => '#60a5fa',
            // `attachOpen`, BUKAN `open`. Scope Alpine digabung dengan scope
            // nenek moya, dan `open` milik komponen global `messageBubble`
            // (gelembung aksi pesan) yang lebih dekat -- jadi `open = false`
            // menutup gelembung itu, BUKAN menu lampiran. Efeknya: menu "+"
            // tetap terbuka di atas modal polling.
            'click' => "attachOpen = false; window.dispatchEvent(new CustomEvent('messages-poll-open'))",
        ],
        [
            'key' => 'sticker',
            'label' => __('New sticker'),
            'icon' => '/images/icons/smile.svg',
            'color' => '#f472b6',
            // Sama seperti item Poll di atas: `attachOpen`, bukan `open`.
            'click' => "attachOpen = false; window.dispatchEvent(new CustomEvent('messages-sticker-open'))",
        ],
    ];

    /*
     * Tiga aksi yang tadinya jadi tombol ikon di HEADER percakapan
     * (Bagikan Katalog, Bagikan Pesanan, Nilai Pengalaman). Sekarang mereka
     * pindah ke menu ini supaya header bersih -- hanya tombol Cari yang
     * tersisa di kanan atas.
     *
     * `wire` dipanggil lewat wire:click, bukan `$wire` di ekspresi Alpine,
     * supaya tidak bergantung pada magi `$wire` Livewire di dalam partial
     * yang dipakai bersama.
     */
    if ($showSharedActions) {
        $attachmentMenuItems[] = [
            'key' => 'share-catalog',
            'label' => __('Bagikan Katalog'),
            'icon' => '/images/icons/bag.svg',
            'color' => '#f59e0b',
            'divider' => true,
            'wire' => "mountAction('shareCatalog')",
            'click' => 'attachOpen = false',
        ];

        $attachmentMenuItems[] = [
            'key' => 'share-order',
            'label' => __('Bagikan Pesanan'),
            'icon' => '/images/icons/document.svg',
            'color' => '#38bdf8',
            'wire' => "mountAction('shareOrder')",
            'click' => 'attachOpen = false',
        ];

        $attachmentMenuItems[] = [
            'key' => 'rate-experience',
            'label' => __('Nilai Pengalaman'),
            'icon' => '/images/icons/star.svg',
            'color' => '#f472b6',
            'wire' => "mountAction('rateExperience')",
            'click' => 'attachOpen = false',
        ];
    }
@endphp

<div
    {{--
        PENTING 1 -- `wire:ignore`.

        Komponen percakapan melakukan polling tiap 2-3 detik, dan setiap morph
        Livewire akan mengganti DOM di dalam komponen. Kalau elemen ini ikut
        diganti, scope Alpine-nya ikut mati: efek `x-show` yang sudah
        terdaftar hilang, sehingga menu tidak bisa dibuka -- atau, lebih parah,
        baru muncul belakangan (setelah request berikutnya) di atas gelembung
        pesan.

        Isi menu ini statis, jadi tidak ada yang perlu diperbarui oleh
        Livewire. `wire:click` di dalam area `wire:ignore` tetap berjalan
        normal.

        PENTING 2 -- `x-data` DAN `x-show` DI ELEMEN YANG SAMA.

        Dulu `x-data` ada di elemen pembungkus dan `x-show` di elemen anak.
        Efek x-show anak itu mati: menu tidak pernah terbuka, dan `open` milik
        komponen global `messageBubble` (gelembung aksi pesan) yang menimpa
        state-nya -- jadi menu bisa muncul sendiri di atas gelembung saat
        gelembung aksi dibuka.

        PENTING 3 -- TANPA `x-transition`.

        `x-transition` di elemen yang sama dengan `x-show` membuat efeknya
        macet: visibilitas elemen tidak lagi mengikuti state-nya. Animasi
        boleh dikorbankan demi keandalan.
    --}}
    wire:ignore
    x-data="{ attachOpen: false }"
    x-on:messages-attach-menu-toggle.window="attachOpen = !attachOpen"
    x-on:messages-attach-menu-close.window="attachOpen = false"
    x-on:click.outside="attachOpen = false"
    x-on:keydown.escape.window="attachOpen = false"
    x-on:livewire:navigating.window="attachOpen = false"
    x-cloak
    x-show="attachOpen"
    {{--
        `bottom-full` = `bottom: 100%`, jadi tepi BAWAH menu menempel di tepi
        ATAS wadah composer -- menu selalu membuka ke ATAS, ke arah daftar
        pesan, tidak pernah ke bawah seperti menu biasanya yang menempel ke
        tombol di atas teks.

        Kelas ini sempat hilang dari bundle CSS karena `npm run build:web`
        dijalankan sebelum file ini dibuat. Tanpa `.bottom-full`, elemen ini
        tetap `position: absolute` tapi tanpa pen vertikal, jatuh ke posisi
        statis, dan menu muncul menimpa baris composer. Kalau kelas
        kustom di view ini tiba-tiba tidak berpengaruh setelah build, itu
        sebabnya.

        `max-h` + `overflow-y-auto` adalah pengaman untuk layar pendek: sebelas
        baris punya tinggi tetap, dan lebih baik area isinya yang bisa digulir
        daripada menu meluber keluar area chat (yang punya `overflow-hidden`).
    --}}
    class="lang-dd absolute bottom-full left-0 mb-2 z-30 w-60 max-w-[70vw] max-h-[min(60vh,20rem)] overflow-y-auto origin-bottom-left rounded-lg bg-white py-1 shadow-2xl ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/20"
    role="menu">

        @foreach ($attachmentMenuItems as $item)
            @php
                $disabled = $item['disabled'] ?? false;
                $dispatch = $item['event'] ?? null;
                // Item boleh membawa ekspresi Alpine siap pakai (`click`) kalau
                // event-nya butuh detail. Tanpa itu, bentuk lamanya dipakai.
                $click = $item['click'] ?? ($dispatch ? "attachOpen = false; window.dispatchEvent(new CustomEvent('{$dispatch}'))" : null);
                // Item yang menjalankan aksi Livewire (bukan window event)
                // memakai wire:click supaya tidak bergantung pada magi
                // `$wire` yang tidak selalu ada di partial bersama.
                $wire = $item['wire'] ?? null;
            @endphp

            @if (! empty($item['divider']))
                <div class="my-1 border-t border-gray-100 dark:border-white/5"></div>
            @endif

            <button type="button"
                role="menuitem"
                wire:key="attach-menu-{{ $item['key'] }}"
                @if ($disabled)
                    disabled
                    aria-disabled="true"
                    title="{{ __('Segera hadir') }}"
                @else
                    x-on:click="{{ $click ?? 'attachOpen = false' }}"
                    @if ($wire)
                        wire:click="{{ $wire }}"
                    @endif
                @endif
                @class([
                    'w-full flex items-center gap-3 px-3 py-2.5 transition-colors',
                    'text-gray-950 dark:text-white',
                    'hover:bg-gray-50 dark:hover:bg-white/5' => ! $disabled,
                    'opacity-40 cursor-not-allowed' => $disabled,
                ])>
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg"
                    style="background-color: {{ $item['color'] }}1f; color: {{ $item['color'] }};">
                    <x-filament::icon :icon="$item['icon']" class="h-[18px] w-[18px]" />
                </span>
                <span class="text-sm">{{ $item['label'] }}</span>
            </button>
        @endforeach
</div>
