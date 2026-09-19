{{--
    Messages Camera Modal
    ─────────────────────────────────────────────────────────────────
    Event trigger : window 'messages-camera-open'
    File target   : .messages-attachment-upload (FilePond)

    Platform matrix
    ───────────────────────────────────────────────────────
    Platform            Foto              Video          Galeri
    ─────────────────── ───────────────── ────────────── ──────────
    Web desktop W/Mac   WebRTC + flip     WebRTC rec     file picker
    Web mobile A/iOS    native capture    native video   file picker
    App mobile A/iOS    native capture    native video   file picker
    ───────────────────────────────────────────────────────

    WebRTC live view menyediakan:
      • Tombol flip (depan ↔ belakang) — user bisa arahkan kamera ke
        kiri/kanan/atas/bawah setelah buka karena device-nya mereka pegang
      • Mode FOTO : ambil frame → preview → gunakan / ulangi
      • Mode VIDEO: rekam MediaRecorder → stop → pratinjau → kirim
--}}
<div
    x-data="{
        open: false,
        mode: 'photo',        /* 'photo' | 'video' */
        phase: 'menu',        /* 'menu' | 'live' | 'preview' */
        facingMode: 'environment',
        stream: null,
        recorder: null,
        recordedChunks: [],
        isRecording: false,
        previewUrl: null,
        previewBlob: null,

        /* ── lifecycle ── */
        openModal() {
            this.open  = true;
            this.phase = 'menu';
            this.previewUrl = null;
            this.previewBlob = null;
            document.body.classList.add('overflow-hidden');
        },
        closeModal() {
            this.stopAll();
            this.open  = false;
            this.phase = 'menu';
            if (this.previewUrl) { URL.revokeObjectURL(this.previewUrl); this.previewUrl = null; }
            this.previewBlob = null;
            document.body.classList.remove('overflow-hidden');
        },
        backToMenu() {
            this.stopAll();
            this.phase = 'menu';
            if (this.previewUrl) { URL.revokeObjectURL(this.previewUrl); this.previewUrl = null; }
            this.previewBlob = null;
        },

        /* ── platform ── */
        isMobile() {
            return /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
        },

        /* ── WebRTC ── */
        async startLive(m, facing) {
            this.mode = m;
            this.facingMode = facing ?? this.facingMode;
            this.phase = 'live';
            this.isRecording = false;
            this.recordedChunks = [];
            await this.$nextTick();
            const video = this.$refs.liveVideo;
            if (!video) return;
            try {
                if (this.stream) this.stopAll();
                const constraints = {
                    video: { facingMode: this.facingMode, width: { ideal: 1280 }, height: { ideal: 720 } },
                    audio: m === 'video',
                };
                this.stream = await navigator.mediaDevices.getUserMedia(constraints);
                video.srcObject = this.stream;
                await video.play();
            } catch (e) {
                alert('{{ __('Kamera tidak dapat diakses. Pastikan izin diberikan.') }}');
                this.phase = 'menu';
            }
        },

        async flipCamera() {
            this.facingMode = this.facingMode === 'user' ? 'environment' : 'user';
            await this.startLive(this.mode, this.facingMode);
        },

        stopAll() {
            if (this.recorder && this.isRecording) {
                try { this.recorder.stop(); } catch(e) {}
                this.isRecording = false;
            }
            if (this.stream) {
                this.stream.getTracks().forEach(t => t.stop());
                this.stream = null;
            }
        },

        /* ── Foto: capture frame ── */
        capturePhoto() {
            const video  = this.$refs.liveVideo;
            const canvas = this.$refs.canvas;
            if (!video || !canvas) return;
            canvas.width  = video.videoWidth  || 640;
            canvas.height = video.videoHeight || 480;
            canvas.getContext('2d').drawImage(video, 0, 0);
            this.stopAll();
            canvas.toBlob(blob => {
                this.previewBlob = blob;
                this.previewUrl  = URL.createObjectURL(blob);
                this.phase = 'preview';
            }, 'image/jpeg', 0.92);
        },

        /* ── Video: start/stop recording ── */
        startRecording() {
            if (!this.stream) return;
            this.recordedChunks = [];
            const mimeType = MediaRecorder.isTypeSupported('video/webm;codecs=vp9')
                ? 'video/webm;codecs=vp9'
                : MediaRecorder.isTypeSupported('video/webm') ? 'video/webm' : 'video/mp4';
            this.recorder = new MediaRecorder(this.stream, { mimeType });
            this.recorder.ondataavailable = e => { if (e.data.size > 0) this.recordedChunks.push(e.data); };
            this.recorder.onstop = () => {
                const blob = new Blob(this.recordedChunks, { type: mimeType });
                this.previewBlob = blob;
                this.previewUrl  = URL.createObjectURL(blob);
                this.phase = 'preview';
            };
            this.recorder.start();
            this.isRecording = true;
        },
        stopRecording() {
            if (this.recorder && this.isRecording) {
                this.recorder.stop();
                this.isRecording = false;
                this.stopAll();
            }
        },

        /* ── Use captured file ── */
        useFile() {
            if (!this.previewBlob) return;
            const ext  = this.mode === 'video' ? 'webm' : 'jpg';
            const mime = this.previewBlob.type || (this.mode === 'video' ? 'video/webm' : 'image/jpeg');
            const file = new File([this.previewBlob], 'msg-' + this.mode + '-' + Date.now() + '.' + ext, { type: mime });
            this.injectFile(file);
            this.closeModal();
        },

        /* ── Native pickers (mobile / all platforms fallback) ── */
        pickNative(inputId) {
            this.closeModal();
            setTimeout(() => {
                const el = document.getElementById(inputId);
                if (el) el.click();
            }, 120);
        },
        onPicked(event) {
            const file = event.target.files?.[0];
            if (file) this.injectFile(file);
            event.target.value = '';
        },

        /* ── Inject to FilePond ── */
        injectFile(file) {
            if (!file) return;
            const pondEl = document.querySelector('.messages-attachment-upload .filepond--root');
            if (pondEl && window.FilePond) {
                const inst = window.FilePond.find(pondEl);
                if (inst) { inst.addFile(file); return; }
            }
            const inp = document.querySelector('.messages-attachment-upload input[type=file]');
            if (inp) {
                const dt = new DataTransfer();
                dt.items.add(file);
                inp.files = dt.files;
                inp.dispatchEvent(new Event('change', { bubbles: true }));
            }
        },
    }"
    x-on:messages-camera-open.window="openModal()"
    x-on:keydown.escape.window="if (open) closeModal()"
    x-on:livewire:navigating.window="closeModal()"
    class="contents"
