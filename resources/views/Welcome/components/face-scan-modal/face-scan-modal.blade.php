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

        /* AI verification state */
        aiEnabled: {{ $aiVerification ? 'true' : 'false' }},
        aiState: 'idle',        /* idle | uploading | verifying | done | error */
        aiResult: null,

        /* Anchor elemen di dalam komponen Livewire (form), karena modal
           di-teleport ke body — mencegah getLivewireComponent() memilih
           komponen halaman yang salah. */
        anchor() {
            return document.querySelector(this.wrapper);
        },

        open() {
            this.isOpen = true;
            document.body.classList.add('overflow-hidden');
            this.photoTaken = false;
            this.aiState = 'idle';
            this.aiResult = null;
            this.previousPath = window.ScannerUI.getFormState('data.face_scan_photo', this.anchor()) || '';

            /* Pre-fill AI result if user already verified (from mount). */
            const existing = {{ $initialAiJson }};
            if (existing && existing.verified) {
                this.aiState = 'done';
                this.aiResult = existing;
            }

            this.$nextTick(() => this.startCamera());
        },

        close() {
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
        },

        async startCamera() {
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
                const el = this.$refs.nativeCapture;
                if (el) {
                    el.click();
                    return;
                }
                alert({{ json_encode(__('Tidak dapat mengakses kamera. Pastikan izin kamera diberikan.')) }});
                this.isOpen = false;
                document.body.classList.remove('overflow-hidden');
            }
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

        usePhoto() {
            const canvas = this.$refs.camCanvas;
            if (!canvas) return;
            canvas.toBlob((blob) => {
                if (!blob) return;
                const file = new File([blob], 'face-scan-' + Date.now() + '.jpg', { type: 'image/jpeg' });
                window.ScannerUI.injectFile(this.wrapper, file, file.name);

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
                const result = await window.ScannerUI.callAction('verifyFaceAi', [], this.anchor());
                this.aiResult = result;
                this.aiState = (result && result.verified) ? 'done' : 'error';
            } catch (e) {
                this.aiState = 'error';
                this.aiResult = {
                    verified: false,
                    similarity: null,
                    reason: 'UNAVAILABLE',
                    message: {{ json_encode(__('Gagal menghubungi server AI. Silakan coba lagi.')) }},
                };
            }
        },

        waitForUpload(timeoutMs) {
            return new Promise((resolve) => {
                const start = Date.now();
                const check = () => {
                    const current = window.ScannerUI.getFormState('data.face_scan_photo', this.anchor()) || '';
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

        retakePhoto() {
            this.photoTaken = false;
            this.aiState = 'idle';
            this.aiResult = null;
            this.startCamera();
        },

        onNativePicked(event) {
            const file = event.target.files?.[0];
            if (file) {
                window.ScannerUI.injectFile(this.wrapper, file, file.name);
                if (this.aiEnabled) {
                    this.runAiVerification();
                } else {
                    this.close();
                }
            }
            event.target.value = '';
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
                class="w-full max-w-md overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
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

                    {{-- Face oval scanner guide --}}
                    <div
                        x-show="!photoTaken"
                        x-transition.opacity.duration.300ms
                        class="pointer-events-none absolute left-1/2 top-1/2 h-[62%] aspect-[3/4] -translate-x-1/2 -translate-y-1/2 rounded-[48%] border-2 border-white/90"
                        style="box-shadow: 0 0 0 9999px rgba(0,0,0,0.40);"
                    ></div>

                    {{-- Loading indicator --}}
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

                {{-- ── Action buttons ── --}}
                <div class="flex items-center justify-center gap-4 px-4 py-4">

                    {{-- Camera capture button (before photo taken) --}}
                    <button
                        type="button"
                        x-show="!photoTaken"
                        x-on:click="capturePhoto()"
                        class="flex h-14 w-14 items-center justify-center rounded-full bg-primary-600 text-white shadow-lg transition hover:bg-primary-500 active:scale-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                        aria-label="{{ __('Ambil Foto') }}"
                    >
                        <x-filament::icon icon="heroicon-m-camera" class="h-7 w-7" />
                    </button>

                    {{-- Post-capture: Retake + Use (when AI is disabled) --}}
                    <template x-if="photoTaken && !aiEnabled">
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
                    <template x-if="photoTaken && aiEnabled && (aiState === 'uploading' || aiState === 'verifying')">
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
                    <template x-if="(photoTaken || (aiEnabled && aiState === 'done')) && aiEnabled && aiState === 'done'">
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

                    {{-- AI result — mismatch / error --}}
                    <template x-if="photoTaken && aiEnabled && aiState === 'error'">
                        <div class="flex w-full flex-col gap-3">
                            <div class="rounded-lg bg-red-50 p-3 text-center ring-1 ring-red-200 dark:bg-red-900/20 dark:ring-red-800">
                                <div class="flex items-center justify-center gap-2 text-sm font-semibold text-red-700 dark:text-red-300">
                                    <x-filament::icon icon="heroicon-m-x-circle" class="h-5 w-5 text-red-500" />
                                    <span x-text="aiResult?.reason === 'NO_KTP' ? {{ json_encode(__('Perlu foto KTP')) }} : {{ json_encode(__('Wajah tidak cocok')) }}"></span>
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
                                    x-on:click="retakePhoto()"
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
