@php
    // CSS selector of the FilePond field wrapper for the triggering button —
    // passed via the page that includes this modal. Defaults are the sign-up /
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
{{-- Cadangan: kalau bundle JS belum memasang window.ScannerUI, modal tetap bisa
     melampirkan foto ke FilePond / form Livewire. Aman dipasang di beberapa
     modal sekaligus (dicek dulu sebelum dibuat). --}}
<script>
    (function () {
        if (window.ScannerUI && window.ScannerUI.injectFile && window.ScannerUI.waitForUploads) return;

        function lw(el) {
            var base = el && el.closest ? el.closest('[wire\\:id]') : null;
            var node = base || document.querySelector('[wire\\:id]');
            if (!node || !window.Livewire) return null;
            return window.Livewire.find(node.getAttribute('wire:id')) || null;
        }

        window.ScannerUI = Object.assign({
            getLivewireComponent: lw,
            getFormState: function (key, el) {
                var c = lw(el);
                if (!c) return null;
                try { var v = c.get(key); return v === undefined ? null : v; } catch (e) { return null; }
            },
            setFormState: function (key, value, el) {
                var c = lw(el);
                if (!c) return false;
                try { c.set(key, value); return true; } catch (e) { return false; }
            },
            setFormStateMany: function (values, el) {
                var n = 0;
                Object.keys(values || {}).forEach(function (k) {
                    var v = values[k];
                    if ((typeof v === 'string' && v !== '') || typeof v === 'number') {
                        if (window.ScannerUI.setFormState(k, v, el)) n++;
                    }
                });
                return n;
            },
            injectFile: function (wrapperSelector, file) {
                var wrapper = document.querySelector(wrapperSelector);
                if (!wrapper || !file) {
                    console.error('[ScannerUI] wrapper tidak ditemukan:', wrapperSelector);
                    return false;
                }
                var pondEl = wrapper.querySelector('.filepond--root');
                if (pondEl && window.FilePond) {
                    var inst = window.FilePond.find(pondEl);
                    if (inst) { inst.addFile(file); return true; }
                }
                var fp = wrapper.querySelector('input[type=file].scan-photo-input') || wrapper.querySelector('input[type=file]');
                if (fp) {
                    var dt = new DataTransfer();
                    dt.items.add(file);
                    fp.files = dt.files;
                    fp.dispatchEvent(new Event('change', { bubbles: true }));
                    return true;
                }
                console.error('[ScannerUI] input file tidak ditemukan di', wrapperSelector);
                return false;
            },
            waitForUploads: function (wrapperSelector, timeoutMs) {
                timeoutMs = timeoutMs || 20000;
                return new Promise(function (resolve) {
                    var start = Date.now();
                    (function poll() {
                        var elapsed = Date.now() - start;
                        var wrapper = document.querySelector(wrapperSelector);
                        var items = wrapper ? wrapper.querySelectorAll('.filepond--item') : [];
                        var busy = false;
                        items.forEach(function (el) {
                            var s = el.getAttribute('data-filepond-item-state') || '';
                            if (s === 'busy' || s === 'processing' || s === 'processing-queued') busy = true;
                            if (s === 'idle' && elapsed < 2500) busy = true;
                        });
                        if (elapsed >= 400 && !busy) { setTimeout(function () { resolve(true); }, 150); return; }
                        if (elapsed >= timeoutMs) { resolve(false); return; }
                        setTimeout(poll, 150);
                    })();
                });
            },
            callAction: function (name, args, el) {
                var c = lw(el);
                if (!c) return Promise.resolve(null);
                try {
                    if (typeof c[name] === 'function') return Promise.resolve(c[name].apply(c, args || []));
                    if (typeof c.$call === 'function') return Promise.resolve(c.$call.apply(c, [name].concat(args || [])));
                } catch (e) { return Promise.reject(e); }
                return Promise.resolve(null);
            }
        }, window.ScannerUI || {});
    })();
