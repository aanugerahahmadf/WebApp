@php
    // CSS selector for the FilePond wrapper — passed from parent
    $pondSelector = $pondSelector ?? '.avatar-upload-centered';
@endphp

{{--
    Avatar Browse Modal — all platforms (Windows, Mac, Android, iOS, desktop & mobile app).

    Platform logic (client-side only):
      • Desktop (≥1024px, non-mobile UA): modal always opens so the user can choose
        Camera (WebRTC) · Gallery/Files (file dialog) — FilePond alone can't open the camera.
      • Mobile web (Android/iOS browser) + NativePHP app: same modal with three options.

    Camera on desktop = WebRTC getUserMedia → live preview → capture → PNG file → FilePond.
    Camera on mobile  = <input capture="environment"> → native camera app → FilePond.
--}}
<div
    x-data="{
        isOpen: false,
        showCamera: false,

        /* ── camera state ── */
        stream: null,
        facingMode: 'user',
        photoTaken: false,

        open() {
            this.isOpen = true;
            this.showCamera = false;
            this.photoTaken = false;
            document.body.classList.add('overflow-hidden');
        },

        close() {
            this.stopCamera();
            this.isOpen = false;
            this.showCamera = false;
            document.body.classList.remove('overflow-hidden');
        },

        isMobile() {
            return /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
        },

        isIOS() {
            return /iPhone|iPad|iPod/i.test(navigator.userAgent);
        },

        isAndroid() {
            return /Android/i.test(navigator.userAgent);
        },

        isMac() {
            return /Macintosh|Mac OS X/i.test(navigator.userAgent) && !/iPhone|iPad|iPod/i.test(navigator.userAgent);
        },

        /* ── camera (WebRTC, desktop + web) ── */
        async startCamera() {
            this.showCamera = true;
            this.photoTaken = false;
            await this.$nextTick();
            const video = this.$refs.camVideo;
            if (!video) return;
            try {
                if (this.stream) { this.stream.getTracks().forEach(t => t.stop()); }
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: this.facingMode, width: { ideal: 1280 }, height: { ideal: 720 } },
                    audio: false,
                });
                video.srcObject = this.stream;
                await video.play();
            } catch (e) {
                alert('{{ __('Tidak dapat mengakses kamera. Pastikan izin kamera diberikan.') }}');
                this.showCamera = false;
            }
        },

        stopCamera() {
            if (this.stream) {
                this.stream.getTracks().forEach(t => t.stop());
                this.stream = null;
            }
        },

        flipCamera() {
            this.facingMode = this.facingMode === 'user' ? 'environment' : 'user';
            this.startCamera();
        },

        capturePhoto() {
            const video = this.$refs.camVideo;
            const canvas = this.$refs.camCanvas;
            if (!video || !canvas) return;
            canvas.width  = video.videoWidth  || 640;
            canvas.height = video.videoHeight || 480;
            canvas.getContext('2d').drawImage(video, 0, 0);
            this.stopCamera();
            this.photoTaken = true;
        },

        retakePhoto() {
            this.photoTaken = false;
            this.startCamera();
        },

        usePhoto() {
            const canvas = this.$refs.camCanvas;
            if (!canvas) return;
            canvas.toBlob(blob => {
                if (!blob) return;
                const file = new File([blob], 'avatar-' + Date.now() + '.png', { type: 'image/png' });
                this.injectFile(file);
                this.close();
            }, 'image/png', 0.92);
        },

        /* ── file picker ── */
        pickNativeCamera() {
            this.close();
            setTimeout(() => {
                const el = document.getElementById('avatar-input-native-camera');
                if (el) el.click();
            }, 100);
        },

        pickGallery() {
            this.close();
            setTimeout(() => {
                const el = document.getElementById('avatar-input-gallery');
                if (el) el.click();
            }, 100);
        },

        pickDrive() {
            // Android Chrome: file picker akan menampilkan Google Drive sebagai sumber
            // Desktop Windows/Mac: file picker biasa (bisa navigasi ke folder mana saja)
            this.close();
            setTimeout(() => {
                const el = document.getElementById('avatar-input-drive');
                if (el) el.click();
            }, 100);
        },

        pickICloud() {
            // iOS Safari: file picker akan menampilkan iCloud Drive
            // macOS Safari: file picker biasa + akses iCloud Drive via folder
            this.close();
            setTimeout(() => {
                const el = document.getElementById('avatar-input-icloud');
                if (el) el.click();
            }, 100);
        },

        onPicked(event) {
            const file = event.target.files?.[0];
            if (file) this.injectFile(file);
            event.target.value = '';
        },

        /* ── FilePond injection ── */
        injectFile(file) {
            if (!file) return;
            const pondEl = document.querySelector('{{ $pondSelector }} .filepond--root');
            if (pondEl && window.FilePond) {
                const inst = window.FilePond.find(pondEl);
                if (inst) { inst.addFile(file); return; }
            }
            const fp = document.querySelector('{{ $pondSelector }} input[type=file].avatar-file-input')
                    || document.querySelector('{{ $pondSelector }} input[type=file]');
            if (fp) {
                const dt = new DataTransfer();
                dt.items.add(file);
                fp.files = dt.files;
                fp.dispatchEvent(new Event('change', { bubbles: true }));
            }
        },
    }"
    x-on:open-avatar-browse.window="open()"
    x-on:keydown.escape.window="if (isOpen) { if (showCamera) { stopCamera(); showCamera = false; } else { close(); } }"
    x-on:livewire:navigating.window="close()"
    class="contents"
