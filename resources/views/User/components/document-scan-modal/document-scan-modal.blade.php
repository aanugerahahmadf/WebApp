@php
    // CSS selector of the FilePond field wrapper for the triggering button —
    // passed via the page that includes this modal. Defaults are the register /
    // complete-profile field wrapper classes.
    $documentWrapper = $documentWrapper ?? '.document-photo-wrapper';
    $selfieWrapper = $selfieWrapper ?? '.selfie-photo-wrapper';
@endphp

{{--
    Document / Selfie Scan Modal — re-usable for both identity-document photos
    (ktp_photo, npwp, passport, sim) and selfie-with-document photos.

    Behaviour:
      • Opens from a wrapper click (dispatched as window event open-document-scan
        with detail: { field, mode, wrapper }).
      • Source picker: Buka Kamera · File · Galeri.
      • Camera (desktop) = WebRTC getUserMedia with a scanner box overlay.
        Camera (mobile) = <input capture="environment"> → native camera app.
      • File & Galeri results are routed into the scanner preview (with the box
        overlay) — then "Scan" runs client-side OCR (Tesseract.js) and the
        parsed document fields are written into the Filament form automatically.
      • No PDF / document text emphasis: all label copy is plain text.

    Window helpers used (exposed by resources/js/app-web/app-web.js):
      window.ScannerUI  → injectFile(), setFormStateMany(), getFormState()
      window.OCR        → recognizeText(), parseDocument(), mapOcrToFormState()
--}}
<div
    x-data="{
        isOpen: false,
        field: 'ktp_photo',
        mode: 'document',          /* 'document' | 'selfie' */
        wrapper: '{{ $documentWrapper }}',
        view: 'source',            /* 'source' | 'camera' | 'preview' | 'result' */

        /* camera state */
        stream: null,
        facingMode: 'environment',
        photoTaken: false,

        /* preview / ocr state */
        imageUrl: null,
        file: null,
        scanning: false,
        ocrStatus: '',
        ocrProgress: 0,
        ocrError: '',
        filledCount: 0,
        parsedSummary: [],

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

        open(detail = {}) {
            this.field = detail.field || (detail.mode === 'selfie' ? 'selfie_photo' : 'ktp_photo');
            this.mode = detail.mode || 'document';
            this.wrapper = detail.wrapper || (this.mode === 'selfie' ? '{{ $selfieWrapper }}' : '{{ $documentWrapper }}');

            this.view = 'source';
            this.imageUrl = null;
            this.file = null;
            this.photoTaken = false;
            this.scanning = false;
            this.ocrStatus = '';
            this.ocrProgress = 0;
            this.ocrError = '';
            this.filledCount = 0;
            this.parsedSummary = [];
            this.isOpen = true;
            document.body.classList.add('overflow-hidden');
        },

        close() {
            this.stopCamera();
            if (this.imageUrl) {
                URL.revokeObjectURL(this.imageUrl);
                this.imageUrl = null;
            }
            this.isOpen = false;
            document.body.classList.remove('overflow-hidden');
        },

        resetToSource() {
            this.stopCamera();
            this.view = 'source';
            this.imageUrl = null;
            this.file = null;
            this.photoTaken = false;
        },

        /* ── camera (WebRTC, semua perangkat) ── */
        async startCamera() {
            this.view = 'camera';
            this.photoTaken = false;
            await this.$nextTick();
            const video = this.$refs.camVideo;
            if (!video) return;
            try {
                if (this.stream) this.stream.getTracks().forEach((t) => t.stop());
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: this.facingMode, width: { ideal: 1280 }, height: { ideal: 720 } },
                    audio: false,
                });
                video.srcObject = this.stream;
                await video.play();
            } catch (e) {
                // Fallback untuk browser yang tidak mendukung WebRTC in-page:
                // buka kamera native lewat input capture.
                this.view = 'source';
                const el = document.getElementById(this.field + '-input-native-camera');
                if (el) {
                    this.close();
                    setTimeout(() => el.click(), 100);
                }
            }
        },

        stopCamera() {
            if (this.stream) {
                this.stream.getTracks().forEach((t) => t.stop());
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
            canvas.width = video.videoWidth || 640;
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
            canvas.toBlob((blob) => {
                if (!blob) return;
                const ext = this.field === 'selfie_photo' ? 'jpg' : 'jpg';
                const file = new File([blob], this.field + '-' + Date.now() + '.' + ext, { type: 'image/jpeg' });
                this.openPreview(file);
            }, 'image/jpeg', 0.92);
        },

        /* ── file pickers (native camera, file, galeri) ── */
        pickNativeCamera() {
            this.close();
            setTimeout(() => {
                const el = document.getElementById(this.field + '-input-native-camera');
                if (el) el.click();
            }, 100);
        },

        pickFile() {
            this.close();
            setTimeout(() => {
                const el = document.getElementById(this.field + '-input-file');
                if (el) el.click();
            }, 100);
        },

        pickGallery() {
            this.close();
            setTimeout(() => {
                const el = document.getElementById(this.field + '-input-gallery');
                if (el) el.click();
            }, 100);
        },

        onPicked(event) {
            const file = event.target.files?.[0];
            if (file) {
                this.isOpen = true;
                document.body.classList.add('overflow-hidden');
                this.openPreview(file);
            }
            event.target.value = '';
        },

        /* ── preview → scanner → OCR ── */
        openPreview(file) {
            this.file = file;
            this.view = 'preview';
            this.photoTaken = false;
            this.stopCamera();
            this.imageUrl = URL.createObjectURL(file);
        },

        retakePreview() {
            if (this.imageUrl) URL.revokeObjectURL(this.imageUrl);
            this.imageUrl = null;
            this.file = null;
            this.stopCamera();
            this.view = 'source';
        },

        translateOcrStatus(status) {
            const map = {
                'loading tesseract core': {{ json_encode(__('Memuat mesin OCR...')) }},
                'loading language traineddata': {{ json_encode(__('Memuat kamus bahasa...')) }},
                'initializing api': {{ json_encode(__('Menyiapkan mesin OCR...')) }},
                'recognizing text': {{ json_encode(__('Menganalisis dokumen...')) }},
            };
            return map[status] || {{ json_encode(__('Menganalisis dokumen...')) }};
        },

        identityType() {
            return window.ScannerUI.getFormState('data.identity_type', document.querySelector(this.wrapper)) || 'ktp';
        },

        fieldLabel() {
            return this.field === 'selfie_photo'
                ? {{ json_encode(__('Foto Selfie + Dokumen')) }}
                : {{ json_encode(__('Foto Dokumen Identitas')) }};
        },

        async scan() {
            if (!this.file || !window.OCR) { this.close(); return; }

            this.view = 'preview';
            this.scanning = true;
            this.ocrError = '';
            this.ocrStatus = {{ json_encode(__('Menganalisis dokumen...')) }};
            this.ocrProgress = 0;

            try {
                // 1) Lampirkan file asli ke FilePond field terpilih
                const anchor = document.querySelector(this.wrapper);
                const injected = window.ScannerUI.injectFile(this.wrapper, this.file, this.file.name);
                if (!injected) this.ocrError = {{ json_encode(__('Tidak dapat melampirkan foto. Silakan unggah manual.')) }};

                // 2) OCR client-side
                const identity = this.identityType();
                const text = await window.OCR.recognizeText(this.file, {
                    onProgress: (status, progress) => {
                        this.ocrStatus = this.translateOcrStatus(status);
                        this.ocrProgress = Math.round((progress || 0) * 100);
                    },
                });

                // 3) Parse sesuai jenis dokumen
                const parsed = window.OCR.parseDocument(text, identity);

                // 4) Tulis ke form (auto-fill)
                const values = window.OCR.mapOcrToFormState(parsed);
                const written = window.ScannerUI.setFormStateMany(values, anchor);
                this.filledCount = written;

                this.parsedSummary = Object.entries(values).map(([key, value]) => ({
                    key,
                    value,
                }));

                this.scanning = false;
                this.view = 'result';
            } catch (error) {
                console.error('[Document Scan] OCR error:', error);
                this.scanning = false;
                this.ocrError = {{ json_encode(__('Gagal membaca dokumen. Silakan coba lagi dengan pencahayaan yang lebih baik.')) }};
                this.view = 'result';
            }
        },

        finish() {
            this.close();
        },
    }"
    x-on:open-document-scan.window="open($event.detail)"
    x-on:keydown.escape.window="if (isOpen) { if (view !== 'source') { resetToSource(); } else { close(); } }"
    x-on:livewire:navigating.window="close()"
    class="contents"
