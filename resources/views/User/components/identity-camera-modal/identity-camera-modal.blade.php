@php
    $modalId = 'identity-camera-modal';
@endphp

{{--
    Identity Camera Modal — selfie + KTP photo.
    Opens front camera for selfie, rear camera for KTP document.
    On mobile: native camera via <input capture>.
    On desktop: WebRTC getUserMedia.

    Usage: dispatch Livewire event 'open-identity-camera' with { target: 'selfie_photo' | 'ktp_photo' }
--}}
<div
    x-data="{
        isOpen: false,
        showCamera: false,
        targetField: '',

        stream: null,
        facingMode: 'user',
        photoTaken: false,
        cameraFacing: '',
        cameraLabel: '',
        mirrorSaved: true,   /* file hasil jepret ikut dicermin seperti preview */

        open(target) {
            this.targetField = target;
            // Set SEKALI saat dibuka. Dulu startCamera() menimpa facingMode tiap kali,
            // jadi tombol balik kamera tidak pernah berefek.
            this.facingMode = this.facingModeForTarget;
            this.activeCameraId = '';
            this.cameraFacing = '';
            this.isOpen = true;
            this.showCamera = false;
            this.photoTaken = false;
            document.body.classList.add('overflow-hidden');
        },

        close() {
            this.stopCamera();
            this.isOpen = false;
            this.showCamera = false;
            this.photoTaken = false;
            document.body.classList.remove('overflow-hidden');
        },

        get facingModeForTarget() {
            return this.targetField === 'selfie_photo' ? 'user' : 'environment';
        },

        /* Cermin (CSS saja) hanya untuk selfie dengan kamera depan. Foto KTP tidak
         * pernah dicermin karena teksnya jadi tidak terbaca. */
        get mirroredView() {
            return !(this.cameraFacing === 'environment' || (!this.cameraFacing && /back|rear|environment|belakang/i.test(this.cameraLabel || '')));
        },

        /* Frame digambar TIDAK dibalik (hanya dipotong sesuai panduan). Pencerminan hanya
         * efek tampilan lewat CSS. Kalau file ikut dibalik, teks KTP di foto
         * selfie menjadi terbalik dan tidak terbaca OCR / AI / petugas. */
        drawToCanvas(video, canvas) {
            const vw = video.videoWidth || 1920;
            const vh = video.videoHeight || 1080;

            /* Kotak preview selalu 4:3 dan video memakai object-cover, jadi
             * yang terlihat hanya bagian tengah 4:3 dari frame. Ambil bagian
             * yang sama supaya hasil foto = yang terlihat di layar. */
            let sw = vw, sh = vh;
            if (vw / vh > 4 / 3) { sw = vh * 4 / 3; } else { sh = vw * 3 / 4; }
            let sx = (vw - sw) / 2, sy = (vh - sh) / 2;

            /* KTP: potong persis di dalam garis kartu (lebar 86% kotak,
             * rasio 85.6 : 54). Angka ini sama dengan <rect> di SVG panduan. */
            if (this.targetField !== 'selfie_photo') {
                const cw = sw * 0.86;
                const ch = cw / 1.585;
                sx += (sw - cw) / 2;
                sy += (sh - ch) / 2;
                sw = cw;
                sh = ch;
            }

            canvas.width = Math.round(sw);
            canvas.height = Math.round(sh);
            const ctx = canvas.getContext('2d');
            if (this.mirrorSaved && this.mirroredView) {
                ctx.save();
                ctx.translate(canvas.width, 0);
                ctx.scale(-1, 1);
                ctx.drawImage(video, sx, sy, sw, sh, 0, 0, canvas.width, canvas.height);
                ctx.restore();
            } else {
                ctx.drawImage(video, sx, sy, sw, sh, 0, 0, canvas.width, canvas.height);
            }
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

        /* Daftar kamera fisik di perangkat. Laptop biasanya cuma satu webcam;
         * HP bisa punya beberapa (depan, belakang, ultra-wide, tele). */
        cameras: [],
        activeCameraId: '',

        async loadCameras() {
            try {
                const devices = await navigator.mediaDevices.enumerateDevices();

                this.cameras = devices.filter((d) => d.kind === 'videoinput');
            } catch (e) {
                this.cameras = [];
            }
        },

        cameraConstraints() {
            const base = { width: { ideal: 1920 }, height: { ideal: 1080 } };

            if (this.activeCameraId) {
                return { ...base, deviceId: { exact: this.activeCameraId } };
            }

            return { ...base, facingMode: this.facingMode };
        },

        /* Dipakai tombol 'Kiri' dan 'Kanan'. */
        async selectCamera(deviceId) {
            this.activeCameraId = deviceId;
            await this.startCamera();
        },

        async startCamera() {
            this.showCamera = true;
            this.photoTaken = false;
            await this.$nextTick();
            const video = this.$refs.camVideo;
            if (!video) return;
            try {
                if (this.stream) { this.stream.getTracks().forEach(t => t.stop()); }
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: this.cameraConstraints(),
                    audio: false,
                });
                video.srcObject = this.stream;

                /* Panduan AI mulai begitu ada stream frame yang bisa dianalisis. */
                this.startCoach();
                await video.play();

                // Sinkronkan state dengan kamera yang BENAR-BENAR dipakai browser.
                // Dulu activeCameraId diisi cameras[0] seenaknya, jadi tombol
                // Kiri/Kanan menandai kamera yang salah dan retake memakai deviceId
                // yang salah (flip kamera 'tidak berfungsi' setelah ulangi foto).
                const settings = this.stream.getVideoTracks()[0]?.getSettings?.() || {};
                this.activeCameraId = settings.deviceId || this.activeCameraId;
                this.cameraFacing = settings.facingMode || '';
                this.cameraLabel = this.stream.getVideoTracks()[0]?.label || '';

                await this.loadCameras();
            } catch (e) {
                alert({{ json_encode(__('Tidak dapat mengakses kamera. Pastikan izin kamera diberikan.')) }});
                this.showCamera = false;
            }
        },

        stopCamera() {
            if (this.stream) {
                this.stream.getTracks().forEach(t => t.stop());
                this.stream = null;
            }
            /* Tanpa stream tidak ada frame untuk dianalisis, dan suara
             * AI harus berhenti sekarang juga. */
            this.stopCoach();
        },

        flipCamera() {
            this.facingMode = this.facingMode === 'user' ? 'environment' : 'user';
            this.activeCameraId = ''; // kalau tidak, deviceId lama mengalahkan facingMode
            this.startCamera();
        },

        capturePhoto() {
            const video = this.$refs.camVideo;
            const canvas = this.$refs.camCanvas;
            if (!video || !canvas) return;
            this.drawToCanvas(video, canvas);
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
                const ext = this.targetField === 'selfie_photo' ? 'selfie' : 'ktp';
                const file = new File([blob], ext + '-' + Date.now() + '.jpg', { type: 'image/jpeg' });
                this.injectFile(file);
                this.close();
            }, 'image/jpeg', 0.92);
        },

        /* Klik input sinkron (tanpa setTimeout) supaya tidak diblokir Safari/iOS. */
        pickNativeCamera() { document.getElementById('identity-input-native-camera')?.click(); },
        pickGallery() { document.getElementById('identity-input-gallery')?.click(); },
        pickDrive() { document.getElementById('identity-input-drive')?.click(); },
        pickICloud() { document.getElementById('identity-input-icloud')?.click(); },

        onPicked(event) {
            const file = event.target.files?.[0];
            if (file) {
                this.injectFile(file);
                this.close(); // dulu modal tetap terbuka setelah pilih file
            }
            event.target.value = '';
        },

        injectFile(file) {
            if (!file || !this.targetField) return;

            let target = null;
            document.querySelectorAll('[wire\\:key]').forEach(el => {
                const key = el.getAttribute('wire:key') || '';
                if (key.includes(this.targetField)) {
                    target = el;
                }
            });

            if (target && window.FilePond) {
                const pondElement = target.querySelector('.filepond--root');
                if (pondElement) {
                    const inst = window.FilePond.find(pondElement);
                    if (inst) {
                        inst.removeFile();
                        inst.addFile(file);
                        return;
                    }
                }
            }

            if (target) {
                const fp = target.querySelector('input[type=file]');
                if (fp) {
                    const dt = new DataTransfer();
                    dt.items.add(file);
                    fp.files = dt.files;
                    fp.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
        },

        /* ── Real-time AI scan coach ──────────────────────────────────────
         * Mengukur fokus, pencahayaan, dan posisi subjek dari frame video
         * langsung, lalu memberi arahan secara lisan dan visual (lingkaran
         * hijau saat kualitas siap ditekan).
         *
         * Seluruh algoritmanya ada di resources/js/ai-scan-coach supaya
         * objek x-data ini tetap tipis. Analisis real-time tidak mungkin
         * lewat server: satu round-trip per frame jauh terlalu lambat.
         *
         * PENTING: hanya kutip tunggal di dalam x-data. Atributnya dibungkus
         * kutip ganda, satu saja akan menutupnya lebih awal. */
        coach: null,

        startCoach() {
            this.stopCoach();

            const factory = window.AIScanCoach && window.AIScanCoach.create;
            if (!factory) return;

            const mount = this.$refs.camWrap;
            if (!mount) return;

            this.coach = factory({
                mount: mount,
                mode: this.targetField === 'ktp_photo' ? 'document' : 'face',
                lang: document.documentElement.lang || '{{ app()->getLocale() }}',
                video: () => this.$refs.camVideo,
            });

            this.coach.start();
        },

        stopCoach() {
            if (!this.coach) return;
            this.coach.stop();
            this.coach = null;
        },
    }"
    x-on:open-identity-camera.window="open($event.detail.target)"
    x-on:keydown.escape.window="if (isOpen) { if (showCamera) { stopCamera(); showCamera = false; } else { close(); } }"
    x-on:livewire:navigating.window="close()"
    class="contents"
>
    {{-- Hidden file inputs --}}
    <input type="file" accept="image/*" :capture="targetField === 'selfie_photo' ? 'user' : 'environment'" class="sr-only" id="identity-input-native-camera" x-on:change="onPicked($event)">
    <input type="file" accept="image/*" class="sr-only" id="identity-input-gallery" x-on:change="onPicked($event)">
    <input type="file" accept="image/*" class="sr-only" id="identity-input-drive" x-on:change="onPicked($event)">
    <input type="file" accept="image/*" class="sr-only" id="identity-input-icloud" x-on:change="onPicked($event)">

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
            aria-labelledby="identity-camera-title"
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
                class="fi-glass-modal w-full max-w-sm overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
            >
                {{-- Header --}}
                <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-white/10">
                    <h3 id="identity-camera-title" class="text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        <span x-show="!showCamera">{{ __('Pilih Sumber Foto') }}</span>
                        <span x-show="showCamera" style="display:none;">
                            <span x-text="targetField === 'selfie_photo' ? {{ json_encode(__('Ambil Selfie')) }} : {{ json_encode(__('Foto Dokumen')) }}"></span>
                        </span>
                    </h3>
                    <div class="flex items-center gap-1">
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

                {{-- Source picker --}}
                <div x-show="!showCamera" class="space-y-1 px-4 py-3">
                    {{-- Camera --}}
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

                    {{-- Google Drive --}}
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

                    {{-- iCloud Drive --}}
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

                {{-- WebRTC Camera view --}}
                <div x-show="showCamera" style="display:none;">
                    <div x-ref="camWrap" class="relative bg-black" style="aspect-ratio:4/3;">
                        <video
                            x-ref="camVideo"
                            x-show="!photoTaken"
                            autoplay
                            playsinline
                            muted
                            class="h-full w-full object-cover"
                            :style="mirroredView ? 'display:block; transform: scaleX(-1);' : 'display:block;'"
                        ></video>
                        <canvas
                            x-ref="camCanvas"
                            x-show="photoTaken"
                            class="h-full w-full"
                            :class="targetField === 'selfie_photo' ? 'object-cover' : 'object-contain'"
                            :style="{ transform: 'none' }"
                            style="display:none;"
                        ></canvas>
                        {{-- Garis panduan. viewBox 400x300 = rasio kotak 4:3 (tanpa distorsi).
                             Selfie: oval wajah. KTP: kotak kartu 85.6:54, lebar 86%.
                             Ukuran harus sama dengan perhitungan di drawToCanvas(). --}}
                        <svg
                            x-show="!photoTaken && targetField === 'selfie_photo'"
                            class="pointer-events-none absolute inset-0 h-full w-full"
                            viewBox="0 0 400 300"
                            preserveAspectRatio="none"
                            aria-hidden="true"
                            style="display:none;"
                        >
                            <defs>
                                <mask id="identity-guide-mask-face">
                                    <rect width="400" height="300" fill="white" />
                                    <ellipse cx="200" cy="150" rx="84" ry="112" fill="black" />
                                </mask>
                            </defs>
                            <rect width="400" height="300" fill="rgba(0,0,0,0.45)" mask="url(#identity-guide-mask-face)" />
                            <ellipse cx="200" cy="150" rx="84" ry="112" fill="none" stroke="rgba(255,255,255,0.95)" stroke-width="2.5" vector-effect="non-scaling-stroke" />
                        </svg>
                        <svg
                            x-show="!photoTaken && targetField !== 'selfie_photo'"
                            class="pointer-events-none absolute inset-0 h-full w-full"
                            viewBox="0 0 400 300"
                            preserveAspectRatio="none"
                            aria-hidden="true"
                            style="display:none;"
                        >
                            <defs>
                                <mask id="identity-guide-mask-card">
                                    <rect width="400" height="300" fill="white" />
                                    <rect x="28" y="41.5" width="344" height="217" fill="black" />
                                </mask>
                            </defs>
                            <rect width="400" height="300" fill="rgba(0,0,0,0.45)" mask="url(#identity-guide-mask-card)" />
                            <rect x="28" y="41.5" width="344" height="217" fill="none" stroke="rgba(255,255,255,0.95)" stroke-width="2.5" vector-effect="non-scaling-stroke" />
                        </svg>
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

                    {{-- Satu tombol jepret di tengah, dikelilingi tombol pemilih kamera
                         KIRI dan KANAN. Hanya tampil kalau perangkat punya
                         lebih dari satu kamera. --}}
                    <div class="flex items-center justify-center gap-5 px-4 py-4">
                        <button
                            type="button"
                            x-show="!photoTaken && cameras.length > 1"
                            x-on:click="selectCamera(cameras[0].deviceId)"
                            x-bind:class="activeCameraId === cameras[0]?.deviceId
                                ? 'bg-primary-600 text-white'
                                : 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200'"
                            class="flex h-11 shrink-0 items-center gap-1.5 rounded-full px-4 text-xs font-semibold transition hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                            :aria-label="cameras[0]?.label || 'Kamera Kiri'"
                        >
                            <x-filament::icon icon="heroicon-m-arrow-left" class="h-4 w-4" />
                            <span>{{ __('Kiri') }}</span>
                        </button>

                        <button
                            type="button"
                            x-show="!photoTaken"
                            x-on:click="capturePhoto()"
                            class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-primary-600 text-white shadow-lg transition hover:bg-primary-500 active:scale-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                            aria-label="{{ __('Ambil Foto') }}"
                        >
                            <x-filament::icon icon="heroicon-m-camera" class="h-8 w-8" />
                        </button>

                        <button
                            type="button"
                            x-show="!photoTaken && cameras.length > 1"
                            x-on:click="selectCamera(cameras[cameras.length - 1].deviceId)"
                            x-bind:class="activeCameraId === cameras[cameras.length - 1]?.deviceId
                                ? 'bg-primary-600 text-white'
                                : 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200'"
                            class="flex h-11 shrink-0 items-center gap-1.5 rounded-full px-4 text-xs font-semibold transition hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                            :aria-label="cameras[cameras.length - 1]?.label || 'Kamera Kanan'"
                        >
                            <span>{{ __('Kanan') }}</span>
                            <x-filament::icon icon="heroicon-m-arrow-right" class="h-4 w-4" />
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
            </div>
        </div>
    </template>
</div>
