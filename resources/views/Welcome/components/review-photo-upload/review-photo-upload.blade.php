@php
    $inputId = $inputId ?? 'review-photo-input';
    $wireModel = $wireModel ?? null;
    $isDisabled = $field->isDisabled();
@endphp

{{--
    Review Photo Upload — satu modal untuk Kamera, Video, Galeri, dan File.
    Diadaptasi dari avatar-browse-modal. Desktop: WebRTC kamera. Mobile: native capture.
--}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
<div
    x-data="{
        photoData: $wire.entangle('{{ $getStatePath() }}'),
        isOpen: false,
        showCamera: false,
        cameraMode: 'photo',
        stream: null,
        facingMode: 'user',
        photoTaken: false,
        recordedChunks: [],
        isRecording: false,
        recordedVideoUrl: null,
        mirroredView: false,

        open() {
            this.isOpen = true;
            this.showCamera = false;
            this.cameraMode = 'photo';
            this.photoTaken = false;
            this.isRecording = false;
            this.recordedVideoUrl = null;
            this.recordedChunks = [];
            document.body.classList.add('overflow-hidden');
        },

        close() {
            this.stopCamera();
            this.isOpen = false;
            this.showCamera = false;
            this.photoTaken = false;
            this.isRecording = false;
            this.recordedVideoUrl = null;
            this.recordedChunks = [];
            document.body.classList.remove('overflow-hidden');
        },

        isMobile() { return /Android|iPhone|iPad|iPod/i.test(navigator.userAgent); },

        async startCamera(mode) {
            this.showCamera = true;
            this.cameraMode = mode || 'photo';
            this.photoTaken = false;
            await this.$nextTick();
            const video = this.$refs.camVideo;
            if (!video) return;
            try {
                if (this.stream) { this.stream.getTracks().forEach(t => t.stop()); }
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: this.facingMode, width: { ideal: 1280 }, height: { ideal: 720 } },
                    audio: this.cameraMode === 'video',
                });
                video.srcObject = this.stream;
                await video.play();
            } catch (e) {
                alert('Tidak dapat mengakses kamera. Pastikan izin kamera diberikan.');
                this.showCamera = false;
            }
        },

        stopCamera() {
            if (this.stream) { this.stream.getTracks().forEach(t => t.stop()); this.stream = null; }
            if (this.isRecording) { this.isRecording = false; }
        },

        flipCamera() {
            this.facingMode = this.facingMode === 'user' ? 'environment' : 'user';
            this.mirroredView = this.facingMode === 'user';
            this.startCamera(this.cameraMode);
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

        retakePhoto() { this.photoTaken = false; this.startCamera('photo'); },

        usePhoto() {
            const canvas = this.$refs.camCanvas;
            if (!canvas) return;
            canvas.toBlob(blob => {
                if (!blob) return;
                const file = new File([blob], 'review-' + Date.now() + '.jpg', { type: 'image/jpeg' });
                this.injectFile(file);
                this.close();
            }, 'image/jpeg', 0.92);
        },

        startRecording() {
            if (!this.stream) return;
            this.recordedChunks = [];
            const preferredTypes = ['video/webm;codecs=vp9', 'video/webm;codecs=vp8', 'video/webm', 'video/mp4'];
            const mimeType = preferredTypes.find(t => MediaRecorder.isTypeSupported(t)) || 'video/webm';
            try {
                const recorder = new MediaRecorder(this.stream, { mimeType });
                recorder.ondataavailable = e => { if (e.data.size > 0) this.recordedChunks.push(e.data); };
                recorder.onstop = () => {
                    const blob = new Blob(this.recordedChunks, { type: mimeType });
                    this.recordedVideoUrl = URL.createObjectURL(blob);
                    this.stopCamera();
                };
                recorder.start(250);
                this.isRecording = true;
                setTimeout(() => { if (this.isRecording) { recorder.stop(); this.isRecording = false; } }, 60000);
            } catch (e) { console.error('Error starting recording:', e); }
        },

        stopRecording() {
            if (this.isRecording) { this.isRecording = false; }
        },

        useVideo() {
            if (this.recordedVideoUrl) {
                fetch(this.recordedVideoUrl)
                    .then(r => r.blob())
                    .then(blob => {
                        const file = new File([blob], 'review-video-' + Date.now() + '.webm', { type: blob.type });
                        this.injectFile(file);
                        this.close();
                    });
            }
        },

        pickNativeCamera() { this.close(); setTimeout(() => { const el = document.getElementById('review-native-camera'); if (el) el.click(); }, 100); },
        pickNativeVideo() { this.close(); setTimeout(() => { const el = document.getElementById('review-native-video'); if (el) el.click(); }, 100); },
        pickGallery() { this.close(); setTimeout(() => { const el = document.getElementById('review-gallery'); if (el) el.click(); }, 100); },
        pickFile() { this.close(); setTimeout(() => { const el = document.getElementById('review-file'); if (el) el.click(); }, 100); },

        onPicked(event) {
            const file = event.target.files?.[0];
            if (file) this.injectFile(file);
            event.target.value = '';
        },

        injectFile(file) {
            if (!file) return;
            const fp = document.querySelector('#{{ $inputId }} input[type=file]');
            if (fp) {
                const dt = new DataTransfer();
                dt.items.add(file);
                fp.files = dt.files;
                fp.dispatchEvent(new Event('change', { bubbles: true }));
            }
        },
    }"
    x-on:open-review-photo-upload.window="open()"
    x-on:keydown.escape.window="if (isOpen) { if (showCamera) { stopCamera(); showCamera = false; } else { close(); } }"
    class="contents"
