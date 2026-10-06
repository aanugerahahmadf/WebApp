@php
    $faceWrapper = $faceWrapper ?? '.face-scan-wrapper';
    $aiVerification = $aiVerification ?? false;
    $initialAiResult = $initialAiResult ?? null;
    $initialAiJson = $initialAiResult ? json_encode($initialAiResult) : 'null';
@endphp

{{--
    Face Scan Modal — for the "Face Verification" field (face_scan_photo).

    Behaviour (aiVerification=false / default):
      • Camera only (no File / Galeri).
      • Live camera preview with an oval face scanner guide.
      • Captured frame is attached straight to the FilePond field.
        No OCR, no auto-fill — a human verification photo.

    Behaviour (aiVerification=true):
      • Same camera flow, but after capture the modal stays open
        and runs server-side AI verification (verifyFaceAi action).
      • Shows a status chip: similarity %, verified / mismatch / error.
      • If verified → "Selesai" button closes the modal.
      • If mismatch  → "Ambil Ulang" button re-opens camera.

    Window helpers used (exposed by resources/js/app-web/app-web.js):
      window.ScannerUI → injectFile(), getFormState(), callAction()
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
        field: 'face_scan_photo',
        wrapper: '{{ $faceWrapper }}',
        stream: null,
        facingMode: 'user',
        photoTaken: false,
        imageUrl: null,
        file: null,
        previousPath: '',
        cameraState: 'idle',   /* idle | starting | ready | error */
        cameraError: '',
        startToken: 0,         /* membatalkan startCamera() lama yang masih menggantung */
        cameraFacing: '',
        cameraLabel: '',
        mirrorSaved: true,   /* file hasil jepret ikut dicermin seperti preview */
        blackRetried: false,   /* sudah pernah coba kamera lain karena gambar hitam */

        /* Mirroring hanya soal TAMPILAN (CSS). File yang diunggah tidak dibalik. */

        /* AI verification state */
        aiEnabled: {{ $aiVerification ? 'true' : 'false' }},
        aiState: 'idle',        /* idle | uploading | verifying | done | error */
        aiResult: null,
        verifyFailed: false,   /* attempt terakhir ditolak, jepret ulang otomatis */

        /* Anchor elemen di dalam komponen Livewire (form), karena modal
           di-teleport ke body — mencegah getLivewireComponent() memilih
           komponen halaman yang salah. */
        anchor() {
            return document.querySelector(this.wrapper);
        },

        open() {
            this.isOpen = true;
            document.body.classList.add('overflow-hidden');
            this.facingMode = 'user';
            this.activeCameraId = '';
            this.cameraLabel = '';
            this.blackRetried = false;
            this.cameraFacing = '';
            this.photoTaken = false;
            this.aiState = 'idle';
            this.aiResult = null;
            this.previousPath = window.ScannerUI?.getFormState('data.face_scan_photo', this.anchor()) || '';

            /* Pre-fill AI result if user already verified (from mount). */
            const existing = {{ $initialAiJson }};
            if (existing && existing.verified) {
                this.aiState = 'done';
                this.aiResult = existing;
            }

            this.$nextTick(() => this.startCamera());
        },

        close() {
            this.startToken++;
            this.cameraState = 'idle';
            this.stopCamera();
            this.isOpen = false;
            this.aiState = 'idle';
            this.aiResult = null;
            document.body.classList.remove('overflow-hidden');
        },

        stopCamera() {
            if (this.stream) {
                this.stream.getTracks().forEach((t) => t.stop());
                this.stream = null;
            }
            /* Tanpa stream tidak ada frame untuk dianalisis, dan suara AI
             * harus berhenti sekarang juga. */
            this.stopCoach();
        },

        /* Daftar kamera fisik di perangkat ini. Laptop biasanya cuma satu webcam;
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

        /* Kalau deviceId belum ada (hanya satu kamera), jatuh ke facingMode. */
        cameraConstraints() {
            const base = { width: { ideal: 1280 }, height: { ideal: 720 } };

            if (this.activeCameraId) {
                return { ...base, deviceId: { exact: this.activeCameraId } };
            }

            return { ...base, facingMode: this.facingMode };
        },

        /* Dipakai tombol 'Kiri' dan 'Kanan' untuk pindah kamera. */
        async selectCamera(deviceId) {
            this.activeCameraId = deviceId;
            await this.startCamera();
        },

        /* Minta stream dengan batas waktu. Kalau getUserMedia menggantung (izin
         * tak dijawab / kamera dipakai aplikasi lain), jangan muter selamanya. */
        requestStream() {
            return new Promise((resolve, reject) => {
                let done = false;
                const timer = setTimeout(() => {
                    done = true;
                    reject(Object.assign(new Error('timeout'), { name: 'TimeoutError' }));
                }, 10000);

                navigator.mediaDevices.getUserMedia({ video: this.cameraConstraints(), audio: false }).then(
                    (stream) => {
                        clearTimeout(timer);
                        if (done) { stream.getTracks().forEach((t) => t.stop()); return; } /* datang telat: jangan bocor */
                        done = true;
                        resolve(stream);
                    },
                    (err) => {
                        clearTimeout(timer);
                        if (!done) { done = true; reject(err); }
                    },
                );
            });
        },

        cameraErrorMessage(e) {
            const map = {
                NotAllowedError: {{ json_encode(__('Izin kamera ditolak. Aktifkan izin kamera di pengaturan browser.')) }},
                NotFoundError: {{ json_encode(__('Kamera tidak ditemukan di perangkat ini.')) }},
                NotReadableError: {{ json_encode(__('Kamera sedang dipakai aplikasi atau tab lain. Tutup lalu coba lagi.')) }},
                TimeoutError: {{ json_encode(__('Kamera tidak merespons. Coba lagi.')) }},
            };
            if (!navigator.mediaDevices) {
                return {{ json_encode(__('Kamera hanya bisa dipakai lewat koneksi aman (HTTPS).')) }};
            }
            return map[e?.name] || {{ json_encode(__('Tidak dapat mengakses kamera.')) }};
        },

        /* Cek apakah video benar-benar menampilkan gambar. Kamera yang tertutup,
         * dipakai aplikasi lain, atau kamera virtual sering 'berhasil dibuka'
         * tapi hanya mengirim frame hitam. Polling sampai 3 detik. */
        async waitForPicture(video, token) {
            const c = document.createElement('canvas');
            c.width = 16; c.height = 12;
            const ctx = c.getContext('2d', { willReadFrequently: true });

            for (let i = 0; i < 12; i++) {
                await new Promise((r) => setTimeout(r, 250));
                if (token !== this.startToken || !this.isOpen) return true; /* dibatalkan */
                if (video.readyState < 2 || !video.videoWidth) continue;

                ctx.drawImage(video, 0, 0, c.width, c.height);
                const d = ctx.getImageData(0, 0, c.width, c.height).data;
                let max = 0;
                for (let p = 0; p < d.length; p += 4) {
                    max = Math.max(max, d[p], d[p + 1], d[p + 2]);
                }
                if (max > 12) return true;
            }

            return false;
        },

        /* Gambar hitam: coba kamera lain (bukan kamera virtual) sekali, kalau
         * tetap hitam tampilkan penyebab yang jelas, bukan layar hitam kosong. */
        async handleBlackFrames() {
            const virtual = /virtual|obs|droidcam|manycam|snap camera|iriun|epoccam|phone link|nvidia broadcast/i;
            const alt = this.cameras.find((cam) =>
                cam.deviceId && cam.deviceId !== this.activeCameraId && !virtual.test(cam.label || ''));

            if (!this.blackRetried && alt) {
                this.blackRetried = true;
                this.stopCamera();
                this.activeCameraId = alt.deviceId;
                return this.startCamera();
            }

            const name = this.cameraLabel ? ' (' + this.cameraLabel + ')' : '';
            this.stopCamera();
            this.cameraState = 'error';
            this.cameraError = {{ json_encode(__('Kamera terbuka tapi gambarnya hitam')) }} + name + '. ' +
                {{ json_encode(__('Buka penutup kamera, tutup aplikasi/tab lain yang memakai kamera, lalu coba lagi.')) }};
        },

        openNativeCamera() {
            document.getElementById('face-scan-native-capture')?.click();
        },

        async startCamera() {
            const token = ++this.startToken;
            this.cameraState = 'starting';
            this.cameraError = '';

            await this.$nextTick();
            const video = this.$refs.camVideo || document.getElementById('face-scan-video');
            if (!video) {
                this.cameraState = 'error';
                this.cameraError = {{ json_encode(__('Tampilan kamera tidak ditemukan.')) }};
                return;
            }

            this.stopCamera();

            try {
                const stream = await this.requestStream();

                // Ada startCamera() yang lebih baru / modal sudah ditutup: buang stream ini.
                if (token !== this.startToken || !this.isOpen) {
                    stream.getTracks().forEach((t) => t.stop());
                    return;
                }

                this.stream = stream;
                video.srcObject = stream;
                await video.play();
                if (token !== this.startToken) return;

                const settings = stream.getVideoTracks()[0]?.getSettings?.() || {};
                this.activeCameraId = settings.deviceId || this.activeCameraId;
                this.cameraFacing = settings.facingMode || '';
                this.cameraLabel = stream.getVideoTracks()[0]?.label || '';
                this.cameraState = 'ready';

                await this.loadCameras();

                /* Panduan AI baru hidup setelah gambar benar-benar mengalir,
                 * supaya tidak bicara ke kamera yang masih hitam. */
                this.startCoach();

                if (!(await this.waitForPicture(video, token))) {
                    await this.handleBlackFrames();
                }
            } catch (e) {
                if (token !== this.startToken) return;
                this.stopCamera();

                // deviceId tersimpan sudah tidak valid -> ulangi sekali pakai facingMode.
                if (this.activeCameraId && ['OverconstrainedError', 'NotFoundError'].includes(e?.name)) {
                    this.activeCameraId = '';
                    return this.startCamera();
                }

                this.cameraState = 'error';
                this.cameraError = this.cameraErrorMessage(e);
            }
        },

        /* Preview dicermin seperti kaca (kecuali kamera belakang). Hanya CSS. */
        get mirroredView() {
            return !(this.cameraFacing === 'environment' || (!this.cameraFacing && /back|rear|environment|belakang/i.test(this.cameraLabel || '')));
        },

        /* Gambar frame video APA ADANYA (tidak dibalik). Pencerminan hanya
         * efek tampilan lewat CSS. Kalau file ikut dibalik, teks KTP di foto
         * selfie menjadi terbalik dan tidak terbaca OCR / AI / petugas. */
        drawToCanvas(video, canvas) {
            const vw = video.videoWidth || 640;
            const vh = video.videoHeight || 480;

            /* Ambil bagian 4:3 yang terlihat di layar (object-cover). */
            let sw = vw, sh = vh;
            if (vw / vh > 4 / 3) { sw = vh * 4 / 3; } else { sh = vw * 3 / 4; }
            const sx = (vw - sw) / 2, sy = (vh - sh) / 2;
            const w = Math.round(sw), h = Math.round(sh);

            canvas.width = w;
            canvas.height = h;
            const ctx = canvas.getContext('2d');

            /* Dicermin di piksel (sama dengan preview); canvas tidak dicermin lagi lewat CSS. */
            if (this.mirrorSaved && this.mirroredView) {
                ctx.save();
                ctx.translate(w, 0);
                ctx.scale(-1, 1);
                ctx.drawImage(video, sx, sy, sw, sh, 0, 0, w, h);
                ctx.restore();
            } else {
                ctx.drawImage(video, sx, sy, sw, sh, 0, 0, w, h);
            }
        },

        capturePhoto() {
            const video = this.$refs.camVideo;
            const canvas = this.$refs.camCanvas;
            if (!video || !canvas) return;
            this.drawToCanvas(video, canvas);
            this.stopCamera();
            this.photoTaken = true;

            // Hasil verifikasi lama bukan milik foto baru ini.
            this.aiState = 'idle';
            this.aiResult = null;
        },

        usePhoto() {
            const canvas = this.$refs.camCanvas;
            if (!canvas) return;
            canvas.toBlob(async (blob) => {
                if (!blob) return;
                const file = new File([blob], 'face-scan-' + Date.now() + '.jpg', { type: 'image/jpeg' });
                this.previousPath = window.ScannerUI?.getFormState('data.face_scan_photo', this.anchor()) || '';
                const attached = window.ScannerUI?.injectFile(this.wrapper, file, file.name);
                if (!attached) {
                    alert({{ json_encode(__('Foto gagal dilampirkan ke form. Muat ulang halaman lalu coba lagi.')) }});
                    return;
                }

                /* Tunggu FilePond selesai mengunggah dulu. Kalau Livewire me-render ulang
                 * (verifikasi AI / tutup modal) saat upload masih jalan, item FilePond
                 * hilang dan muncul error 'Cannot read properties of null (reading
                 * filename)'.
                 *
                 * CATATAN: jangan pernah memakai tanda kutip ganda di dalam
                 * x-data. Atributnya dibungkus kutip ganda, jadi satu saja
                 * akan menutup atribut lebih awal dan seluruh JavaScript di
                 * bawahnya dibrowser sebagai teks -- Alpine gagal parse dan
                 * semua properti jadi 'not defined'. */
                await window.ScannerUI?.waitForUploads?.(this.wrapper);

                if (this.aiEnabled) {
                    this.runAiVerification();
                } else {
                    this.close();
                }
            }, 'image/jpeg', 0.92);
        },

        async runAiVerification() {
            this.aiState = 'uploading';
            this.aiResult = null;
            this.verifyFailed = false;

            /* Wait for the FilePond upload to finish (state gets the stored path). */
            const newPath = await this.waitForUpload(12000);

            if (!newPath) {
                this.aiState = 'error';
                this.aiResult = {
                    verified: false,
                    similarity: null,
                    reason: 'UPLOAD_FAILED',
                    message: {{ json_encode(__('Gagal mengunggah foto wajah. Silakan coba lagi.')) }},
                };
                return;
            }

            this.aiState = 'verifying';

            try {
                const result = await window.ScannerUI?.callAction('verifyFaceAi', [], this.anchor());
                this.aiResult = result;
                this.aiState = (result && result.verified) ? 'done' : 'error';

                /* Server sudah bicara lewat result.reason. Coach mengubahnya
                 * jadi arahan yang bisa dikerjakan (posisi, pencahayaan, mata)
                 * lalu otomatis memulai attempt berikutnya -- tanpa tombol. */
                if (this.coach) {
                    const key = this.coach.reportVerdict(result);

                    const recoverable = key === 'VERDICT_RETRY'
                        || key === 'VERDICT_NO_FACE'
                        || key === 'VERDICT_MULTIPLE_FACES'
                        || key === 'VERDICT_NOT_MATCH'
                        || key === 'VERDICT_EYES_CLOSED'
                        || key === 'VERDICT_BLURRY';

                    if (recoverable && this.coach.attemptsLeft > 0) {
                    this.verifyFailed = true;
                        /* Beri jeda pendek supaya user sempat membaca /
                         * mendengar arahani sebelum kamera menyala lagi. */
                        setTimeout(() => this.retakePhoto(), 2500);
                    }
                }
            } catch (e) {
                this.aiState = 'error';
                this.aiResult = {
                    verified: false,
                    similarity: null,
                    reason: 'UNAVAILABLE',
                    message: {{ json_encode(__('Gagal menghubungi server AI. Silakan coba lagi.')) }},
                };

                /* Jaringan/server mati: mengulang attempt hanya akan gagal
                 * sama. Beri tahu sekali lalu hentikan loop otomatis. */
                if (this.coach) this.coach.reportVerdict(this.aiResult);
            }
        },

        waitForUpload(timeoutMs) {
            return new Promise((resolve) => {
                const start = Date.now();
                const check = () => {
                    const current = window.ScannerUI?.getFormState('data.face_scan_photo', this.anchor()) || '';
                    if (current && current !== this.previousPath) {
                        resolve(current);
                        return;
                    }
                    if (Date.now() - start > timeoutMs) {
                        resolve(null);
                        return;
                    }
                    setTimeout(check, 300);
                };
                check();
            });
        },

        retryVerification() {
            if (this.aiResult?.reason === 'UPLOAD_FAILED') {
                this.photoTaken ? this.usePhoto() : this.retakePhoto();
                return;
            }
            this.runAiVerification();
        },

        retakePhoto() {
            this.photoTaken = false;
            this.aiState = 'idle';
            this.aiResult = null;
            this.verifyFailed = false;

            /* Lepas cooldown jepret otomatis supaya attempt berikutnya boleh
             * terjadi lagi setelah verifikasi ditolak. */
            this.coach?.armAutoCapture();

            this.startCamera();
        },

        async onNativePicked(event) {
            const file = event.target.files?.[0];
            if (file) {
                this.previousPath = window.ScannerUI?.getFormState('data.face_scan_photo', this.anchor()) || '';
                window.ScannerUI?.injectFile(this.wrapper, file, file.name);
                await window.ScannerUI?.waitForUploads?.(this.wrapper);
                if (this.aiEnabled) {
                    this.runAiVerification();
                } else {
                    this.close();
                }
            }
            event.target.value = '';
        },

        /* ── Real-time AI scan coach ──────────────────────────────────────
         * Mengukur fokus, pencahayaan, dan posisi wajah dari frame video
         * langsung, lalu memberi arahan secara lisan (suara AI) dan visual
         * (lingkaran jadi hijau saat kualitas siap).
         *
         * Seluruh algoritma-nya ada di resources/js/ai-scan-coach supaya objek
         * x-data ini tetap tipis. Analisis real-time tidak mungkin lewat
         * server: satu round-trip per frame jauh terlalu lambat untuk jadi
         * panduan langsung.
         *
         * PENTING: hanya memakai kutip tunggal di dalam x-data. Atributnya
         * dibungkus kutip ganda, satu saja akan menutupnya lebih awal. */
        coach: null,

        startCoach() {
            this.stopCoach();

            const factory = window.AIScanCoach && window.AIScanCoach.create;
            if (!factory) return;

            const mount = this.$refs.camWrap;
            if (!mount) return;

            this.coach = factory({
                mount: mount,
                mode: 'face',
                lang: document.documentElement.lang || '{{ app()->getLocale() }}',
                video: () => this.$refs.camVideo,
                /* Jepret otomatis: coach bicara sampai foto bombardir adequate,
                 * lalu memotret sendiri. Tidak ada tombol jepret. */
                autoCapture: true,
                onCapture: () => this.capturePhoto(),
            });

            this.coach.start();
        },

        stopCoach() {
            if (!this.coach) return;
            this.coach.stop();
            this.coach = null;
        },
    }"
    x-on:open-face-scan.window="open()"
    x-on:keydown.escape.window="if (isOpen) close()"
    x-on:livewire:navigating.window="close()"
    class="contents"
>
    <input
        type="file"
        accept="image/*"
        capture="user"
        class="sr-only"
        x-ref="nativeCapture"
        id="face-scan-native-capture"
        x-on:change="onNativePicked($event)"
    >

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
            aria-labelledby="face-scan-title"
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
                    <h3 id="face-scan-title" class="text-base font-semibold leading-6 text-gray-950 dark:text-white">{{ __('Verifikasi Wajah') }}</h3>
                    <button
                        type="button"
                        x-on:click="close()"
                        class="relative -m-1.5 flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 outline-none transition duration-75 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/5 dark:hover:text-gray-300"
                        aria-label="{{ __('Tutup') }}"
                    >
                        <x-filament::icon icon="heroicon-m-x-mark" class="h-5 w-5" />
                    </button>
                </div>

                {{-- ── Camera body ── --}}
                <div x-ref="camWrap" class="relative overflow-hidden bg-black" style="aspect-ratio:4/3;">
                    <video
                        x-ref="camVideo"
                        id="face-scan-video"
                        x-show="!photoTaken"
                        autoplay
                        playsinline
                        muted
                        class="h-full w-full object-cover"
                        :style="mirroredView ? 'display:block; transform: scaleX(-1);' : 'display:block;'"
                    ></video>
                    {{-- Canvas dicermin lewat CSS yang sama dengan video; file yang diunggah tidak dicermin. --}}
                    <canvas
                        x-ref="camCanvas"
                        x-show="photoTaken"
                        class="h-full w-full object-cover"
                            :style="{ transform: 'none' }"
                        style="display:none;"
                    ></canvas>

                    {{-- Face oval scanner guide.
                         Dulu: div rounded-[48%] + box-shadow 9999px + -translate-*.
                         Radius 48% bukan elips sungguhan (ada sisi lurus), dan
                         box-shadow pada elemen yang di-translate sering memunculkan
                         garis tipis / bergeser. SVG mask: elips murni, area luar
                         digelapkan, dan skalanya selalu sama dengan kotak 4:3. --}}
                    <svg
                        x-show="!photoTaken"
                        class="pointer-events-none absolute inset-0 h-full w-full"
                        viewBox="0 0 400 300"
                        preserveAspectRatio="none"
                        aria-hidden="true"
                    >
                        <defs>
                            <mask id="face-scan-guide-mask">
                                <rect width="400" height="300" fill="white" />
                                <ellipse cx="200" cy="150" rx="84" ry="112" fill="black" />
                            </mask>
                        </defs>
                        <rect width="400" height="300" fill="rgba(0,0,0,0.45)" mask="url(#face-scan-guide-mask)" />
                        <ellipse cx="200" cy="150" rx="84" ry="112" fill="none" stroke="rgba(255,255,255,0.9)" stroke-width="2.5" vector-effect="non-scaling-stroke" />
                    </svg>

                    {{-- Loading indicator --}}
                    <div
                        x-show="cameraState === 'starting' && !photoTaken"
                        class="absolute inset-0 flex items-center justify-center bg-gray-900/60"
                        style="display:none;"
                    >
                        <svg class="h-8 w-8 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                    </div>

                    {{-- Kamera gagal: tampilkan alasannya, jangan muter terus --}}
                    <div
                        x-show="cameraState === 'error' && !photoTaken"
                        class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 bg-gray-950/85 px-6 text-center"
                        style="display:none;"
                    >
                        <p class="text-sm font-medium text-white" x-text="cameraError"></p>
                        <div class="flex gap-2">
                            <button type="button" x-on:click="startCamera()" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500">{{ __('Coba Lagi') }}</button>
                            <button type="button" x-on:click="openNativeCamera()" class="rounded-lg border border-white/30 px-4 py-2 text-sm font-semibold text-white hover:bg-white/10">{{ __('Kamera Bawaan') }}</button>
                        </div>
                    </div>
                </div>

                {{-- ── Instruction text ── --}}
                <p class="px-4 py-2 text-center text-xs text-gray-500 dark:text-gray-400"
                   x-text="aiState === 'done'
                       ? {{ json_encode(__('Wajah berhasil diverifikasi.')) }}
                       : aiState === 'error'
                           ? (aiResult?.reason === 'NO_KTP' ? {{ json_encode(__('Unggah foto KTP terlebih dahulu.')) }} : {{ json_encode(__('Verifikasi gagal. Ambil ulang dengan pencahayaan yang baik.')) }})
                           : aiState === 'verifying'
                               ? {{ json_encode(__('Memverifikasi wajah dengan AI...')) }}
                               : aiState === 'uploading'
                                   ? {{ json_encode(__('Mengunggah foto...')) }}
                                   : {{ json_encode(__('Posisikan wajah di dalam lingkaran.')) }}"
                ></p>

                {{-- ── Action buttons ──
                     TIDAK ada tombol jepret. AI Scan Coach mengukur frame
                     setiap saat, memberi arahan lewat suara, dan memotret
                     sendiri begitu kualitasnya stabil bagus; AI Core lalu
                     memverifikasi. Tombol yang tampil hanya pemilih kamera
                     KIRI / KANAN, yang memindah stream ke device berbeda dan
                     hanya muncul kalau perangkat punya lebih dari satu kamera. --}}
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

                    {{-- Tidak ada tombol jepret di sini. Foto diambil otomatis
                         oleh AI Scan Coach, bukan dengan menekan apa pun. --}}

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

                    {{-- Pas verifikasi ditolak, coach sedang mencoba lagi sendiri.
                         Tampilkan status supaya user tahu itu bukan macet,
                         tapi juga tidak menawarkan tombol jepret. --}}
                    <template x-if="photoTaken && verifyFailed">
                        <div class="flex w-full items-center justify-center gap-3 px-4">
                            <div class="flex items-center gap-2 rounded-lg bg-amber-50 px-4 py-2.5 text-sm text-amber-800 ring-1 ring-amber-200 dark:bg-amber-900/20 dark:text-amber-200 dark:ring-amber-800">
                                <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <span>{{ __('AI mencoba lagi otomatis...') }}</span>
                            </div>
                        </div>
                    </template>

                    {{-- Post-capture: Retake + Use (when AI is disabled) --}}
                    <template x-if="photoTaken && !verifyFailed && (!aiEnabled || aiState === 'idle')">
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

                    {{-- Post-capture: AI verification in progress --}}
                    <template x-if="aiEnabled && (aiState === 'uploading' || aiState === 'verifying')">
                        <div class="flex w-full items-center justify-center gap-3">
                            <div class="flex items-center gap-2 rounded-lg bg-gray-100 px-4 py-2.5 text-sm text-gray-700 dark:bg-white/10 dark:text-gray-200">
                                <svg class="h-4 w-4 animate-spin text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <span x-text="aiState === 'uploading' ? {{ json_encode(__('Mengunggah foto...')) }} : {{ json_encode(__('Memverifikasi dengan AI...')) }}"></span>
                            </div>
                        </div>
                    </template>

                    {{-- AI result — verified (shown after capture OR when pre-filled from mount) --}}
                    <template x-if="aiEnabled && aiState === 'done'">
                        <div class="flex w-full flex-col gap-3">
                            <div class="rounded-lg bg-emerald-50 p-3 text-center ring-1 ring-emerald-200 dark:bg-emerald-900/20 dark:ring-emerald-800">
                                <div class="flex items-center justify-center gap-2 text-sm font-semibold text-emerald-700 dark:text-emerald-300">
                                    <x-filament::icon icon="heroicon-m-check-circle" class="h-5 w-5 text-emerald-500" />
                                    <span x-text="{{ json_encode(__('Wajah cocok: ')) }} + (aiResult?.similarity != null ? Number(aiResult.similarity).toFixed(1) + '%' : {{ json_encode(__('Terverifikasi')) }})"></span>
                                </div>
                                <p class="mt-1 text-xs text-emerald-600 dark:text-emerald-400" x-text="aiResult?.message || ''"></p>
                            </div>
                            <div class="flex gap-3">
                                <template x-if="!photoTaken">
                                    <button
                                        type="button"
                                        x-on:click="close()"
                                        class="flex-1 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 active:bg-primary-700"
                                    >
                                        {{ __('Selesai') }}
                                    </button>
                                </template>
                                <template x-if="photoTaken">
                                    <div class="flex w-full gap-3">
                                        <button
                                            type="button"
                                            x-on:click="retakePhoto()"
                                            class="flex-1 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 active:bg-gray-100 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10"
                                        >
                                            {{ __('Scan Ulang') }}
                                        </button>
                                        <button
                                            type="button"
                                            x-on:click="close()"
                                            class="flex-1 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 active:bg-primary-700"
                                        >
                                            {{ __('Selesai') }}
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    {{-- AI result — mismatch / error.
                         Kalau coach masih punya attempt tersisa, dia sudah
                         menjadwalkan ulang sendiri, jadi tombol "Ambil Ulang"
                         disembunyikan agar tidak terlihat seperti satu-satunya jalan. --}}
                    <template x-if="aiEnabled && aiState === 'error' && !verifyFailed">
                        <div class="flex w-full flex-col gap-3">
                            <div class="rounded-lg bg-red-50 p-3 text-center ring-1 ring-red-200 dark:bg-red-900/20 dark:ring-red-800">
                                <div class="flex items-center justify-center gap-2 text-sm font-semibold text-red-700 dark:text-red-300">
                                    <x-filament::icon icon="heroicon-m-x-circle" class="h-5 w-5 text-red-500" />
                                    <span x-text="aiResult?.reason === 'NO_KTP'
                                        ? {{ json_encode(__('Perlu foto KTP')) }}
                                        : (['UPLOAD_FAILED', 'UNAVAILABLE'].includes(aiResult?.reason) ? {{ json_encode(__('Verifikasi gagal')) }} : {{ json_encode(__('Wajah tidak cocok')) }})"></span>
                                </div>
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400" x-text="aiResult?.message || ''"></p>
                            </div>
                            <div class="flex gap-3">
                                <button
                                    type="button"
                                    x-on:click="retakePhoto()"
                                    class="flex-1 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 active:bg-gray-100 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10"
                                >
                                    {{ __('Ambil Ulang') }}
                                </button>
                                <button
                                    type="button"
                                    x-on:click="retryVerification()"
                                    class="flex-1 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 active:bg-primary-700"
                                >
                                    {{ __('Coba Lagi') }}
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>
</div>
