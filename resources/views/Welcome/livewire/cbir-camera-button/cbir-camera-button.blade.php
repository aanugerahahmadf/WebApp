<div
    x-data="{
        isCbirMenuOpen: false,
        toggleMenu() {
            this.isCbirMenuOpen = !this.isCbirMenuOpen;
            if (this.isCbirMenuOpen) {
                this.$nextTick(() => this.positionMenu());
            }
        },
        closeMenu() { this.isCbirMenuOpen = false },
        pick(event, detail) {
            this.closeMenu();
            window.dispatchEvent(new CustomEvent(event, { detail }));
        },
        positionMenu() {
            const btn = this.$refs.cbirButton;
            const panel = this.$refs.cbirPanel;
            if (!btn || !panel) return;
            const rect = btn.getBoundingClientRect();
            const panelWidth = panel.offsetWidth || 240;
            const panelHeight = panel.offsetHeight || 220;
            let left = rect.right - panelWidth;
            left = Math.max(8, Math.min(left, window.innerWidth - panelWidth - 8));
            let top = rect.bottom + 8;
            if (top + panelHeight > window.innerHeight - 8) {
                top = Math.max(8, rect.top - panelHeight - 8);
            }
            panel.style.left = left + 'px';
            panel.style.top = top + 'px';
        },
    }"
    class="relative inline-flex items-center"
    x-on:click.outside="closeMenu()"
    x-on:keydown.escape.window="closeMenu()"
>
    @if($isLoading)
        <x-filament::icon icon="/images/icons/refresh.svg" class="animate-spin w-[18px] h-[18px] text-primary-500" />
    @else
        {{-- Pemicu. Membuka dropdown, bukan langsung menjalankan apa pun:
             memilih sumber gambar adalah keputusan pengguna, dan tiap sumber
             punya konsekuensi berbeda (kamera meminta izin perangkat, galeri
             membuka file picker, file membuka document picker). Menu ini
             menampilkan semuanya lebih dulu. --}}
        <button
            type="button"
            id="cbir-camera-btn"
            x-ref="cbirButton"
            x-on:click="toggleMenu()"
            title="{{ __('Pencarian Visual') }}"
            class="inline-flex items-center justify-center w-7 h-7 rounded-md
                   text-gray-400 hover:text-primary-500 dark:hover:text-primary-400
                   transition-all duration-150 active:scale-90 touch-manipulation
                   focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
        >
            <x-filament::icon icon="/images/icons/camera.svg" class="w-[18px] h-[18px]" />
        </button>
    @endif

    {{-- Tiga opsi. Foto Kamera Belakang dan Foto Depan digabung jadi satu
         "Camera Photo": viewfinder di dalam sudah punya tombol Switch Camera
         (flipCamera), dan di desktop sebuah <select> daftar kamera -- jadi
         dua item menu untuk satu kontrol yang sama tidak perlu.

         Rekam video TIDAK ada di sini: modal viewfinder-nya sendiri sudah
         punya toggle Camera Photo / Camera Video, jadi item video di dropdown
         akan dobel dengan kontrol yang sama.

         Label diambil lewat __() dengan string Indonesia sebagai kunci, sama
         seperti switcher bahasa dan tema. Kuncinya sudah ada di id/en/ar
         (id memakai stringnya sendiri sebagai nilai). --}}
    @php
        $cbirMenuItems = [
            [
                'label' => __('Camera Photo'),
                'icon' => '/images/icons/camera.svg',
                'event' => 'cbir-open-webrtc-camera',
                'detail' => (object) [],
            ],
            [
                'label' => __('Pilih dari Galeri'),
                'icon' => '/images/icons/photo.svg',
                'event' => 'cbir-pick-gallery',
                'detail' => (object) [],
            ],
            [
                'label' => __('Pilih dari File'),
                'icon' => '/images/icons/folder.svg',
                'event' => 'cbir-pick-file',
                'detail' => (object) [],
            ],
        ];
    @endphp

    {{-- Panel di-teleport ke <body>, sama seperti switcher bahasa: topbar
         punya overflow:hidden, jadi dropdown di dalamnya akan terpotong.
         Positioned ulang oleh positionMenu() di atas. Class `lang-dd`
         dipakai apa adanya -- Shared.css memperlakukannya sebagai hook
         generik untuk panel dropdown (body.fi-panel-x .lang-dd), sehingga
         kacanya sama persis dengan switcher bahasa dan tema tanpa CSS baru. --}}
    <template x-teleport="body">
        <div
            x-ref="cbirPanel"
            x-show="isCbirMenuOpen"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="lang-dd x-cam-menu fixed rounded-lg shadow-2xl ring-1 bg-white ring-gray-950/10 dark:bg-gray-900 dark:ring-white/20"
            style="z-index:100000; min-width:230px;"
            x-cloak
        >
            <div class="p-1 w-full">
                @foreach ($cbirMenuItems as $cbirItem)
                    <button
                        type="button"
                        x-on:click="pick(@js($cbirItem['event']), @js($cbirItem['detail']))"
                        class="group flex items-center w-full gap-2 whitespace-nowrap rounded-md px-2 py-2 text-sm outline-none transition-all
                               text-gray-700 dark:text-gray-200
                               hover:bg-gray-50 dark:hover:bg-white/5"
                    >
                        <x-filament::icon :icon="$cbirItem['icon']" class="h-5 w-5 shrink-0 text-gray-400 dark:text-gray-500" />
                        <span class="truncate flex-1 text-start">{{ $cbirItem['label'] }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    </template>

    {{-- TakePicture WebRTC: membuka viewfinder kamera di modal, dengan bar mode
         (Belakang / Depan / Video / Galeri). 'Galeri' → cbir-pick-gallery. --}}
    <div class="cbir-take-picture-hidden w-0 h-0" aria-hidden="true">
        {{ $this->form }}
    </div>

    {{-- Menyediakan input file tersembunyi sekaligus listener window event
         (cbir-pick-gallery / cbir-pick-file / cbir-pick-video) yang dipanggil
         oleh item menu di atas. --}}
    @include('Welcome.components.cbir-camera-options.cbir-camera-options', [
        'isNative' => $isNative,
        'cameraAccept' => $cameraAccept,
    ])
</div>