>
    {{-- Hidden file inputs — out of the teleport so they always exist in the DOM --}}
    <input type="file" accept="image/*" capture="environment" class="sr-only" :id="field + '-input-native-camera'" x-on:change="onPicked($event)">
    <input type="file" accept="image/*" class="sr-only" :id="field + '-input-file'" x-on:change="onPicked($event)">
    <input type="file" accept="image/*" class="sr-only" :id="field + '-input-gallery'" x-on:change="onPicked($event)">

    <template x-teleport="body">
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
            class="fixed inset-0 z-[9998] flex items-end justify-center bg-gray-950/50 p-3 pb-[calc(0.75rem+env(safe-area-inset-bottom,0px))] dark:bg-gray-950/75 sm:items-center sm:p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="document-scan-title"
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
                class="w-full max-w-md overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
            >
                {{-- ── Header ── --}}
                <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-white/10">
                    <h3 id="document-scan-title" class="text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        <span x-show="view === 'source'">{{ __('Pilih Sumber Foto') }}</span>
                        <span x-show="view === 'camera'" style="display:none;">{{ __('Ambil Foto') }}</span>
                        <span x-show="view === 'preview'" style="display:none;">{{ __('Scan') }} <span x-text="fieldLabel()"></span></span>
                        <span x-show="view === 'result'" style="display:none;">{{ __('Hasil Scan') }}</span>
                    </h3>
                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            x-show="view === 'camera'"
                            x-on:click="flipCamera()"
                            class="relative flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 outline-none transition duration-75 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/5 dark:hover:text-gray-300"
                            :aria-label="{{ json_encode(__('Balik Kamera')) }}"
                            style="display:none;"
                        >
                            <x-filament::icon icon="heroicon-m-arrow-path" class="h-5 w-5" />
                        </button>
                        <button
                            type="button"
                            x-show="view !== 'source'"
                            x-on:click="resetToSource()"
                            class="relative flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 outline-none transition duration-75 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/5 dark:hover:text-gray-300"
                            :aria-label="{{ json_encode(__('Kembali')) }}"
                            style="display:none;"
                        >
                            <x-filament::icon icon="heroicon-m-arrow-left" class="h-5 w-5" />
                        </button>
                        <button
                            type="button"
                            x-on:click="close()"
                            class="relative -m-1.5 flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 outline-none transition duration-75 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/5 dark:hover:text-gray-300"
                            :aria-label="{{ json_encode(__('Tutup')) }}"
                        >
                            <x-filament::icon icon="heroicon-m-x-mark" class="h-5 w-5" />
                        </button>
                    </div>
                </div>

                {{-- ── Source picker —─ --}}
                <div x-show="view === 'source'" class="space-y-1 px-4 py-3">
                    <button
                        type="button"
                        x-on:click="startCamera()"
                        class="group flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium outline-none transition duration-75 hover:bg-gray-50 focus-visible:bg-gray-50 active:bg-gray-100 dark:hover:bg-white/5 dark:active:bg-white/10"
                    >
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-50 text-primary-600 ring-1 ring-primary-600/10 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/20">
                            <x-filament::icon icon="heroicon-o-camera" class="h-5 w-5" />
                        </span>
                        <span class="flex-1 text-left text-gray-700 dark:text-gray-200">{{ __('Buka Kamera') }}</span>
                        <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4 shrink-0 text-gray-400 group-hover:text-gray-600 dark:text-gray-500 dark:group-hover:text-gray-300" />
                    </button>

                    <button
                        type="button"
                        x-on:click="pickFile()"
                        class="group flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium outline-none transition duration-75 hover:bg-gray-50 focus-visible:bg-gray-50 active:bg-gray-100 dark:hover:bg-white/5 dark:active:bg-white/10"
                    >
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-50 text-primary-600 ring-1 ring-primary-600/10 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/20">
                            <x-filament::icon icon="heroicon-o-arrow-up-tray" class="h-5 w-5" />
                        </span>
                        <span class="flex-1 text-left text-gray-700 dark:text-gray-200">{{ __('File') }}</span>
                        <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4 shrink-0 text-gray-400 group-hover:text-gray-600 dark:text-gray-500 dark:group-hover:text-gray-300" />
                    </button>

                    <button
                        type="button"
                        x-on:click="pickGallery()"
                        class="group flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium outline-none transition duration-75 hover:bg-gray-50 focus-visible:bg-gray-50 active:bg-gray-100 dark:hover:bg-white/5 dark:active:bg-white/10"
                    >
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-50 text-primary-600 ring-1 ring-primary-600/10 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/20">
                            <x-filament::icon icon="heroicon-o-photo" class="h-5 w-5" />
                        </span>
                        <span class="flex-1 text-left text-gray-700 dark:text-gray-200">{{ __('Galeri') }}</span>
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
                <div x-show="view === 'camera'" style="display:none;">
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

                        {{-- Scanner box / guide overlay --}}
                        <div
                            x-show="!photoTaken"
                            x-transition.opacity.duration.300ms
                            class="pointer-events-none absolute inset-0 flex items-center justify-center"
                            :class="mode === 'selfie' ? '' : ''"
                        >
                            {{-- Selfie: face oval (top) + document box (bottom) --}}
                            <template x-if="mode === 'selfie'">
                                <div class="relative h-full w-full">
                                    {{-- Wajah (oval) --}}
                                    <div
                                        class="absolute left-1/2 top-[12%] h-[34%] w-[42%] -translate-x-1/2 rounded-[45%] border-2 border-white/90"
                                        style="box-shadow: 0 0 0 9999px rgba(0,0,0,0.40);"
                                    ></div>
                                    {{-- Dokumen (persegi) --}}
                                    <div
                                        class="absolute bottom-[8%] left-1/2 aspect-[1.586/1] w-[72%] -translate-x-1/2 rounded-lg border-2 border-white/90"
                                    ></div>
                                </div>
                            </template>
                            {{-- Document only: rectangle guide --}}
                            <template x-if="mode !== 'selfie'">
                                <div
                                    class="absolute left-1/2 top-1/2 aspect-[1.586/1] w-[80%] -translate-x-1/2 -translate-y-1/2 rounded-lg border-2 border-white/90"
                                    style="box-shadow: 0 0 0 9999px rgba(0,0,0,0.40);"
                                ></div>
                            </template>
                        </div>

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

                    {{-- hint --}}
                    <p class="px-4 py-2 text-center text-xs text-gray-500 dark:text-gray-400">
                        <template x-if="mode === 'selfie'">{{ __('Arahkan wajah ke oval dan dokumen ke kotak bawah.') }}</template>
                        <template x-if="mode !== 'selfie'">{{ __('Letakkan dokumen di dalam kotak scanner.') }}</template>
                    </p>

                    {{-- Controls --}}
                    <div class="flex items-center justify-center gap-4 px-4 py-4">
                        <button
                            type="button"
                            x-show="!photoTaken"
                            x-on:click="capturePhoto()"
                            class="flex h-14 w-14 items-center justify-center rounded-full bg-primary-600 text-white shadow-lg transition hover:bg-primary-500 active:scale-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                            :aria-label="{{ json_encode(__('Ambil Foto')) }}"
                        >
                            <x-filament::icon icon="heroicon-m-camera" class="h-7 w-7" />
                        </button>

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

                {{-- ── Preview / scanner view ── --}}
                <div x-show="view === 'preview'" style="display:none;">
                    <div class="relative bg-black" style="aspect-ratio:4/3;">
                        <img
                            :src="imageUrl"
                            alt="Preview"
                            class="h-full w-full object-contain"
                        />
                        {{-- Scanner box overlay on preview --}}
                        <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                            <template x-if="mode === 'selfie'">
                                <div class="relative h-full w-full">
                                    <div
                                        class="absolute left-1/2 top-[12%] h-[34%] w-[42%] -translate-x-1/2 rounded-[45%] border-2 border-white/80"
                                        style="box-shadow: 0 0 0 9999px rgba(0,0,0,0.30);"
                                    ></div>
                                    <div
                                        class="absolute bottom-[8%] left-1/2 aspect-[1.586/1] w-[72%] -translate-x-1/2 rounded-lg border-2 border-white/80"
                                    ></div>
                                </div>
                            </template>
                            <template x-if="mode !== 'selfie'">
                                <div
                                    class="absolute left-1/2 top-1/2 aspect-[1.586/1] w-[80%] -translate-x-1/2 -translate-y-1/2 rounded-lg border-2 border-white/80"
                                    style="box-shadow: 0 0 0 9999px rgba(0,0,0,0.30);"
                                ></div>
                            </template>
                        </div>

                        {{-- Scanning overlay --}}
                        <div
                            x-show="scanning"
                            class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 bg-gray-950/70"
                            style="display:none;"
                        >
                            <svg class="h-8 w-8 animate-spin text-primary-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <p class="px-6 text-center text-sm font-medium text-white" x-text="ocrStatus"></p>
                            <p class="px-6 text-center text-xs text-gray-300" x-text="ocrProgress + '%'"></p>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-3 px-4 py-4">
                        <button
                            type="button"
                            x-on:click="retakePreview()"
                            :disabled="scanning"
                            class="flex-1 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 active:bg-gray-100 disabled:opacity-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10"
                        >
                            {{ __('Ubah Foto') }}
                        </button>
                        <button
                            type="button"
                            x-on:click="scan()"
                            :disabled="scanning"
                            class="flex-1 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 active:bg-primary-700 disabled:opacity-50"
                        >
                            <span x-show="!scanning">{{ __('Scan & Isi Form') }}</span>
                            <span x-show="scanning" style="display:none;">{{ __('Memproses...') }}</span>
                        </button>
                    </div>
                </div>

                {{-- ── Result view ── --}}
                <div x-show="view === 'result'" style="display:none;">
                    <div class="px-4 py-5">
                        <template x-if="ocrError">
                            <div class="flex items-start gap-3 rounded-lg bg-danger-50 p-3 text-sm text-danger-700 dark:bg-danger-400/10 dark:text-danger-400">
                                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="mt-0.5 h-5 w-5 shrink-0" />
                                <div>
                                    <p class="font-semibold">{{ __('Scan gagal') }}</p>
                                    <p x-text="ocrError"></p>
                                </div>
                            </div>
                        </template>

                        <template x-if="!ocrError">
                            <div class="flex flex-col items-center gap-2 text-center">
                                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-success-50 text-success-600 dark:bg-success-400/10 dark:text-success-400">
                                    <x-filament::icon icon="heroicon-m-check" class="h-6 w-6" />
                                </span>
                                <p class="text-base font-semibold text-gray-950 dark:text-white">{{ __('Scan berhasil!') }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400" x-text="filledCount > 0 ? {{ json_encode(__('Form otomatis terisi :count kolom. Silakan periksa kembali.')) }}.replace(':count', filledCount) : {{ json_encode(__('Tidak ada kolom yang cocok. Silakan isi manual.')) }}"></p>
                            </div>
                        </template>

                        {{-- Detected field summary --}}
                        <template x-if="parsedSummary.length > 0">
                            <ul class="mt-4 max-h-56 space-y-1 overflow-y-auto rounded-lg border border-gray-200 p-2 text-xs dark:border-white/10">
                                <template x-for="item in parsedSummary" :key="item.key">
                                    <li class="flex items-start gap-2 rounded px-2 py-1 hover:bg-gray-50 dark:hover:bg-white/5">
                                        <span class="mt-0.5 h-1.5 w-1.5 shrink-0 rounded-full bg-success-400"></span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block font-medium text-gray-700 dark:text-gray-200" x-text="item.key.replace('data.', '') + ':'"></span>
                                            <span class="block truncate text-gray-500 dark:text-gray-400" x-text="item.value"></span>
                                        </span>
                                    </li>
                                </template>
                            </ul>
                        </template>

                        <button
                            type="button"
                            x-on:click="finish()"
                            class="mt-4 w-full rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 active:bg-primary-700"
                        >
                            {{ __('Selesai') }}
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </template>
</div>