>
    {{-- Hidden file inputs — di luar teleport agar selalu ada di DOM --}}
    {{-- Native camera (mobile) --}}
    <input type="file" accept="image/*" capture="environment" class="sr-only" id="avatar-input-native-camera" x-on:change="onPicked($event)">
    {{-- Gallery / Photos --}}
    <input type="file" accept="image/*" class="sr-only" id="avatar-input-gallery" x-on:change="onPicked($event)">
    {{-- Google Drive: Android Chrome menampilkan Drive di picker ini --}}
    <input type="file" accept="image/*" class="sr-only" id="avatar-input-drive" x-on:change="onPicked($event)">
    {{-- iCloud / Files: iOS Safari menampilkan iCloud Drive di picker ini --}}
    <input type="file" accept="image/*" class="sr-only" id="avatar-input-icloud" x-on:change="onPicked($event)">

    <template x-teleport="body">
        {{-- Backdrop --}}
        <div
            x-show="isOpen"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-on:click.self="close()"
            class="fixed inset-0 z-[9999] flex items-end justify-center bg-gray-950/50 p-3 pb-[calc(0.75rem+env(safe-area-inset-bottom,0px))] dark:bg-gray-950/75 sm:items-center sm:p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="avatar-browse-title"
            style="display:none;"
        >
            <div
                x-show="isOpen"
                x-on:click.stop
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                class="w-full max-w-sm overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
            >
                {{-- ── Header ── --}}
                <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-white/10">
                    <h3 id="avatar-browse-title" class="text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        <span x-show="!showCamera">{{ __('Pilih Sumber Foto') }}</span>
                        <span x-show="showCamera" style="display:none;">{{ __('Ambil Foto') }}</span>
                    </h3>
                    <div class="flex items-center gap-1">
                        {{-- Flip camera (only in camera view) --}}
                        <button
                            type="button"
                            x-show="showCamera"
                            x-on:click="flipCamera()"
                            class="relative flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 outline-none transition duration-75 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/5 dark:hover:text-gray-300"
                            aria-label="{{ __('Balik Kamera') }}"
                            style="display:none;"
                        >
                            <x-filament::icon icon="heroicon-m-arrow-path" class="h-5 w-5" />
                        </button>
                        {{-- Back (in camera view) --}}
                        <button
                            type="button"
                            x-show="showCamera"
                            x-on:click="stopCamera(); showCamera = false; photoTaken = false;"
                            class="relative flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 outline-none transition duration-75 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/5 dark:hover:text-gray-300"
                            aria-label="{{ __('Kembali') }}"
                            style="display:none;"
                        >
                            <x-filament::icon icon="heroicon-m-arrow-left" class="h-5 w-5" />
                        </button>
                        {{-- Close --}}
                        <button
                            type="button"
                            x-on:click="close()"
                            class="relative -m-1.5 flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 outline-none transition duration-75 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/5 dark:hover:text-gray-300"
                            aria-label="{{ __('Tutup') }}"
                        >
                            <x-filament::icon icon="heroicon-m-x-mark" class="h-5 w-5" />
                        </button>
                    </div>
                </div>

                {{-- ── Source picker (default view) ── --}}
                <div x-show="!showCamera" class="space-y-1 px-4 py-3">

                    {{-- Camera (WebRTC on desktop, native on mobile) --}}
                    <button
                        type="button"
                        x-on:click="isMobile() ? pickNativeCamera() : startCamera()"
                        class="group flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium outline-none transition duration-75 hover:bg-gray-50 focus-visible:bg-gray-50 active:bg-gray-100 dark:hover:bg-white/5 dark:active:bg-white/10"
                    >
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-50 text-primary-600 ring-1 ring-primary-600/10 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/20">
                            <x-filament::icon icon="heroicon-o-camera" class="h-5 w-5" />
                        </span>
                        <span class="flex-1 text-left text-gray-700 dark:text-gray-200">{{ __('Kamera') }}</span>
                        <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4 shrink-0 text-gray-400 group-hover:text-gray-600 dark:text-gray-500 dark:group-hover:text-gray-300" />
                    </button>

                    {{-- Gallery --}}
                    <button
                        type="button"
                        x-on:click="pickGallery()"
                        class="group flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium outline-none transition duration-75 hover:bg-gray-50 focus-visible:bg-gray-50 active:bg-gray-100 dark:hover:bg-white/5 dark:active:bg-white/10"
                    >
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-50 text-primary-600 ring-1 ring-primary-600/10 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/20">
                            <x-filament::icon icon="heroicon-o-photo" class="h-5 w-5" />
                        </span>
                        <span class="flex-1 text-left text-gray-700 dark:text-gray-200">{{ __('Galeri / Album') }}</span>
                        <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4 shrink-0 text-gray-400 group-hover:text-gray-600 dark:text-gray-500 dark:group-hover:text-gray-300" />
                    </button>

                    {{-- Google Drive — tampil di Android & Windows/Linux (non Apple) --}}
                    <button
                        type="button"
                        x-show="!isIOS() && !isMac()"
                        x-on:click="pickDrive()"
                        class="group flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium outline-none transition duration-75 hover:bg-gray-50 focus-visible:bg-gray-50 active:bg-gray-100 dark:hover:bg-white/5 dark:active:bg-white/10"
                    >
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-50 ring-1 ring-primary-600/10 dark:bg-primary-400/10 dark:ring-primary-400/20">
                            <svg class="h-5 w-5" viewBox="0 0 87.3 78" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path d="m6.6 66.85 3.85 6.65c.8 1.4 1.95 2.5 3.3 3.3l13.75-23.8h-27.5c0 1.55.4 3.1 1.2 4.5z" fill="#0066da"/>
                                <path d="m43.65 25-13.75-23.8c-1.35.8-2.5 1.9-3.3 3.3l-25.4 44a9.06 9.06 0 0 0 -1.2 4.5h27.5z" fill="#00ac47"/>
                                <path d="m73.55 76.8c1.35-.8 2.5-1.9 3.3-3.3l1.6-2.75 7.65-13.25c.8-1.4 1.2-2.95 1.2-4.5h-27.502l5.852 11.5z" fill="#ea4335"/>
                                <path d="m43.65 25 13.75-23.8c-1.35-.8-2.9-1.2-4.5-1.2h-18.5c-1.6 0-3.15.45-4.5 1.2z" fill="#00832d"/>
                                <path d="m59.8 53h-32.3l-13.75 23.8c1.35.8 2.9 1.2 4.5 1.2h50.8c1.6 0 3.15-.45 4.5-1.2z" fill="#2684fc"/>
                                <path d="m73.4 26.5-12.7-22c-.8-1.4-1.95-2.5-3.3-3.3l-13.75 23.8 16.15 27.98h27.45c0-1.55-.4-3.1-1.2-4.5z" fill="#ffba00"/>
                            </svg>
                        </span>
                        <span class="flex-1 text-left text-gray-700 dark:text-gray-200">{{ __('Google Drive') }}</span>
                        <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4 shrink-0 text-gray-400 group-hover:text-gray-600 dark:text-gray-500 dark:group-hover:text-gray-300" />
                    </button>

                    {{-- iCloud Drive — tampil di iOS & macOS --}}
                    <button
                        type="button"
                        x-show="isIOS() || isMac()"
                        x-on:click="pickICloud()"
                        class="group flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium outline-none transition duration-75 hover:bg-gray-50 focus-visible:bg-gray-50 active:bg-gray-100 dark:hover:bg-white/5 dark:active:bg-white/10"
                        style="display:none;"
                    >
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-50 text-primary-600 ring-1 ring-primary-600/10 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/20">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path d="M17.5 10.5C17.5 10.5 17.5 10.5 17.5 10.5C17.5 7.46 15.04 5 12 5C9.52 5 7.44 6.67 6.74 8.94C4.64 9.13 3 10.9 3 13C3 15.21 4.79 17 7 17H17C18.93 17 20.5 15.43 20.5 13.5C20.5 11.68 19.13 10.18 17.36 10.02C17.41 10.18 17.5 10.34 17.5 10.5Z"/>
                            </svg>
                        </span>
                        <span class="flex-1 text-left text-gray-700 dark:text-gray-200">{{ __('iCloud Drive') }}</span>
                        <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4 shrink-0 text-gray-400 group-hover:text-gray-600 dark:text-gray-500 dark:group-hover:text-gray-300" />
                    </button>

                    <div class="my-1 border-t border-gray-100 dark:border-white/10"></div>

                    <button
                        type="button"
                        x-on:click="close()"
                        class="w-full rounded-lg px-3 py-2.5 text-center text-sm font-semibold text-danger-600 outline-none transition duration-75 hover:bg-danger-50 active:bg-danger-100 dark:text-danger-400 dark:hover:bg-danger-400/10"
                    >
                        {{ __('Batal') }}
                    </button>
                </div>

                {{-- ── WebRTC Camera view (desktop) ── --}}
                <div x-show="showCamera" style="display:none;">
                    {{-- Video / Canvas --}}
                    <div class="relative bg-black" style="aspect-ratio:4/3;">
                        <video
                            x-ref="camVideo"
                            x-show="!photoTaken"
                            autoplay
                            playsinline
                            muted
                            class="h-full w-full object-cover"
                            style="display:block;"
                        ></video>
                        <canvas
                            x-ref="camCanvas"
                            x-show="photoTaken"
                            class="h-full w-full object-cover"
                            style="display:none;"
                        ></canvas>
                        {{-- Loading indicator while camera starts --}}
                        <div
                            x-show="!photoTaken && stream === null"
                            class="absolute inset-0 flex items-center justify-center bg-gray-900/60"
                            style="display:none;"
                        >
                            <svg class="h-8 w-8 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        </div>
                    </div>

                    {{-- Controls --}}
                    <div class="flex items-center justify-center gap-4 px-4 py-4">
                        {{-- Capture button (live view) --}}
                        <button
                            type="button"
                            x-show="!photoTaken"
                            x-on:click="capturePhoto()"
                            class="flex h-14 w-14 items-center justify-center rounded-full bg-primary-600 text-white shadow-lg transition hover:bg-primary-500 active:scale-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                            aria-label="{{ __('Ambil Foto') }}"
                        >
                            <x-filament::icon icon="heroicon-m-camera" class="h-7 w-7" />
                        </button>

                        {{-- Retake + Use (after capture) --}}
                        <template x-if="photoTaken">
                            <div class="flex w-full items-center justify-between gap-3">
                                <button
                                    type="button"
                                    x-on:click="retakePhoto()"
                                    class="flex-1 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 active:bg-gray-100 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10"
                                >
                                    {{ __('Ulangi') }}
                                </button>
                                <button
                                    type="button"
                                    x-on:click="usePhoto()"
                                    class="flex-1 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 active:bg-primary-700"
                                >
                                    {{ __('Gunakan Foto') }}
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

            </div>
        </div>
    </template>
</div>