>
    {{-- Hidden file inputs --}}
    <input type="file" accept="image/*" capture="environment" class="sr-only" id="review-native-camera" x-on:change="onPicked($event)">
    <input type="file" accept="video/*" capture="environment" class="sr-only" id="review-native-video" x-on:change="onPicked($event)">
    <input type="file" accept="image/*,video/*" class="sr-only" id="review-gallery" x-on:change="onPicked($event)">
    <input type="file" accept="image/*,video/*,.pdf,.doc,.docx" class="sr-only" id="review-file" x-on:change="onPicked($event)">

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
                    <h3 class="text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        <span x-show="!showCamera">{{ __('Pilih Sumber') }}</span>
                        <span x-show="showCamera" style="display:none" x-text="cameraMode === 'video' ? '{{ __('Rekam Video') }}' : '{{ __('Ambil Foto') }}'"></span>
                    </h3>
                    <div class="flex items-center gap-1">
                        <button type="button" x-show="showCamera" x-on:click="flipCamera()" class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/5 dark:hover:text-gray-300" style="display:none">
                            <x-filament::icon icon="heroicon-m-arrow-path" class="h-5 w-5" />
                        </button>
                        <button type="button" x-show="showCamera" x-on:click="stopCamera(); showCamera = false; photoTaken = false; recordedVideoUrl = null;" class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/5 dark:hover:text-gray-300" style="display:none">
                            <x-filament::icon icon="heroicon-m-arrow-left" class="h-5 w-5" />
                        </button>
                        <button type="button" x-on:click="close()" class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/5 dark:hover:text-gray-300">
                            <x-filament::icon icon="heroicon-m-x-mark" class="h-5 w-5" />
                        </button>
                    </div>
                </div>

                {{-- Source picker --}}
                <div x-show="!showCamera" class="space-y-1 px-4 py-3">
                    {{-- Kamera --}}
                    <button type="button" x-on:click="isMobile() ? pickNativeCamera() : startCamera('photo')" class="group flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium hover:bg-gray-50 dark:hover:bg-white/5 active:bg-gray-100 dark:active:bg-white/10">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-50 text-primary-600 ring-1 ring-primary-600/10 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/20">
                            <x-filament::icon icon="heroicon-o-camera" class="h-5 w-5" />
                        </span>
                        <span class="flex-1 text-left text-gray-700 dark:text-gray-200">{{ __('Kamera') }}</span>
                        <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4 text-gray-400" />
                    </button>

                    {{-- Video --}}
                    <button type="button" x-on:click="isMobile() ? pickNativeVideo() : startCamera('video')" class="group flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium hover:bg-gray-50 dark:hover:bg-white/5 active:bg-gray-100 dark:active:bg-white/10">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-50 text-primary-600 ring-1 ring-primary-600/10 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/20">
                            <x-filament::icon icon="heroicon-o-video-camera" class="h-5 w-5" />
                        </span>
                        <span class="flex-1 text-left text-gray-700 dark:text-gray-200">{{ __('Video') }}</span>
                        <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4 text-gray-400" />
                    </button>

                    {{-- Galeri --}}
                    <button type="button" x-on:click="pickGallery()" class="group flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium hover:bg-gray-50 dark:hover:bg-white/5 active:bg-gray-100 dark:active:bg-white/10">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-50 text-primary-600 ring-1 ring-primary-600/10 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/20">
                            <x-filament::icon icon="heroicon-o-photo" class="h-5 w-5" />
                        </span>
                        <span class="flex-1 text-left text-gray-700 dark:text-gray-200">{{ __('Galeri / Album') }}</span>
                        <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4 text-gray-400" />
                    </button>

                    {{-- File --}}
                    <button type="button" x-on:click="pickFile()" class="group flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium hover:bg-gray-50 dark:hover:bg-white/5 active:bg-gray-100 dark:active:bg-white/10">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-50 text-primary-600 ring-1 ring-primary-600/10 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/20">
                            <x-filament::icon icon="heroicon-o-folder" class="h-5 w-5" />
                        </span>
                        <span class="flex-1 text-left text-gray-700 dark:text-gray-200">{{ __('File / Folder') }}</span>
                        <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4 text-gray-400" />
                    </button>

                    <div class="my-1 border-t border-gray-100 dark:border-white/10"></div>

                    <button type="button" x-on:click="close()" class="w-full rounded-lg px-3 py-2.5 text-center text-sm font-semibold text-danger-600 dark:text-danger-400">
                        {{ __('Batal') }}
                    </button>
                </div>

                {{-- WebRTC Camera view --}}
                <div x-show="showCamera" style="display:none;">
                    <div class="relative bg-black" style="aspect-ratio:4/3;">
                        <video x-ref="camVideo" x-show="!photoTaken && cameraMode === 'photo'" autoplay playsinline muted class="h-full w-full object-cover" :style="mirroredView ? 'display:block; transform: scaleX(-1);' : 'display:block;'"></video>
                        <canvas x-ref="camCanvas" x-show="photoTaken" class="h-full w-full object-cover" :style="mirroredView ? 'transform: scaleX(-1);' : ''" style="display:none;"></canvas>
                        <video x-show="recordedVideoUrl && cameraMode === 'video' && !photoTaken" :src="recordedVideoUrl" class="h-full w-full object-cover" controls playsinline></video>
                        <div x-show="!photoTaken && !recordedVideoUrl && stream === null" class="absolute inset-0 flex items-center justify-center bg-gray-900/60" style="display:none;">
                            <svg class="h-8 w-8 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        </div>
                    </div>

                    <div class="flex items-center justify-center gap-4 px-4 py-4">
                        <button type="button" x-show="!photoTaken && !recordedVideoUrl && cameraMode === 'photo'" x-on:click="capturePhoto()" class="flex h-14 w-14 items-center justify-center rounded-full bg-primary-600 text-white shadow-lg">
                            <x-filament::icon icon="heroicon-m-camera" class="h-7 w-7" />
                        </button>
                        <button type="button" x-show="!photoTaken && !recordedVideoUrl && cameraMode === 'video'" x-on:click="isRecording ? stopRecording() : startRecording()" :class="isRecording ? 'bg-danger-600' : 'bg-primary-600'" class="flex h-14 w-14 items-center justify-center rounded-full text-white shadow-lg">
                            <span x-show="!isRecording" class="block h-8 w-8 rounded-full bg-white"></span>
                            <span x-show="isRecording" class="block h-6 w-6 rounded-sm bg-white"></span>
                        </button>
                        <div x-show="photoTaken" class="flex w-full items-center justify-between gap-3">
                            <button type="button" x-on:click="retakePhoto()" class="flex-1 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-gray-200">
                                {{ __('Ulangi') }}
                            </button>
                            <button type="button" x-on:click="usePhoto()" class="flex-1 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm">
                                {{ __('Gunakan Foto') }}
                            </button>
                        </div>
                        <div x-show="recordedVideoUrl" class="flex w-full items-center justify-between gap-3">
                            <button type="button" x-on:click="retakePhoto()" class="flex-1 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-gray-200">
                                {{ __('Ulangi') }}
                            </button>
                            <button type="button" x-on:click="useVideo()" class="flex-1 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm">
                                {{ __('Gunakan Video') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
</x-dynamic-component>