</script>

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

        /* Mirroring hanya soal TAMPILAN (CSS). File hasil jepret tidak pernah
         * dibalik, supaya teks KTP di foto selfie tetap terbaca. */
        cameraFacing: '',
        cameraLabel: '',
        /* true = file hasil jepret ikut dicermin (sama dengan yang terlihat di layar). */
        mirrorSaved: true,
        ocrBlob: null,   /* salinan TIDAK dicermin, khusus untuk OCR */
        ocrFile: null,

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

            // Selfie = kamera depan, dokumen = kamera belakang. Sebelumnya
            // facingMode selalu 'environment' sehingga mode selfie membuka
            // kamera belakang (tapi dicermin).
            this.facingMode = this.mode === 'selfie' ? 'user' : 'environment';
            this.activeCameraId = '';
            this.cameraFacing = '';

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

        /* Daftar kamera fisik yang terdeteksi di perangkat ini.
         *
         * Notebook dan HP bisa punya lebih dari satu kamera (depan, belakang,
         * ultra-wide, tele). Desktop biasanya cuma satu webcam, jadi daftar ini
         * sering cuma berisi satu tombol saja. */
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

        /* Constraint getUserMedia untuk kamera yang sedang aktif.
         * Kalau belum ada deviceId (mis. hanya satu kamera), pakai facingMode
         * supaya tetap jalan. */
        cameraConstraints() {
            const base = { width: { ideal: 1280 }, height: { ideal: 720 } };

            if (this.activeCameraId) {
                return { ...base, deviceId: { exact: this.activeCameraId } };
            }

            return { ...base, facingMode: this.facingMode };
        },

        /* Pindah ke kamera tertentu. Dipakai oleh tombol 'Kiri' dan 'Kanan'
         * di bawah preview. */
        async selectCamera(deviceId) {
            this.activeCameraId = deviceId;
            await this.startCamera();
        },

        async startCamera() {
            this.view = 'camera';
            this.photoTaken = false;
            await this.$nextTick();
            const video = this.$refs.camVideo;
            if (!video) return;
            try {
                if (this.stream) this.stream.getTracks().forEach((t) => t.stop());
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

                // Label kamera hanya terisi setelah izin kamera diberikan.
                await this.loadCameras();
            } catch (e) {
                // Fallback untuk browser yang tidak mendukung WebRTC in-page:
                // buka kamera native lewat input capture.
                this.view = 'source';
                document.getElementById(this.field + '-input-native-camera')?.click();
            }
        },

        stopCamera() {
            if (this.stream) {
                this.stream.getTracks().forEach((t) => t.stop());
                this.stream = null;
            }
            /* Tanpa stream tidak ada frame untuk dianalisis, dan suara
             * AI harus berhenti sekarang juga. */
            this.stopCoach();
        },

        /* Cermin seperti kaca HANYA untuk preview selfie kamera depan.
         * Mode 'document' tidak pernah dicermin (teks jadi terbalik). */
        get mirroredView() {
            return !(this.cameraFacing === 'environment' || (!this.cameraFacing && /back|rear|environment|belakang/i.test(this.cameraLabel || '')));
        },

        flipCamera() {
            this.facingMode = this.facingMode === 'user' ? 'environment' : 'user';

            // Penting: deviceId harus dibuang. Kalau tidak, cameraConstraints()
            // tetap memakai activeCameraId dan mengabaikan facingMode --
            // tombol 'balik' jadi tidaklonjakan apa-apa.
            this.activeCameraId = '';

            this.startCamera();
        },

        /* Gambar frame video APA ADANYA (tidak dibalik). Pencerminan hanya
         * efek tampilan lewat CSS. Kalau file ikut dibalik, teks KTP di foto
         * selfie menjadi terbalik dan tidak terbaca OCR / AI / petugas. */
        drawToCanvas(video, canvas) {
            const vw = video.videoWidth || 640;
            const vh = video.videoHeight || 480;

            /* Preview 4:3 + object-cover: yang terlihat hanya bagian tengah 4:3. */
            let sw = vw, sh = vh;
            if (vw / vh > 4 / 3) { sw = vh * 4 / 3; } else { sh = vw * 3 / 4; }
            let sx = (vw - sw) / 2, sy = (vh - sh) / 2;

            /* Mode dokumen: potong persis di kotak scanner (lebar 80%, rasio 1.586). */
            if (this.mode !== 'selfie') {
                const cw = sw * 0.80;
                const ch = cw / 1.586;
                sx += (sw - cw) / 2;
                sy += (sh - ch) / 2;
                sw = cw;
                sh = ch;
            }

            const w = Math.round(sw), h = Math.round(sh);
            canvas.width = w;
            canvas.height = h;
            const ctx = canvas.getContext('2d');

            /* Hasil jepret dicermin PERSIS seperti preview (cermin sudah ada di
             * piksel, jadi canvas tidak boleh dicermin lagi lewat CSS). */
            if (this.mirrorSaved && this.mirroredView) {
                ctx.save();
                ctx.translate(w, 0);
                ctx.scale(-1, 1);
                ctx.drawImage(video, sx, sy, sw, sh, 0, 0, w, h);
                ctx.restore();
            } else {
                ctx.drawImage(video, sx, sy, sw, sh, 0, 0, w, h);
            }

            /* Salinan tidak dicermin untuk OCR: teks KTP yang dicermin tidak bisa dibaca. */
            this.ocrBlob = null;
            const raw = document.createElement('canvas');
            raw.width = w;
            raw.height = h;
            raw.getContext('2d').drawImage(video, sx, sy, sw, sh, 0, 0, w, h);
            raw.toBlob((b) => { this.ocrBlob = b; }, 'image/jpeg', 0.92);
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
            canvas.toBlob((blob) => {
                if (!blob) return;
                const ext = this.field === 'selfie_photo' ? 'jpg' : 'jpg';
                const file = new File([blob], this.field + '-' + Date.now() + '.' + ext, { type: 'image/jpeg' });
                const ocr = this.ocrBlob
                    ? new File([this.ocrBlob], 'ocr-' + Date.now() + '.jpg', { type: 'image/jpeg' })
                    : null;
                this.openPreview(file, ocr);
            }, 'image/jpeg', 0.92);
        },

        /* ── file pickers (native camera, file, galeri) ── */
        /* Klik input SINKRON di dalam handler klik user. Dulu modal ditutup
         * dulu lalu setTimeout 100ms -> Safari/iOS memblokir karena user
         * gesture hilang. Modal tetap terbuka; onPicked() yang lanjut. */
        pickNativeCamera() {
            document.getElementById(this.field + '-input-native-camera')?.click();
        },

        pickFile() {
            document.getElementById(this.field + '-input-file')?.click();
        },

        pickGallery() {
            document.getElementById(this.field + '-input-gallery')?.click();
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
        openPreview(file, ocrFile = null) {
            this.file = file;
            this.ocrFile = ocrFile;
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
            return window.ScannerUI?.getFormState('data.identity_type', document.querySelector(this.wrapper)) || 'ktp';
        },

        fieldLabel() {
            return this.field === 'selfie_photo'
                ? {{ json_encode(__('Foto Selfie + Dokumen')) }}
                : {{ json_encode(__('Foto Dokumen Identitas')) }};
        },

        async scan() {
            if (!this.file) { this.close(); return; }

            // OCR belum termuat: tetap lampirkan fotonya, jangan dibuang diam-diam.
            if (!window.OCR) {
                window.ScannerUI?.injectFile(this.wrapper, this.file, this.file.name);
                await window.ScannerUI?.waitForUploads?.(this.wrapper);
                this.close();
                return;
            }

            this.view = 'preview';
            this.scanning = true;
            this.ocrError = '';
            this.ocrStatus = {{ json_encode(__('Menganalisis dokumen...')) }};
            this.ocrProgress = 0;

            try {
                // 1) Lampirkan file asli ke FilePond field terpilih
                const anchor = document.querySelector(this.wrapper);
                const injected = window.ScannerUI?.injectFile(this.wrapper, this.file, this.file.name);
                if (!injected) this.ocrError = {{ json_encode(__('Tidak dapat melampirkan foto. Silakan unggah manual.')) }};

                // 2) OCR client-side
                const identity = this.identityType();
                const text = await window.OCR.recognizeText(this.ocrFile || this.file, {
                    onProgress: (status, progress) => {
                        this.ocrStatus = this.translateOcrStatus(status);
                        this.ocrProgress = Math.round((progress || 0) * 100);
                    },
                });

                // Pastikan upload FilePond selesai sebelum Livewire me-render ulang form
                await window.ScannerUI?.waitForUploads?.(this.wrapper);

                // 3) Parse sesuai jenis dokumen
                const parsed = window.OCR.parseDocument(text, identity);

                // 4) Tulis ke form (auto-fill)
                const values = window.OCR.mapOcrToFormState(parsed);
                const written = window.ScannerUI?.setFormStateMany(values, anchor);
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
                mode: this.mode === 'selfie' ? 'face' : 'document',
                lang: document.documentElement.lang || '{{ app()->getLocale() }}',
                video: () => this.$refs.camVideo,
                /* Untuk mode dokumen, bingkai + kalimat AI menyesuaikan
                 * jenis dokumen yang dipilih di form (ktp | npwp | sim |
                 * passport). Mode selfie tidak memakai guide kartu. */
                docType: this.mode === 'selfie' ? null : this.identityType(),
                autoCapture: true,
                onCapture: () => this.capturePhoto(),
            });

            this.coach.start();

            /* Modal ini menggabungkan selfie + dokumen, jadi pengumuman
             * jenis dokumen hanya keluar ketika yang dipindai kartu. */
            if (this.mode !== 'selfie') this.coach.announceDocument(this.identityType());
        },

        stopCoach() {
            if (!this.coach) return;
            this.coach.stop();
            this.coach = null;
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
                class="fi-glass-modal w-full max-w-md overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
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
                        {{-- Canvas dicermin lewat CSS yang sama dengan video; file yang disimpan tidak dicermin. --}}
                        <canvas
                            x-ref="camCanvas"
                            x-show="photoTaken"
                            class="h-full w-full"
                            :class="mode === 'selfie' ? 'object-cover' : 'object-contain'"
                            :style="{ transform: 'none' }"
                            style="display:none;"
                        ></canvas>

                        {{-- Scanner box / guide overlay. Area luar kotak digelapkan lewat
                             box-shadow pada kotak guide; wrapper-nya overflow-hidden
                             jadi bayangan tidak meluber keluar preview. --}}
                        <div
                            x-show="!photoTaken"
                            x-transition.opacity.duration.300ms
                            class="pointer-events-none absolute inset-0 flex items-center justify-center overflow-hidden"
                        >
                            {{-- Selfie: face oval (atas) + document box (bawah).

                                 BUG YANG DIPERBAIKI: kedua guide lama positioned
                                 dengan `left-1/2 -translate-x-1/2` dan
                                 top-/bottom-[%]. Pada mode selfie keduanya berada
                                 di satu wrapper yang sama, jadi:
                                   - oval (top-12%, h-34%) menabrak kotak dokumen
                                     (bottom-8%, w-72%): tinggi 34% + 8% + tinggi
                                     kotak >= 100% -> keduanya saling tumpang tindih
                                     dan menyisakan garis horizontal tepat di
                                     tengah oval (terlihat di screenshot);
                                   - kotak dokumen punya `-translate-x-1/2` tanpa
                                     `left-1/2`, jadi translate-50% itu menggeser
                                     kotak KIRI dari posisi static-nya dan keluar
                                     dari area preview, menyisakan garis vertikal
                                     panjang di sisi kiri.

                                 Sekarang jadi grid 2 baris (1fr auto) dengan
                                 jarak eksplisit: guide tidak pernah Absolute,
                                 tidak bisa saling tumpang tindih, dan tidak
                                 bisa keluar dari kotaknya. --}}
                            {{-- Selfie: SVG viewBox 400x300 = rasio kotak 4:3 persis, tanpa
                                 distorsi. Oval wajah (rx60 ry80 = 3:4) di atas dan kotak KTP
                                 (190x119.8 = 1.586) di bawah, tidak saling menimpa.
                                 Dulu pakai grid + h-full sehingga oval mengecil jadi titik. --}}
                            <template x-if="mode === 'selfie'">
                                <svg class="h-full w-full" viewBox="0 0 400 300" preserveAspectRatio="none" aria-hidden="true">
                                    <defs>
                                        <mask id="doc-scan-selfie-mask">
                                            <rect width="400" height="300" fill="white" />
                                            <ellipse cx="200" cy="92" rx="60" ry="80" fill="black" />
                                            <rect x="105" y="176" width="190" height="119.8" rx="8" fill="black" />
                                        </mask>
                                    </defs>
                                    <rect width="400" height="300" fill="rgba(0,0,0,0.40)" mask="url(#doc-scan-selfie-mask)" />
                                    <ellipse cx="200" cy="92" rx="60" ry="80" fill="none" stroke="rgba(255,255,255,0.95)" stroke-width="2.5" vector-effect="non-scaling-stroke" />
                                    <rect x="105" y="176" width="190" height="119.8" rx="8" fill="none" stroke="rgba(255,255,255,0.95)" stroke-width="2.5" vector-effect="non-scaling-stroke" />
                                </svg>
                            </template>
                            {{-- Document only: rectangle guide --}}
                            <template x-if="mode !== 'selfie'">
                                <div class="flex h-full w-full items-center justify-center overflow-hidden">
                                    <div class="aspect-[1.586/1] w-[80%] rounded-lg border-2 border-white/90" style="box-shadow: 0 0 0 9999px rgba(0,0,0,0.40);"></div>
                                </div>
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
                        {{-- x-if butuh satu elemen root; teks polos di dalam <template x-if> tidak dirender. --}}
                        <span x-show="mode === 'selfie'">{{ __('Arahkan wajah ke oval dan dokumen ke kotak bawah.') }}</span>
                        <span x-show="mode !== 'selfie'" style="display:none;">{{ __('Letakkan dokumen di dalam kotak scanner.') }}</span>
                    </p>

                    {{-- Controls.

                             Satu tombol jepret di tengah, dikelilingi dua tombol
                             pemilih kamera: KAMERA KIRI dan KAMERA KANAN. Dua
                             tombol itu memindah stream ke device yang berbeda,
                             jadi user bisa pilih kamera fisik yang mau dipakai
                             tanpa lewat tombol "balik" yang cuma men-toggle.

                             Hanya tampil kalau perangkat memang punya >1
                             kamera -- di laptop yang cuma satu webcam, kedua
                             tombol disembunyikan supaya tidak membingungkan.
                             --}}
                    <div class="flex items-center justify-center gap-5 px-4 py-4">
                        {{-- Kamera kiri --}}
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

                        {{-- Tombol jepret --}}
                        <button
                            type="button"
                            x-show="!photoTaken"
                            x-on:click="capturePhoto()"
                            class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-primary-600 text-white shadow-lg transition hover:bg-primary-500 active:scale-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                            :aria-label="{{ json_encode(__('Ambil Foto')) }}"
                        >
                            <x-filament::icon icon="heroicon-m-camera" class="h-8 w-8" />
                        </button>

                        {{-- Kamera kanan --}}
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

                {{-- ── Preview / scanner view ── --}}
                {{--
                    Wrapper preview DISAMAI dengan gambar, bukan kotak 4/3 tetap.

                    Dulu: <div style="aspect-ratio:4/3"> membungkus <img
                    class="object-contain">. object-contain memastikan SELURUH foto
                    terlihat, jadi saat rasio foto bukan 4/3 selalu ada bar hitam
                    di atas/bawah atau kiri/kanan. Guide-overlay-nya `absolute inset-0`
                    ikut kotak 4/3 itu, bukan fotonya -- jadi kotak guide bergeser
                    dari dokumen, dan user meluruskan KTP ke tempat yang salah.

                    Sekarang img yang menentukan tinggi wrapper (w-fit + max-h),
                    jadi guide dan foto selalu punya koordinat yang sama.
                --}}
                <div x-show="view === 'preview'" style="display:none;">
                    <div class="relative mx-auto flex w-fit max-w-full items-center justify-center bg-black">
                        <img
                            :src="imageUrl"
                            alt="Preview"
                            class="block max-h-[60vh] w-auto max-w-full object-contain"
                        />
                        {{-- Tidak ada garis panduan di preview: foto sudah dipotong persis sesuai
                             garis saat jepret, dan OCR membaca seluruh foto ini. Overlay lama
                             (grid oval + kotak) menutupi wajah dan tidak cocok dengan isi foto. --}}

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