>
    {{-- ── Hidden native inputs (used on mobile + desktop galeri/video) ── --}}
    {{-- Foto kamera (environment = belakang, user bisa pilih arah sendiri) --}}
    <input id="msg-native-photo"  type="file" accept="image/*" capture="environment" class="sr-only" x-on:change="onPicked($event)">
    {{-- Video kamera --}}
    <input id="msg-native-video"  type="file" accept="video/*" capture="environment" class="sr-only" x-on:change="onPicked($event)">
    {{-- Galeri foto --}}
    <input id="msg-native-gallery" type="file" accept="image/*,video/*"               class="sr-only" x-on:change="onPicked($event)">

    <template x-teleport="body">
        <div
            x-cloak
            x-show="open"
            x-transition:enter="transition ease-out duration-50"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-50"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-on:click.self="closeModal()"
            class="fixed inset-0 z-[9998] flex items-end justify-center bg-gray-950/65 p-3 pb-[calc(0.75rem+env(safe-area-inset-bottom,0px))] sm:items-center sm:p-4"
            role="dialog" aria-modal="true"
            style="display:none;"
        >
            <div
                x-show="open"
                x-on:click.stop
                x-transition:enter="transition ease-out duration-75"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="transition ease-in duration-50"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                class="w-full max-w-sm overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
            >

                {{-- ════ HEADER ════ --}}
                <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-white/10">
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                        <span x-show="phase === 'menu'">{{ __('Kamera & Media') }}</span>
                        <span x-show="phase === 'live'" style="display:none">
                            <span x-show="mode === 'photo'">{{ __('Foto') }}</span>
                            <span x-show="mode === 'video'" style="display:none">{{ __('Video') }}</span>
                        </span>
                        <span x-show="phase === 'preview'" style="display:none">{{ __('Pratinjau') }}</span>
                    </h3>
                    <div class="flex items-center gap-1">
                        {{-- flip — live only --}}
                        <button type="button" x-show="phase === 'live'" x-on:click="flipCamera()"
                            class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5"
                            style="display:none" aria-label="{{ __('Flip kamera') }}" style="touch-action:manipulation">
                            <x-filament::icon icon="heroicon-m-arrow-path" class="h-5 w-5" />
                        </button>
                        {{-- back — live/preview --}}
                        <button type="button" x-show="phase !== 'menu'" x-on:click="backToMenu()"
                            class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5"
                            style="display:none" aria-label="{{ __('Kembali') }}" style="touch-action:manipulation">
                            <x-filament::icon icon="heroicon-m-arrow-left" class="h-5 w-5" />
                        </button>
                        {{-- close --}}
                        <button type="button" x-on:click="closeModal()"
                            class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5"
                            aria-label="{{ __('Tutup') }}" style="touch-action:manipulation">
                            <x-filament::icon icon="heroicon-m-x-mark" class="h-5 w-5" />
                        </button>
                    </div>
                </div>

                {{-- ════ MENU ════ --}}
                <div x-show="phase === 'menu'" class="p-4">
                    {{-- Foto section --}}
                    <p class="mb-2 text-[10px] font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">{{ __('Foto') }}</p>
                    <div class="grid grid-cols-2 gap-2 mb-4">
                        {{-- Foto via WebRTC (all platforms) / native fallback --}}
                        <button type="button"
                            x-on:click="isMobile() ? pickNative('msg-native-photo') : startLive('photo', 'environment')"
                            class="flex flex-col items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-3 py-4 text-center transition-all hover:border-primary-400 hover:bg-primary-50 active:scale-95 dark:border-white/10 dark:bg-white/5 dark:hover:border-primary-500 dark:hover:bg-primary-500/10"
                            style="touch-action:manipulation">
                            <x-filament::icon icon="heroicon-o-camera" class="h-7 w-7 text-primary-500" />
                            <span class="text-xs font-medium text-gray-900 dark:text-white">
                                {{ __('Ambil Foto') }}
                            </span>
                            <span class="text-[10px] text-gray-400 dark:text-gray-500">{{ __('+ flip depan/belakang') }}</span>
                        </button>

                        {{-- Pilih dari Galeri --}}
                        <button type="button"
                            x-on:click="pickNative('msg-native-gallery')"
                            class="flex flex-col items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-3 py-4 text-center transition-all hover:border-primary-400 hover:bg-primary-50 active:scale-95 dark:border-white/10 dark:bg-white/5 dark:hover:border-primary-500 dark:hover:bg-primary-500/10"
                            style="touch-action:manipulation">
                            <x-filament::icon icon="heroicon-o-photo" class="h-7 w-7 text-primary-500" />
                            <span class="text-xs font-medium text-gray-900 dark:text-white">{{ __('Galeri') }}</span>
                            <span class="text-[10px] text-gray-400 dark:text-gray-500">{{ __('foto & video') }}</span>
                        </button>
                    </div>

                    {{-- Video section --}}
                    <p class="mb-2 text-[10px] font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">{{ __('Video') }}</p>
                    <div class="grid grid-cols-2 gap-2">
                        {{-- Rekam Video via WebRTC / native fallback --}}
                        <button type="button"
                            x-on:click="isMobile() ? pickNative('msg-native-video') : startLive('video', 'environment')"
                            class="flex flex-col items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-3 py-4 text-center transition-all hover:border-primary-400 hover:bg-primary-50 active:scale-95 dark:border-white/10 dark:bg-white/5 dark:hover:border-primary-500 dark:hover:bg-primary-500/10"
                            style="touch-action:manipulation">
                            <x-filament::icon icon="heroicon-o-video-camera" class="h-7 w-7 text-primary-500" />
                            <span class="text-xs font-medium text-gray-900 dark:text-white">{{ __('Rekam Video') }}</span>
                            <span class="text-[10px] text-gray-400 dark:text-gray-500">{{ __('+ flip depan/belakang') }}</span>
                        </button>

                        {{-- Pilih Video dari Galeri --}}
                        <button type="button"
                            x-on:click="pickNative('msg-native-gallery')"
                            class="flex flex-col items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-3 py-4 text-center transition-all hover:border-primary-400 hover:bg-primary-50 active:scale-95 dark:border-white/10 dark:bg-white/5 dark:hover:border-primary-500 dark:hover:bg-primary-500/10"
                            style="touch-action:manipulation">
                            <x-filament::icon icon="heroicon-o-film" class="h-7 w-7 text-primary-500" />
                            <span class="text-xs font-medium text-gray-900 dark:text-white">{{ __('Galeri Video') }}</span>
                            <span class="text-[10px] text-gray-400 dark:text-gray-500">{{ __('pilih file') }}</span>
                        </button>
                    </div>
                </div>

                {{-- ════ LIVE VIEW (WebRTC) ════ --}}
                <div x-show="phase === 'live'" style="display:none">
                    {{-- Video feed --}}
                    <div class="relative bg-black" style="aspect-ratio:4/3">
                        <video x-ref="liveVideo" autoplay playsinline muted
                            class="h-full w-full object-cover" style="display:block;"></video>

                        {{-- Recording indicator --}}
                        <div x-show="isRecording"
                            class="absolute top-3 left-3 flex items-center gap-1.5 rounded-full bg-red-600/90 px-2.5 py-1 text-xs font-semibold text-white"
                            style="display:none">
                            <span class="h-2 w-2 rounded-full bg-white animate-pulse"></span>
                            {{ __('REC') }}
                        </div>
                    </div>

                    {{-- Controls —— Foto --}}
                    <div x-show="mode === 'photo'" class="flex items-center justify-center gap-6 px-4 py-4" style="display:none">
                        <button type="button" x-on:click="capturePhoto()"
                            class="flex h-16 w-16 items-center justify-center rounded-full bg-primary-600 text-white shadow-lg transition hover:bg-primary-500 active:scale-90 focus:outline-none"
                            style="touch-action:manipulation" aria-label="{{ __('Ambil Foto') }}">
                            <x-filament::icon icon="heroicon-m-camera" class="h-8 w-8" />
                        </button>
                    </div>

                    {{-- Controls —— Video --}}
                    <div x-show="mode === 'video'" class="flex items-center justify-center gap-6 px-4 py-4" style="display:none">
                        {{-- Start --}}
                        <button type="button" x-show="!isRecording" x-on:click="startRecording()"
                            class="flex h-16 w-16 items-center justify-center rounded-full bg-red-600 text-white shadow-lg transition hover:bg-red-500 active:scale-90 focus:outline-none"
                            style="touch-action:manipulation" aria-label="{{ __('Mulai Rekam') }}">
                            <span class="h-5 w-5 rounded-full bg-white"></span>
                        </button>
                        {{-- Stop --}}
                        <button type="button" x-show="isRecording" x-on:click="stopRecording()"
                            class="flex h-16 w-16 items-center justify-center rounded-full bg-gray-800 text-white shadow-lg transition hover:bg-gray-700 active:scale-90 focus:outline-none"
                            style="display:none;touch-action:manipulation" aria-label="{{ __('Stop Rekam') }}">
                            <span class="h-5 w-5 rounded bg-white"></span>
                        </button>
                    </div>
                </div>

                {{-- ════ PREVIEW ════ --}}
                <div x-show="phase === 'preview'" style="display:none">
                    {{-- Foto preview --}}
                    <div x-show="mode === 'photo'" style="display:none" class="relative bg-black" style="aspect-ratio:4/3">
                        <img x-bind:src="previewUrl" class="h-full w-full object-cover" alt="">
                    </div>
                    {{-- Video preview --}}
                    <div x-show="mode === 'video'" style="display:none" class="relative bg-black" style="aspect-ratio:4/3">
                        <video x-bind:src="previewUrl" controls playsinline
                            class="h-full w-full object-cover"></video>
                    </div>
                    {{-- Actions --}}
                    <div class="flex items-center justify-between gap-3 px-4 py-4">
                        <button type="button" x-on:click="backToMenu()"
                            class="flex-1 rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-200"
                            style="touch-action:manipulation">{{ __('Ulangi') }}</button>
                        <button type="button" x-on:click="useFile()"
                            class="flex-1 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-500"
                            style="touch-action:manipulation">{{ __('Gunakan') }}</button>
                    </div>
                </div>

                {{-- hidden canvas for photo capture --}}
                <canvas x-ref="canvas" class="sr-only"></canvas>

            </div>
        </div>
    </template>
</div>
