@php
    $isDisabled = $field->isDisabled();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div x-data="{
            photoData: $wire.entangle('{{ $getStatePath() }}'),
            photoSelected: false,
            webcamActive: false,
            webcamError: null,
            cameraStream: null,
            availableCameras: [],
            selectedCameraId: null,
            modalOpen: false,
            showingPreview: false,
            aspectRatio: '{{ $getAspect() }}',
            imageQuality: {{ $getImageQuality() }},
            maxWidth: {{ $getMaxWidth() ?? 'null' }},
            maxHeight: {{ $getCaptureMaxWidth() ?? 'null' }},
            autoStart: {{ $getCaptureMaxHeight() ? 'true' : 'false' }},
            mirroredView: false,
            isBackCamera: false,
            isDisabled: {{ json_encode($isDisabled) }},
            isMobile: /iPhone|iPad|iPod|Android/i.test(navigator.userAgent),
            currentFacingMode: 'environment',
            componentId: '{{ $getId() }}',
            initialized: false,
            isHovering: false,
            captureMode: 'photo',
            mediaRecorder: null,
            recordedChunks: [],
            isRecording: false,
            recordedVideoUrl: null,
            showingVideoPreview: false,

            getImageUrl(path) {
                if (!path) return null;
                if (path.startsWith('data:image/')) return path;
                if (path.startsWith('http://') || path.startsWith('https://')) return path;
                const cleanPath = path.replace(/^\/+/, '');
                return '/storage/' + cleanPath;
            },

            async getCameras() {
                try {
                    const devices = await navigator.mediaDevices.enumerateDevices();
                    this.availableCameras = devices.filter(device => device.kind === 'videoinput');

                    if (this.availableCameras.length > 0 && !this.selectedCameraId && !this.isMobile) {
                        this.selectedCameraId = this.availableCameras[0].deviceId;
                        this.detectCameraType(this.availableCameras[0]);
                    }

                    return this.availableCameras;
                } catch (error) {
                    console.error('Error getting camera devices:', error);
                    this.webcamError = '{{ __('Unable to detect available cameras') }}';
                    return [];
                }
            },

            detectCameraType(camera) {
                if (!camera) return;

                const label = (camera.label || '').toLowerCase();

                this.isBackCamera = label.includes('back') ||
                                    label.includes('rear') ||
                                    label.includes('environment') ||
                                    label.includes('belakang') ||
                                    label.includes('0, facing back');

                this.mirroredView = !this.isBackCamera;
            },

            findCameraForFacing(facing) {
                const isFront = facing === 'user';
                const frontPatterns = ['front', 'user', 'facetime', 'depan', 'selfie', 'integrated', 'true depth'];
                const backPatterns = ['back', 'rear', 'environment', 'belakang', 'world', 'wide', 'trcamera', '0, facing back'];

                for (const cam of this.availableCameras) {
                    const label = (cam.label || '').toLowerCase();
                    const patterns = isFront ? frontPatterns : backPatterns;

                    if (patterns.some((pattern) => label.includes(pattern))) {
                        return cam;
                    }
                }

                if (this.availableCameras.length >= 2) {
                    const other = this.selectedCameraId
                        ? this.availableCameras.find((cam) => cam.deviceId !== this.selectedCameraId)
                        : null;

                    if (other) {
                        return other;
                    }

                    return isFront
                        ? this.availableCameras[this.availableCameras.length - 1]
                        : this.availableCameras[0];
                }

                return null;
            },

            buildStreamAttempts() {
                const audio = this.captureMode === 'video';
                const videoBase = {
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                };
                const attempts = [];

                if (this.selectedCameraId) {
                    attempts.push({
                        video: { ...videoBase, deviceId: { exact: this.selectedCameraId } },
                        audio,
                    });
                }

                attempts.push({
                    video: { ...videoBase, facingMode: { exact: this.currentFacingMode } },
                    audio,
                });
                attempts.push({
                    video: { ...videoBase, facingMode: { ideal: this.currentFacingMode } },
                    audio,
                });
                attempts.push({ video: { ...videoBase }, audio });

                return attempts;
            },

            async acquireStream() {
                let lastError = null;

                for (const constraints of this.buildStreamAttempts()) {
                    try {
                        return await navigator.mediaDevices.getUserMedia(constraints);
                    } catch (error) {
                        lastError = error;
                    }
                }

                throw lastError ?? new Error('Unable to access camera');
            },

            async applyCameraStream(stream) {
                this.cameraStream = stream;

                await this.$nextTick();

                if (this.$refs.video) {
                    this.$refs.video.srcObject = stream;

                    try {
                        await this.$refs.video.play();
                    } catch (error) {
                        // Autoplay can fail silently on some mobile browsers.
                    }
                }

                await this.getCameras();

                const videoTrack = stream.getVideoTracks()[0];

                if (videoTrack) {
                    const settings = videoTrack.getSettings();

                    if (settings.facingMode) {
                        this.currentFacingMode = settings.facingMode;
                        this.isBackCamera = settings.facingMode === 'environment';
                    } else {
                        this.isBackCamera = this.currentFacingMode === 'environment';
                    }

                    this.mirroredView = this.currentFacingMode === 'user';

                    if (settings.deviceId) {
                        this.selectedCameraId = settings.deviceId;
                    }
                }
            },

            async startCamera() {
                if (this.isDisabled) return;

                this.webcamError = null;

                try {
                    const stream = await this.acquireStream();
                    this.webcamActive = true;
                    await this.applyCameraStream(stream);

                    if ({{ $getUseModal() ? 'true' : 'false' }} && !this.modalOpen) {
                        this.openModal();
                    }
                } catch (error) {
                    console.error('Error accessing webcam:', error);
                    this.webcamActive = false;
                    this.handleWebcamError(error);
                }
            },

            async switchFacing(facing) {
                if (this.isRecording) {
                    this.stopVideoRecording();
                }

                this.captureMode = 'photo';
                this.showingVideoPreview = false;
                this.showingPreview = false;
                this.currentFacingMode = facing;
                this.isBackCamera = facing === 'environment';
                this.mirroredView = facing === 'user';

                this.stopCamera();
                await this.$nextTick();

                if (this.availableCameras.length === 0 || this.availableCameras.every((cam) => !cam.label)) {
                    try {
                        const tempStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
                        tempStream.getTracks().forEach((track) => track.stop());
                    } catch (error) {
                        // Permission may already be granted; continue with facingMode fallback.
                    }
                }

                await this.getCameras();

                const preferred = this.findCameraForFacing(facing);
                this.selectedCameraId = preferred ? preferred.deviceId : null;

                if (preferred) {
                    this.detectCameraType(preferred);
                }

                await this.startCamera();
            },

            async switchCaptureMode(mode) {
                if (this.isRecording) {
                    this.stopVideoRecording();
                }

                if (mode === 'gallery') {
                    this.closeModal();
                    window.dispatchEvent(new CustomEvent('cbir-pick-gallery'));

                    return;
                }

                this.captureMode = mode;
                this.showingPreview = false;
                this.showingVideoPreview = false;
                this.recordedChunks = [];

                if (this.recordedVideoUrl) {
                    URL.revokeObjectURL(this.recordedVideoUrl);
                    this.recordedVideoUrl = null;
                }

                this.selectedCameraId = null;

                if (this.webcamActive) {
                    this.stopCamera();
                    await this.$nextTick();
                }

                await this.startCamera();
            },

            startVideoRecording() {
                if (!this.cameraStream || this.isRecording) return;

                this.recordedChunks = [];
                const preferredTypes = ['video/webm;codecs=vp9', 'video/webm;codecs=vp8', 'video/webm', 'video/mp4'];
                const mimeType = preferredTypes.find((type) => MediaRecorder.isTypeSupported(type)) || 'video/webm';

                try {
                    this.mediaRecorder = new MediaRecorder(this.cameraStream, { mimeType });
                    this.mediaRecorder.ondataavailable = (event) => {
                        if (event.data.size > 0) {
                            this.recordedChunks.push(event.data);
                        }
                    };
                    this.mediaRecorder.onstop = () => {
                        const blob = new Blob(this.recordedChunks, { type: mimeType });
                        this.recordedVideoUrl = URL.createObjectURL(blob);
                        this.showingVideoPreview = true;
                        this.stopCamera();
                    };
                    this.mediaRecorder.start(250);
                    this.isRecording = true;
                } catch (error) {
                    console.error('Error starting video recording:', error);
                    this.webcamError = '{{ __('Gagal memulai rekam video.') }}';
                }
            },

            stopVideoRecording() {
                if (this.mediaRecorder && this.isRecording) {
                    this.mediaRecorder.stop();
                    this.isRecording = false;
                }
            },

            resetVideoState() {
                this.showingVideoPreview = false;
                this.recordedChunks = [];
                this.isRecording = false;
                this.mediaRecorder = null;

                if (this.recordedVideoUrl) {
                    URL.revokeObjectURL(this.recordedVideoUrl);
                    this.recordedVideoUrl = null;
                }
            },

            confirmVideo() {
                if (!this.recordedChunks.length) return;

                const mimeType = this.mediaRecorder?.mimeType || 'video/webm';
                const blob = new Blob(this.recordedChunks, { type: mimeType });
                const extension = mimeType.includes('mp4') ? 'mp4' : 'webm';
                const file = new File([blob], `cbir-video-${Date.now()}.${extension}`, { type: mimeType });

                this.$wire.upload(
                    'cameraUpload',
                    file,
                    () => {
                        this.resetVideoState();
                        this.captureMode = 'photo';
                        this.closeModal();
                    },
                    () => {
                        this.webcamError = '{{ __('Gagal mengunggah video.') }}';
                    }
                );
            },

            retakeVideo() {
                this.resetVideoState();
                this.startCamera();
            },

            handleWebcamError(error) {
                // NativePHP mobile runs on capacitor:// or http://localhost — allow camera without HTTPS
                const isNativePHP = window.location.protocol === 'capacitor:' || 
                                    (window.location.hostname === 'localhost' && /iPhone|iPad|iPod|Android/i.test(navigator.userAgent));
                if (/iPhone|iPad|iPod|Android/i.test(navigator.userAgent) && !isNativePHP) {
                    if (window.location.protocol !== 'https:') {
                        this.webcamError = '{{ __('Camera access requires HTTPS on mobile devices') }}';
                        return;
                    }
                }

                switch (error.name) {
                    case 'NotAllowedError':
                    case 'PermissionDeniedError':
                        this.webcamError = '{{ __("filament-take-picture-field::take-picture-field.error_permission_denied") }}';
                        break;

                    case 'NotFoundError':
                    case 'DevicesNotFoundError':
                        this.webcamError = '{{ __("filament-take-picture-field::take-picture-field.error_no_camera_found") }}';
                        break;

                    case 'NotReadableError':
                    case 'TrackStartError':
                        this.webcamError = '{{ __("filament-take-picture-field::take-picture-field.error_camera_in_use") }}';
                        break;

                    case 'OverconstrainedError':
                        this.webcamError = '{{ __("filament-take-picture-field::take-picture-field.error_overconstrained") }}';
                        break;

                    case 'SecurityError':
                        this.webcamError = '{{ __("filament-take-picture-field::take-picture-field.error_security") }}';
                        break;

                    case 'AbortError':
                        this.webcamError = '{{ __("filament-take-picture-field::take-picture-field.error_abort") }}';
                        break;

                    default:
                        this.webcamError = '{{ __("filament-take-picture-field::take-picture-field.error_unknown") }}';
                }
            },

            async changeCamera(cameraId) {
                this.selectedCameraId = cameraId;

                const camera = this.availableCameras.find(c => c.deviceId === cameraId);
                this.detectCameraType(camera);

                if (this.webcamActive) {
                    this.stopCamera();
                    await this.$nextTick();
                    this.startCamera();
                }
            },

            capturePhoto() {
                const video = this.$refs.video;
                const canvas = document.createElement('canvas');

                let targetWidth = video.videoWidth;
                let targetHeight = video.videoHeight;

                if (this.maxWidth || this.maxHeight) {
                    const aspectRatio = video.videoWidth / video.videoHeight;

                    if (this.maxWidth && this.maxHeight) {
                        if (targetWidth > this.maxWidth) {
                            targetWidth = this.maxWidth;
                            targetHeight = Math.round(targetWidth / aspectRatio);
                        }
                        if (targetHeight > this.maxHeight) {
                            targetHeight = this.maxHeight;
                            targetWidth = Math.round(targetHeight * aspectRatio);
                        }
                    } else if (this.maxWidth && targetWidth > this.maxWidth) {
                        targetWidth = this.maxWidth;
                        targetHeight = Math.round(targetWidth / aspectRatio);
                    } else if (this.maxHeight && targetHeight > this.maxHeight) {
                        targetHeight = this.maxHeight;
                        targetWidth = Math.round(targetHeight * aspectRatio);
                    }
                }

                canvas.width = targetWidth;
                canvas.height = targetHeight;
                const context = canvas.getContext('2d');

                if (this.mirroredView) {
                    context.translate(canvas.width, 0);
                    context.scale(-1, 1);
                }

                context.drawImage(video, 0, 0, targetWidth, targetHeight);

                const quality = this.imageQuality / 100;
                this.photoData = canvas.toDataURL('image/jpeg', quality);

                this.stopCamera();
                this.showingPreview = true;
            },

            confirmPhoto() {
                if (this.photoData && typeof this.$wire.searchFromCameraPhoto === 'function') {
                    this.$wire.searchFromCameraPhoto(this.photoData);
                }
                this.showingPreview = false;
                this.closeModal();
            },

            retakePhoto() {
                this.photoSelected = false;
                this.startCamera();
            },

            stopCamera() {
                this.webcamActive = false;

                if (this.$refs.video) {
                    this.$refs.video.srcObject = null;
                }

                if (this.cameraStream) {
                    this.cameraStream.getTracks().forEach(track => track.stop());
                    this.cameraStream = null;
                }
            },

            toggleMirror() {
                this.mirroredView = !this.mirroredView;
            },

            async flipCamera() {
                await this.switchFacing(this.currentFacingMode === 'environment' ? 'user' : 'environment');
            },

            async retakeInModal() {
                this.showingPreview = false;
                await this.$nextTick();
                this.startCamera();
            },

            handlePreviewClick() {
                if (!this.photoData) return;

                if (this.photoData.startsWith('data:image/')) {
                    const byteString = atob(this.photoData.split(',')[1]);
                    const mimeString = this.photoData.split(',')[0].split(':')[1].split(';')[0];
                    const ab = new ArrayBuffer(byteString.length);
                    const ia = new Uint8Array(ab);

                    for (let i = 0; i < byteString.length; i++) {
                        ia[i] = byteString.charCodeAt(i);
                    }

                    const blob = new Blob([ab], { type: mimeString });
                    const url = URL.createObjectURL(blob);
                    window.open(url, '_blank').focus();
                    return;
                }

                window.open(this.getImageUrl(this.photoData), '_blank');
            },

            isBase64Image() {
                return this.photoData && this.photoData.startsWith('data:image/');
            },

            clearPhoto() {
                this.photoData = null;
                this.photoSelected = false;
            },

            openModal() {
                this.modalOpen = true;
                document.body.classList.add('overflow-hidden');
            },

            closeModal() {
                if (this.isRecording) {
                    this.stopVideoRecording();
                }

                this.modalOpen = false;
                this.showingPreview = false;
                this.captureMode = 'photo';
                document.body.classList.remove('overflow-hidden');
                this.resetVideoState();
                this.stopCamera();
            },

            checkVisibilityAndAutoStart() {
                if (this.initialized || this.isDisabled || this.photoData) return;

                const el = this.$el;
                if (!el) return;

                const rect = el.getBoundingClientRect();
                const isVisible = rect.width > 0 && rect.height > 0 && window.getComputedStyle(el).display !== 'none';

                if (isVisible && this.autoStart) {
                    this.initialized = true;
                    this.$nextTick(() => {
                        this.startCamera();
                    });
                }
            }
        }" x-init="() => {
            // NativePHP mobile runs on capacitor:// or http://localhost — allow camera without HTTPS
            const isNativePHP = window.location.protocol === 'capacitor:' || 
                                (window.location.hostname === 'localhost' && /iPhone|iPad|iPod|Android/i.test(navigator.userAgent));
            if (window.location.protocol !== 'https:' && !isNativePHP && /iPhone|iPad|iPod|Android/i.test(navigator.userAgent)) {
                webcamError = '{{ __('Camera access requires HTTPS on mobile devices') }}';
            }

            if (!photoData) {
                if ({{ $getUseModal() ? 'false' : 'true' }}) {
                    startCamera();
                } else if (autoStart) {
                    const observer = new MutationObserver(() => {
                        checkVisibilityAndAutoStart();
                    });

                    observer.observe(document.body, {
                        childList: true,
                        subtree: true,
                        attributes: true,
                        attributeFilter: ['class', 'style']
                    });

                    checkVisibilityAndAutoStart();
                    setTimeout(() => checkVisibilityAndAutoStart(), 100);
                    setTimeout(() => checkVisibilityAndAutoStart(), 500);
                }
            } else if (!isBase64Image()) {
                photoSelected = true;
            }

            if ({{ $getShowCameraSelector() ? 'true' : 'false' }}) {
                getCameras();
            }

            window.addEventListener('cbir-open-webrtc-camera', (event) => {
                if (isDisabled) return;
                captureMode = 'photo';
                currentFacingMode = event.detail?.facing === 'user' ? 'user' : 'environment';
                selectedCameraId = null;
                startCamera();
            });
        }" @keydown.escape.window="if (modalOpen) { closeModal(); } else { stopCamera(); }" class="w-full">


        <!-- container -->
        <div class="w-full">

            <!-- Empty -->
            <template x-if="!photoData">
                <button type="button" @click="!isDisabled && startCamera()" :disabled="isDisabled" class="
                        relative w-full rounded-lg border-2 border-dashed
                        bg-white shadow-sm
                        border-gray-300 hover:border-primary-500 hover:bg-gray-50
                        dark:bg-white/5 dark:border-white/10 dark:hover:border-primary-400 dark:hover:bg-white/10
                        ring-1 ring-transparent dark:ring-white/10
                        transition-all duration-200
                        focus:outline-none focus:ring-2 focus:ring-primary-600/30 dark:focus:ring-primary-400/30
                    " :class="{
                        'cursor-not-allowed opacity-50': isDisabled,
                        'cursor-pointer': !isDisabled,
                    }">
                    <div class="flex flex-col items-center justify-center py-8 px-4">
                        <div class="mb-3 rounded-full p-3 bg-gray-100 dark:bg-white/10">
                            <x-filament::icon icon="heroicon-o-camera" class="h-6 w-6 text-gray-500 dark:text-gray-200" />
                        </div>

                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ __('filament-take-picture-field::take-picture-field.click_to_capture') }}
                        </p>

                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-300">
                            {{ __('filament-take-picture-field::take-picture-field.opens_camera_hint') }}
                        </p>
                    </div>
                </button>
            </template>


            <!-- Photo preview -->
            <template x-if="photoData">
                <div class="relative w-full rounded-lg bg-white dark:bg-gray-900 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm overflow-hidden" @mouseenter="isHovering = true" @mouseleave="isHovering = false">
                    <div class="flex items-center gap-4 p-3">

                        <!-- Thumbnail -->
                        <div class="relative flex-shrink-0 w-16 h-16 rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-800 ring-1 ring-gray-950/5 dark:ring-white/10">
                            <img :src="photoData ? getImageUrl(photoData) : ''" class="w-full h-full object-cover" alt="Captured photo">
                        </div>

                        <!-- Info -->
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-200 truncate">
                                {{ __('filament-take-picture-field::take-picture-field.captured_photo') }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                <span
                                    x-text="isBase64Image() ? '{{ __('filament-take-picture-field::take-picture-field.new_capture') }}' : '{{ __('filament-take-picture-field::take-picture-field.saved_photo') }}'">
                                </span>
                            </p>
                        </div>

                        <!-- Action buttons -->
                        <div class="flex items-center gap-1">

                            <!-- Manual Search Action -->
                            @if ($manualSearchAction = $field->getAction('manualSearch'))
                                <button
                                    type="button"
                                    @click.stop="$wire.mountFormComponentAction('{{ $getStatePath() }}', 'manualSearch')"
                                    class="flex items-center justify-center w-10 h-8 rounded-lg bg-primary-500/10 text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 hover:bg-primary-500/20 dark:hover:bg-primary-500/10 transition-all"
                                    title="{{ $manualSearchAction->getLabel() }}"
                                >
                                    <x-filament::icon
                                        icon="heroicon-m-arrow-up-tray"
                                        class="h-5 w-5"
                                    />
                                </button>
                            @endif

                            <!-- Preview button -->
                            <button type="button" @click.stop="handlePreviewClick()" class="flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors" title="{{ __('View full size') }}">
                                <x-filament::icon icon="heroicon-m-eye" class="h-5 w-5" />
                            </button>
                            <!-- Retake button -->
                            <button x-show="!isDisabled" type="button" @click.stop="startCamera()" class="flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors" title="{{ __('Retake photo') }}">
                                <x-filament::icon icon="heroicon-m-camera" class="h-5 w-5" />
                            </button>
                            <!-- Delete button -->
                            <button x-show="!isDisabled" type="button" @click.stop="clearPhoto()" class="flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-danger-500 hover:bg-danger-50 dark:hover:bg-danger-500/10 transition-colors" title="{{ __('Remove photo') }}">
                                <x-filament::icon icon="heroicon-m-trash" class="h-5 w-5" />
                            </button>
                        </div>

                    </div>
                </div>
            </template>

        </div>


        <!-- Error message -->
        <template x-if="webcamError && !modalOpen">
            <div class="mt-2 rounded-lg bg-danger-50 dark:bg-danger-400/10 p-3 text-sm text-danger-600 dark:text-danger-400">
                <div class="flex items-start gap-2">
                    <x-filament::icon icon="heroicon-m-exclamation-circle" class="h-5 w-5 flex-shrink-0" />
                    <span x-text="webcamError"></span>
                </div>
            </div>
        </template>


        <!-- Get state path -->
        <input type="hidden" {{ $applyStateBindingModifiers('wire:model') }}="{{ $getStatePath() }}">


        <!-- Modal -->
        <template x-teleport="body">
            <div x-cloak x-show="modalOpen" @click.self="closeModal()" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-gray-950/50 p-4" style="display: none;">
                <div @click.stop x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="w-full max-w-xl rounded-xl bg-white dark:bg-gray-900 shadow-xl ring-1 ring-gray-950/5 dark:ring-white/10">

                    <!-- Modal header -->
                    <div class="flex items-center justify-between border-b border-gray-200 dark:border-white/10 px-6 py-4">
                        <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                            {{ __('Ambil Foto') }}
                        </h3>
                        <button type="button" @click="closeModal()" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                            <x-filament::icon icon="heroicon-m-x-mark" class="h-5 w-5" />
                        </button>
                    </div>

                    <!-- Modal body -->
                    <div class="p-6">
                        <div class="relative rounded-lg overflow-hidden bg-gray-950 mb-4">

                            <!-- Video preview (camera active) -->
                            <div x-show="webcamActive && !webcamError" class="aspect-video flex items-center justify-center">
                                <video x-ref="video" autoplay playsinline :style="mirroredView ? 'transform: scaleX(-1);' : ''" class="max-w-full max-h-[60vh] object-contain"></video>
                            </div>

                            <!-- Photo preview (after capture) -->
                            <div x-show="showingPreview && photoData && !webcamActive && !showingVideoPreview" class="aspect-video flex items-center justify-center bg-gray-900">
                                <img :src="getImageUrl(photoData)" class="max-w-full max-h-[60vh] object-contain" alt="Captured preview">
                            </div>

                            <!-- Video preview (after recording) -->
                            <div x-show="showingVideoPreview && recordedVideoUrl" class="aspect-video flex items-center justify-center bg-gray-900">
                                <video :src="recordedVideoUrl" class="max-w-full max-h-[60vh] object-contain" controls playsinline></video>
                            </div>

                            <!-- Error display -->
                            <div x-show="webcamError" class="aspect-video bg-gray-900 flex flex-col items-center justify-center text-center p-6">
                                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-12 w-12 text-danger-500 mb-4" />
                                <span class="text-white text-lg font-medium" x-text="webcamError"></span>

                                <x-filament::button color="primary" size="sm" class="mt-4" @click="webcamError = null; startCamera()">
                                    {{ __('Try Again') }}
                                </x-filament::button>
                            </div>

                            <!-- Capture button overlay (photo mode) -->
                            <div x-show="webcamActive && !webcamError && !showingPreview && !showingVideoPreview && captureMode === 'photo'" class="absolute bottom-4 left-0 right-0 flex justify-center">
                                <button type="button" @click="capturePhoto()" class="w-16 h-16 rounded-full bg-primary-600 hover:bg-primary-500 border-4 border-white flex items-center justify-center shadow-lg transition-colors" title="{{ __('Take Photo') }}">
                                    <x-filament::icon icon="heroicon-s-camera" class="h-8 w-8 text-white" />
                                </button>
                            </div>

                            <!-- Record button overlay (video mode) -->
                            <div x-show="webcamActive && !webcamError && !showingPreview && !showingVideoPreview && captureMode === 'video'" class="absolute bottom-4 left-0 right-0 flex justify-center">
                                <button
                                    type="button"
                                    x-on:click="isRecording ? stopVideoRecording() : startVideoRecording()"
                                    class="flex h-16 w-16 items-center justify-center rounded-full border-4 border-white shadow-lg transition-colors"
                                    :class="isRecording ? 'bg-danger-600 hover:bg-danger-500' : 'bg-primary-600 hover:bg-primary-500'"
                                    :title="isRecording ? '{{ __('Stop Recording') }}' : '{{ __('Start Recording') }}'"
                                >
                                    <span x-show="!isRecording" class="block h-8 w-8 rounded-full bg-white"></span>
                                    <span x-show="isRecording" class="block h-6 w-6 rounded-sm bg-white"></span>
                                </button>
                            </div>

                            <!-- Recording indicator -->
                            <div x-show="isRecording" class="absolute top-4 left-1/2 -translate-x-1/2">
                                <span class="flex items-center gap-2 rounded-full bg-danger-600/90 px-3 py-1 text-xs font-medium text-white">
                                    <span class="h-2 w-2 animate-pulse rounded-full bg-white"></span>
                                    {{ __('Merekam...') }}
                                </span>
                            </div>

                            <!-- Mirror toggle -->
                            <div x-show="webcamActive && !webcamError && !showingPreview" class="absolute top-4 right-4">
                                <button type="button" @click="toggleMirror()" class="w-10 h-10 rounded-full bg-gray-900/70 text-white flex items-center justify-center hover:bg-gray-900/90 transition-colors" :class="{ 'ring-2 ring-primary-500': mirroredView }" :title="mirroredView ? '{{ __('Disable mirror') }}' : '{{ __('Enable mirror') }}'">
                                    <x-filament::icon icon="heroicon-o-arrows-right-left" class="h-5 w-5" />
                                </button>
                            </div>

                            <!-- Flip camera -->
                            <div x-show="webcamActive && !webcamError && !showingPreview && !showingVideoPreview" class="absolute top-4 left-4">
                                <button type="button" @click="flipCamera()" class="w-10 h-10 rounded-full bg-gray-900/70 text-white flex items-center justify-center hover:bg-gray-900/90 transition-colors" title="{{ __('Switch Camera') }}">
                                    <x-filament::icon icon="heroicon-o-arrow-path" class="h-5 w-5" />
                                </button>
                            </div>

                            <!-- Camera type indicator (front/back) -->
                            <div x-show="webcamActive && !webcamError && !showingPreview && !showingVideoPreview" class="absolute bottom-4 left-4">
                                <span class="px-2 py-1 rounded-full bg-gray-900/70 text-white text-xs">
                                    <span x-text="captureMode === 'video' ? '{{ __('Video') }} — ' : '' + (currentFacingMode === 'environment' || isBackCamera ? '{{ __('Back Camera') }}' : '{{ __('Front Camera') }}')"></span>
                                </span>
                            </div>
                        </div>

                        <!-- Camera dropdown selector (desktop only) -->
                        <div x-show="{{ $getShowCameraSelector() ? 'true' : 'false' }} && availableCameras.length > 1 && !isMobile" class="mb-4">
                            <label class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">
                                {{ __('filament-take-picture-field::take-picture-field.select_camera') }}
                            </label>

                            <select x-model="selectedCameraId" @change="changeCamera($event.target.value)" class="
                                    block w-full rounded-lg border-none
                                    bg-white text-gray-950
                                    py-2 pe-10 ps-3 text-sm
                                    shadow-sm ring-1 ring-inset ring-gray-950/10
                                    focus:ring-2 focus:ring-inset focus:ring-primary-600/40
                                    dark:bg-white/5 dark:text-white dark:ring-white/10
                                    dark:focus:ring-primary-400/40">
                                <template x-for="(camera, index) in availableCameras" :key="camera.deviceId">
                                    <option :value="camera.deviceId" class="bg-white text-gray-950 dark:bg-gray-900 dark:text-white" x-text="`Camera ${index + 1} (${camera.label || 'Unnamed Camera'})`"></option>
                                </template>
                            </select>

                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-300">
                                {{ __('filament-take-picture-field::take-picture-field.select_camera_hint') }}
                            </p>
                        </div>

                        <!-- CBIR: mode picker inside modal (drag/swipe to slide, centered) -->
                        <div
                            class="mb-4 overflow-x-auto pb-1 select-none cursor-grab active:cursor-grabbing"
                            x-show="!showingPreview && !showingVideoPreview"
                            x-cloak
                            x-data="{
                                dragging: false,
                                moved: false,
                                startX: 0,
                                scrollLeft: 0,
                                startDrag(e) {
                                    this.dragging = true;
                                    this.moved = false;
                                    this.startX = e.pageX;
                                    this.scrollLeft = e.currentTarget.scrollLeft;
                                },
                                moveDrag(e) {
                                    if (! this.dragging) return;
                                    const dx = e.pageX - this.startX;
                                    if (Math.abs(dx) > 5) this.moved = true;
                                    e.currentTarget.scrollLeft = this.scrollLeft - dx;
                                },
                                endDrag() {
                                    this.dragging = false;
                                }
                            }"
                            x-on:mousedown="startDrag($event)"
                            x-on:mousemove="moveDrag($event)"
                            x-on:mouseup="endDrag()"
                            x-on:mouseleave="endDrag()"
                            x-on:click.capture="if (moved) { $event.preventDefault(); $event.stopPropagation(); }"
                        >
                            <div class="flex w-max max-w-full gap-2 px-1 py-0.5 mx-auto snap-x">
                                <button
                                    type="button"
                                    x-on:click="switchFacing('environment')"
                                    class="flex shrink-0 snap-start flex-col items-center gap-1 rounded-lg border px-3 py-2.5 text-center text-xs font-medium transition-colors"
                                    :class="captureMode === 'photo' && currentFacingMode === 'environment'
                                        ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400'
                                        : 'border-gray-200 text-gray-700 hover:border-primary-400 dark:border-white/10 dark:text-gray-200'"
                            >
                                <x-filament::icon icon="heroicon-o-camera" class="h-5 w-5" />
                                <span>{{ __('Belakang') }}</span>
                            </button>
                            <button
                                type="button"
                                x-on:click="switchFacing('user')"
                                class="flex shrink-0 snap-start flex-col items-center gap-1 rounded-lg border px-3 py-2.5 text-center text-xs font-medium transition-colors"
                                :class="captureMode === 'photo' && currentFacingMode === 'user'
                                    ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400'
                                    : 'border-gray-200 text-gray-700 hover:border-primary-400 dark:border-white/10 dark:text-gray-200'"
                            >
                                <x-filament::icon icon="heroicon-o-user-circle" class="h-5 w-5" />
                                <span>{{ __('Depan') }}</span>
                            </button>

                            <button
                                type="button"
                                x-on:click="switchCaptureMode('video')"
                                class="flex shrink-0 snap-start flex-col items-center gap-1 rounded-lg border px-3 py-2.5 text-center text-xs font-medium transition-colors"
                                :class="captureMode === 'video'
                                    ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400'
                                    : 'border-gray-200 text-gray-700 hover:border-primary-400 dark:border-white/10 dark:text-gray-200'"
                            >
                                <x-filament::icon icon="heroicon-o-video-camera" class="h-5 w-5" />
                                <span>{{ __('Video') }}</span>
                            </button>
                            <button
                                type="button"
                                x-on:click="switchCaptureMode('gallery')"
                                class="flex shrink-0 snap-start flex-col items-center gap-1 rounded-lg border px-3 py-2.5 text-center text-xs font-medium transition-colors"
                                :class="captureMode === 'gallery'
                                    ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400'
                                    : 'border-gray-200 text-gray-700 hover:border-primary-400 dark:border-white/10 dark:text-gray-200'"
                            >
                                <x-filament::icon icon="heroicon-o-photo" class="h-5 w-5" />
                                <span>{{ __('Galeri') }}</span>
                            </button>

                            @php
                                $slideSources = [
                                    ['event' => 'cbir-pick-gallery', 'icon' => 'heroicon-o-folder', 'label' => __('File')],
                                ];
                            @endphp
                            @foreach ($slideSources as $src)
                                <button
                                    type="button"
                                    x-on:click="closeModal(); window.dispatchEvent(new CustomEvent('{{ $src['event'] }}'))"
                                    class="flex shrink-0 snap-start flex-col items-center gap-1 rounded-lg border border-gray-200 px-3 py-2.5 text-center text-xs font-medium text-gray-700 transition-colors hover:border-primary-400 dark:border-white/10 dark:text-gray-200"
                                >
                                    <x-filament::icon :icon="$src['icon']" class="h-5 w-5" />
                                    <span>{{ $src['label'] }}</span>
                                </button>
                            @endforeach
                            </div>
                        </div>

                        <!-- Action buttons -->
                        <div class="flex justify-end gap-3">
                            <x-filament::button x-show="!showingPreview && !showingVideoPreview" color="gray" @click="closeModal()">
                                {{ __('filament-take-picture-field::take-picture-field.cancel') }}
                            </x-filament::button>

                            <x-filament::button x-show="!showingPreview && !showingVideoPreview && captureMode === 'photo' && webcamActive && !webcamError" color="primary" @click="capturePhoto()">
                                {{ __('filament-take-picture-field::take-picture-field.capture') }}
                            </x-filament::button>

                            <x-filament::button x-show="showingPreview" color="gray" @click="retakeInModal()">
                                {{ __('filament-take-picture-field::take-picture-field.retake') }}
                            </x-filament::button>

                            <x-filament::button x-show="showingPreview" color="primary" @click="confirmPhoto()">
                                {{ __('filament-take-picture-field::take-picture-field.use_photo') }}
                            </x-filament::button>

                            <x-filament::button x-show="showingVideoPreview" color="gray" @click="retakeVideo()">
                                {{ __('Ulangi') }}
                            </x-filament::button>

                            <x-filament::button x-show="showingVideoPreview" color="primary" @click="confirmVideo()">
                                {{ __('Gunakan Video') }}
                            </x-filament::button>
                        </div>

                    </div>

                </div>
            </div>
        </template>

    </div>
</x-dynamic-component>