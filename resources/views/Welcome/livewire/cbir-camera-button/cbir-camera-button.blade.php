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
        <svg class="animate-spin w-[18px] h-[18px] text-primary-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
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
            <svg xmlns="http://www.w3.org/2000/svg" class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
            </svg>
        </button>
    @endif

    {{-- Empat opsi. Foto Kamera Belakang dan Foto Depan digabung jadi satu
         "Ambil Foto": viewfinder di dalam sudah punya tombol Switch Camera
         (flipCamera), dan di desktop sebuah <select> daftar kamera -- jadi
         dua item menu untuk satu kontrol yang sama tidak perlu.

         Label diambil lewat __() dengan string Indonesia sebagai kunci, sama
         seperti switcher bahasa dan tema. Kuncinya sudah ada di id/en/ar
         (id memakai stringnya sendiri sebagai nilai). --}}
    @php
        $cbirMenuItems = [
            [
                'label' => __('Kamera'),
                'icon' => 'heroicon-o-camera',
                'event' => 'cbir-open-webrtc-camera',
                'detail' => (object) [],
            ],
            [
                // Membuka modal yang SAMA dalam mode rekam, bukan file picker
                // video bawaan sistem: yang ini menghasilkan file dari
                // MediaRecorder dan langsung masuk ke alur CBIR lewat
                // $wire.upload('cameraUpload', ...) di confirmVideo().
                'label' => __('Rekam Video'),
                'icon' => 'heroicon-o-video-camera',
                'event' => 'cbir-open-webrtc-camera',
                'detail' => ['mode' => 'video'],
            ],
            [
                'label' => __('Pilih dari Galeri'),
                'icon' => 'heroicon-o-photo',
                'event' => 'cbir-pick-gallery',
                'detail' => (object) [],
            ],
            [
                'label' => __('Pilih dari File'),
                'icon' => 'heroicon-o-folder',
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
