{{--
    MESSAGES CAMERA MODAL — satu-satunya modal kamera untuk halaman Messages.

    Dipakai oleh tiga panel sekaligus (Admin, User, Welcome). Include-nya ada di
    file HALAMAN (resources/views/{Admin,User,Welcome}/pages/messages), bukan di
    komponen Livewire percakapan: komponen itu polling tiap beberapa detik dan
    setiap render ulang menduplikasi isi <template x-teleport>, sehingga di body
    menumpuk beberapa dialog kamera sekaligus.

    Bentuk dan perilakunya SENGAJA disamakan dengan viewfinder global search
    (topbar) — `filament-take-picture-field`:

      • modal max-w-xl, judul "Kamera" / "Video"
      • pratinjau penuh: balik kamera di kiri atas, cermin di kanan atas,
        label kamera aktif di kiri bawah, tombol bulat besar di tengah bawah
      • mode FOTO : tombol jepret  → pratinjau → Retake / Use Photo
      • mode VIDEO: tombol rekam   → pratinjau → Retake / Use Video

    PENTING 1 — nama state.
    Semua state di sini berawalan `cam` (camOpen, camMode, ...). Dulu namanya
    `open`, dan itu BENTROK dengan `open` milik menu lampiran di komposer:
    Alpine menggabungkan seluruh scope leluhur, jadi `x-show="open"` membaca
    `open=false` milik menu dan modal tidak pernah muncul sama sekali.

    PENTING 2 — ukuran pratinjau.
    Wadah `.x-cam-stage` hanya boleh punya max-height, TIDAK boleh aspect-ratio.
    aspect-ratio + max-height pada elemen block membuat lebarnya dihitung ulang
    dari tinggi, sehingga pratinjau menyusut jadi kolom tipis. Lihat
    public/css/panel-glass.css.
--}}
<div
    x-data="{
        camOpen: false,
        camMode: 'photo',
        camPhase: 'live',
        camFacing: 'environment',
        camIsBack: false,
        camStream: null,
        camRecorder: null,
        camChunks: [],
        camRecording: false,
        camMirrored: false,
        camPreviewUrl: null,
        camBlob: null,
        camError: null,

        camIsMobile() {
            return /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
        },

        camOpenWith(detail) {
            this.camMode = detail && detail.mode === 'video' ? 'video' : 'photo';
            this.camReleasePreview();
            this.camError = null;
            this.camRecording = false;

            /*
             * Di HP (web maupun WebView aplikasi) kamera native yang dibuka,
             * sama seperti global search: tidak ada gunanya menampilkan
             * viewfinder WebRTC di layar yang seluruhnya kamera.
             */
            if (this.camIsMobile()) {
                const inputId = this.camMode === 'video' ? 'msg-native-video' : 'msg-native-photo';
                setTimeout(() => {
                    const el = document.getElementById(inputId);
                    if (el) el.click();
                }, 120);
                return;
            }

            this.camPhase = 'live';
            this.camOpen = true;
            document.body.classList.add('overflow-hidden');
            this.$nextTick(() => this.camStartStream());
        },

        camSetMode(mode) {
            if (this.camMode === mode) return;

            this.camStopRecorder();
            this.camReleasePreview();
            this.camMode = mode;
            this.camPhase = 'live';

            if (this.camOpen) {
                this.$nextTick(() => this.camStartStream());
            }
        },

        camClose() {
            this.camStopRecorder();
            this.camStopStream();
            this.camReleasePreview();
            this.camRecording = false;
            this.camOpen = false;
            this.camPhase = 'live';
            document.body.classList.remove('overflow-hidden');
        },

        camReleasePreview() {
            if (this.camPreviewUrl) {
                URL.revokeObjectURL(this.camPreviewUrl);
                this.camPreviewUrl = null;
            }
            this.camBlob = null;
        },

        async camStartStream() {
            const video = this.$refs.camVideo;
            if (!video) return;

            this.camStopStream();

            try {
                this.camStream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: this.camFacing, width: { ideal: 1280 }, height: { ideal: 720 } },
                    audio: this.camMode === 'video',
                });
                video.srcObject = this.camStream;
                video.muted = true;
                await video.play();

                const track = this.camStream.getVideoTracks()[0];
                const label = (track && track.label ? track.label : '').toLowerCase();

                /*
                 * TENTUKAN KAMERA YANG BENAR-BENAR AKTIF.
                 *
                 * Aturan prioritasnya sama dengan viewfinder global search:
                 * 1. facingMode SESUNGGUHNYA dari track (getSettings) -- ini yang
                 *    dipakai browser, bukan yang kita minta.
                 * 2. Kalau tidak ada (sebagian desktop tidak melaporkannya),
                 *    pakai label perangkat.
                 * 3. Kalau label juga tidak membantu dan perangkat cuma punya
                 *    SATU kamera, itu hampir selalu webcam depan (laptop).
                 *
                 * Kenapa harus begini: `facingMode` yang DIMINTA ('environment')
                 * tidak selalu yang dipakai. Di laptop tidak ada kamera
                 * belakang, jadi permintaan itu jatuh ke webcam depan -- tapi
                 * logika lama hanya mengecek facingMode yang diminta, sehingga
                 * pratinjau webcam depan TIDAK dicerminkan, padahal di
                 * global search iya.
                 */
                let facing = this.camFacing;
                let hasFacingMode = false;

                if (track && typeof track.getSettings === 'function') {
                    const settings = track.getSettings();

                    if (settings.facingMode) {
                        facing = settings.facingMode;
                        hasFacingMode = true;
                    }
                }

                if (!hasFacingMode) {
                    const frontPatterns = ['front', 'user', 'facetime', 'depan', 'selfie', 'integrated', 'true depth'];
                    const backPatterns = ['back', 'rear', 'environment', 'belakang', 'world', 'wide', 'tricamera'];

                    if (frontPatterns.some((pattern) => label.includes(pattern))) {
                        facing = 'user';
                    } else if (backPatterns.some((pattern) => label.includes(pattern))) {
                        facing = 'environment';
                    } else if (facing === 'environment') {
                        let cameras = [];

                        try {
                            cameras = (await navigator.mediaDevices.enumerateDevices())
                                .filter((device) => device.kind === 'videoinput');
                        } catch (e) {
                            cameras = [];
                        }

                        if (cameras.length === 1) {
                            facing = 'user';
                        }
                    }
                }

                this.camFacing = facing;
                this.camIsBack = facing === 'environment';

                /*
                 * KAMERA DEPAN SELALU DICERMINKAN, seperti WhatsApp.
                 *
                 * Flag yang sama dengan chip 'Front Camera'/'Back Camera' di
                 * bawah -- jadi tidak mungkin labelnya menulis 'Front Camera'
                 * sementara pratinjunya tidak dicerminkan.
                 */
                this.camMirrored = !this.camIsBack;
                this.camError = null;
            } catch (e) {
                this.camError = @js(__('Kamera tidak dapat diakses. Pastikan izin kamera diberikan.'));
            }
        },

        camStopStream() {
            if (this.$refs.camVideo) {
                this.$refs.camVideo.srcObject = null;
            }
            if (this.camStream) {
                this.camStream.getTracks().forEach(track => track.stop());
                this.camStream = null;
            }
        },

        camFlip() {
            this.camFacing = this.camFacing === 'user' ? 'environment' : 'user';
            this.$nextTick(() => this.camStartStream());
        },

        camToggleMirror() {
            this.camMirrored = !this.camMirrored;
        },

        camCapturePhoto() {
            const video = this.$refs.camVideo;
            if (!video) return;

            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth || 1280;
            canvas.height = video.videoHeight || 720;
            const context = canvas.getContext('2d');

            // Hasil jepret harus sama dengan yang terlihat di layar: kalau
            // pratinjau dicerminkan, frame yang disimpan juga dicerminkan.
            if (this.camMirrored) {
                context.translate(canvas.width, 0);
                context.scale(-1, 1);
            }

            context.drawImage(video, 0, 0, canvas.width, canvas.height);

            canvas.toBlob((blob) => {
                if (!blob) return;
                this.camBlob = blob;
                this.camPreviewUrl = URL.createObjectURL(blob);
                this.camPhase = 'preview';
                this.camStopStream();
            }, 'image/jpeg', 0.92);
        },

        camStartRecording() {
            if (!this.camStream || this.camRecording) return;

            this.camChunks = [];
            const mimeType = MediaRecorder.isTypeSupported('video/webm;codecs=vp9')
                ? 'video/webm;codecs=vp9'
                : (MediaRecorder.isTypeSupported('video/webm') ? 'video/webm' : 'video/mp4');

            this.camRecorder = new MediaRecorder(this.camStream, { mimeType });
            this.camRecorder.ondataavailable = (e) => {
                if (e.data && e.data.size > 0) this.camChunks.push(e.data);
            };
            this.camRecorder.onstop = () => {
                if (!this.camChunks.length) return;
                const blob = new Blob(this.camChunks, { type: mimeType });
                this.camBlob = blob;
                this.camPreviewUrl = URL.createObjectURL(blob);
                this.camPhase = 'preview';
                this.camStopStream();
            };

            this.camRecorder.start(250);
            this.camRecording = true;
        },

        camStopRecording() {
            if (this.camRecorder && this.camRecording) {
                this.camRecorder.stop();
                this.camRecording = false;
            }
        },

        camStopRecorder() {
            if (this.camRecorder && this.camRecording) {
                try { this.camRecorder.stop(); } catch (e) {}
                this.camRecording = false;
            }
            this.camRecorder = null;
            this.camChunks = [];
        },

        camRetake() {
            this.camReleasePreview();
            this.camPhase = 'live';
            this.$nextTick(() => this.camStartStream());
        },

        camUse() {
            if (!this.camBlob) return;

            const isVideo = this.camMode === 'video';
            const mime = this.camBlob.type || (isVideo ? 'video/webm' : 'image/jpeg');
            const extension = isVideo ? (mime.includes('mp4') ? 'mp4' : 'webm') : 'jpg';
            const file = new File([this.camBlob], 'kamera-' + Date.now() + '.' + extension, { type: mime });

            if (!this.camInjectFile(file)) {
                this.camError = @js(__('Lampiran tidak dapat disiapkan. Coba lagi.'));
                return;
            }

            this.camClose();

            // Jembatan di dalam komponen percakapan yang mengunggah lalu
            // mengirim pesanannya, jadi gelembung pesannya langsung muncul.
        },

        /*
         * Lampirkan ke FilePond milik field `attachments` di composer.
         *
         * Delegasi ke helper bersama (Shared/components/messages-attach-inject)
         * supaya kamera, audio, dan kontak memakai jalur yang sama.
         */
        camInjectFile(file) {
            if (!file) return false;

            if (window.messagesAttachFile) {
                return window.messagesAttachFile(file);
            }

            return false;
        },

        camOnPicked(event) {
            const file = event.target.files && event.target.files[0];
            if (file) this.camInjectFile(file);
            event.target.value = '';
        },
    }"
    x-on:messages-camera-open.window="camOpenWith($event.detail)"
    x-on:keydown.escape.window="camOpen && camClose()"
    x-on:livewire:navigating.window="camClose()"
    class="contents"
>
    {{-- Input native: satu-satunya jalur di HP, sama seperti global search. --}}
    <input id="msg-native-photo" type="file" accept="image/*" capture="environment" class="sr-only" x-on:change="camOnPicked($event)">
    <input id="msg-native-video" type="file" accept="video/*" capture="environment" class="sr-only" x-on:change="camOnPicked($event)">

    {{--
        Lampiran FilePond di komposer tetap hidup (diisi lewat camInjectFile()),
        tetapi UI FilePond bukan bagian dari desain komposer: tombol "+" yang
        membuka menu lampiran. Field-nya dirender oleh Forms\FileUpload di
        ketiga komponen Messages dengan class `messages-attachment-upload`.
    --}}
    <style>
        .messages-attachment-upload {
            position: absolute !important;
            width: 1px !important;
            height: 1px !important;
            overflow: hidden !important;
            opacity: 0 !important;
            pointer-events: none !important;
        }
    </style>

    <template x-teleport="body">
        <div
            x-cloak
            x-show="camOpen"
            x-on:click.self="camClose()"
            class="fixed inset-0 z-[9998] flex items-center justify-center overflow-y-auto bg-gray-950/60 p-2 sm:p-4"
            role="dialog"
            aria-modal="true"
            aria-label="{{ __('Camera Photo') }}"
        >
            <div
                x-on:click.stop
                class="fi-glass-modal w-full max-w-xl rounded-xl bg-white shadow-xl ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
            >
                {{-- Header --}}
                <div class="flex items-center justify-between gap-2 border-b border-gray-200 px-3 py-2.5 dark:border-white/10 sm:px-6 sm:py-4">
                    <h3
                        class="truncate text-base font-semibold text-gray-950 dark:text-white"
                        x-text="camMode === 'video' ? @js(__('Camera Video')) : @js(__('Camera Photo'))"
                    >{{ __('Camera Photo') }}</h3>

                    <div class="flex items-center gap-2">
                        {{-- Pilih mode. Tanpa ini, "modal video" hanya bisa
                             dibuka dari menu "+", tidak dari dalam modal. --}}
                        <div class="flex items-center gap-0.5 rounded-lg bg-gray-100 p-0.5 dark:bg-white/5">
                            <button
                                type="button"
                                x-on:click="camSetMode('photo')"
                                class="flex items-center gap-1.5 rounded-md px-2 py-1.5 text-xs font-medium transition"
                                :class="camMode === 'photo' ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-800 dark:text-white' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                                title="{{ __('Camera Photo') }}"
                            >
                                <x-filament::icon icon="/images/icons/camera.svg" class="h-4 w-4" />
                                <span class="hidden sm:inline">{{ __('Camera Photo') }}</span>
                            </button>

                            <button
                                type="button"
                                x-on:click="camSetMode('video')"
                                class="flex items-center gap-1.5 rounded-md px-2 py-1.5 text-xs font-medium transition"
                                :class="camMode === 'video' ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-800 dark:text-white' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                                title="{{ __('Camera Video') }}"
                            >
                                <x-filament::icon icon="/images/icons/video.svg" class="h-4 w-4" />
                                <span class="hidden sm:inline">{{ __('Camera Video') }}</span>
                            </button>
                        </div>

                        <button type="button" x-on:click="camClose()" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300" aria-label="{{ __('Tutup') }}">
                            <x-filament::icon icon="/images/icons/x.svg" class="h-5 w-5" />
                        </button>
                    </div>
                </div>

                {{-- Body --}}
                <div class="p-3 sm:p-6">
                    <div class="relative rounded-lg overflow-hidden bg-gray-950 mb-2 sm:mb-4">

                        {{-- Pratinjau langsung --}}
                        <div x-show="camPhase === 'live' && !camError" class="x-cam-stage flex items-center justify-center" style="max-height:55vh;">
                            <video
                                x-ref="camVideo"
                                autoplay
                                playsinline
                                muted
                                :style="camMirrored ? 'transform: scaleX(-1);' : ''"
                                class="x-cam-video block w-full max-w-full object-contain"
                                style="max-height:55vh;"
                            ></video>
                        </div>

                        {{-- Pratinjau foto --}}
                        <div x-show="camPhase === 'preview' && camMode === 'photo' && camPreviewUrl" class="x-cam-stage flex items-center justify-center bg-gray-900" style="max-height:55vh;">
                            <img :src="camPreviewUrl" class="block w-full max-w-full object-contain" style="max-height:55vh;" alt="{{ __('Pratinjau foto') }}">
                        </div>

                        {{-- Pratinjau video --}}
                        <div x-show="camPhase === 'preview' && camMode === 'video' && camPreviewUrl" class="x-cam-stage flex items-center justify-center bg-gray-900" style="max-height:55vh;">
                            <video :src="camPreviewUrl" class="block w-full max-w-full object-contain" style="max-height:55vh;" controls playsinline></video>
                        </div>

                        {{-- Kamera gagal --}}
                        <div x-show="camError" class="x-cam-stage bg-gray-900 flex flex-col items-center justify-center text-center p-4" style="max-height:38vh;">
                            <x-filament::icon icon="/images/icons/warning.svg" class="h-12 w-12 text-danger-500 mb-4" />
                            <span class="text-white text-sm sm:text-base font-medium" x-text="camError"></span>
                            <x-filament::button color="primary" size="sm" class="mt-4" x-on:click="camStartStream()">
                                {{ __('Coba Lagi') }}
                            </x-filament::button>
                        </div>

                        {{-- Jepret (foto) --}}
                        <div x-show="camPhase === 'live' && !camError && camMode === 'photo'" class="absolute bottom-4 left-0 right-0 flex justify-center">
                            <button
                                type="button"
                                x-on:click="camCapturePhoto()"
                                class="w-14 h-14 sm:w-16 sm:h-16 rounded-full bg-primary-600 hover:bg-danger-500 border-4 border-white flex items-center justify-center shadow-lg transition-colors"
                                title="{{ __('Ambil Foto') }}"
                            >
                                <x-filament::icon icon="/images/icons/camera.svg" class="h-7 w-7 sm:h-8 sm:w-8 text-white" />
                            </button>
                        </div>

                        {{-- Rekam (video) --}}
                        <div x-show="camPhase === 'live' && !camError && camMode === 'video'" class="absolute bottom-4 left-0 right-0 flex justify-center">
                            <button
                                type="button"
                                x-on:click="camRecording ? camStopRecording() : camStartRecording()"
                                class="flex h-14 w-14 sm:h-16 sm:w-16 items-center justify-center rounded-full border-4 border-white shadow-lg transition-colors"
                                :class="camRecording ? 'bg-danger-600 hover:bg-danger-500' : 'bg-primary-600 hover:bg-danger-500'"
                                :title="camRecording ? @js(__('Stop Rekam')) : @js(__('Mulai Rekam'))"
                            >
                                <span x-show="!camRecording" class="block h-8 w-8 rounded-full bg-white"></span>
                                <span x-show="camRecording" class="block h-6 w-6 rounded-sm bg-white"></span>
                            </button>
                        </div>

                        {{-- Indikator rekam --}}
                        <div x-show="camRecording" class="absolute top-4 left-1/2 -translate-x-1/2">
                            <span class="flex items-center gap-2 rounded-full bg-danger-600/90 px-3 py-1 text-xs font-medium text-white">
                                <span class="h-2 w-2 animate-pulse rounded-full bg-white"></span>
                                {{ __('Merekam...') }}
                            </span>
                        </div>

                        {{-- Cermin --}}
                        <div x-show="camPhase === 'live' && !camError" class="absolute top-4 right-4">
                            <button
                                type="button"
                                x-on:click="camToggleMirror()"
                                class="w-10 h-10 rounded-full bg-gray-900/70 text-white flex items-center justify-center hover:bg-gray-900/90 transition-colors"
                                :class="{ 'ring-2 ring-primary-500': camMirrored }"
                                :title="camMirrored ? @js(__('Disable mirror')) : @js(__('Enable mirror'))"
                            >
                                <x-filament::icon icon="/images/icons/arrows_lr.svg" class="h-5 w-5" />
                            </button>
                        </div>

                        {{-- Balik kamera --}}
                        <div x-show="camPhase === 'live' && !camError" class="absolute top-4 left-4">
                            <button
                                type="button"
                                x-on:click="camFlip()"
                                class="w-10 h-10 rounded-full bg-gray-900/70 text-white flex items-center justify-center hover:bg-gray-900/90 transition-colors"
                                title="{{ __('Switch Camera') }}"
                            >
                                <x-filament::icon icon="/images/icons/refresh.svg" class="h-5 w-5" />
                            </button>
                        </div>

                        {{-- Label kamera aktif --}}
                        <div x-show="camPhase === 'live' && !camError" class="absolute bottom-4 left-4">
                            <span class="px-2 py-1 rounded-full bg-gray-900/70 text-white text-xs">
                                <span x-text="(camMode === 'video' ? @js(__('Video')) + ' ' : '') + (camIsBack ? @js(__('Back Camera')) : @js(__('Front Camera')))"></span>
                            </span>
                        </div>
                    </div>

                    {{-- Footer: hanya di keadaan pratinjau --}}
                    <div x-show="camPhase === 'preview'" class="flex justify-end gap-3">
                        <x-filament::button color="gray" x-on:click="camRetake()">
                            {{ __('Retake') }}
                        </x-filament::button>

                        <x-filament::button color="primary" x-on:click="camUse()">
                            <span x-show="camMode === 'photo'">{{ __('Use Photo') }}</span>
                            <span x-show="camMode === 'video'">{{ __('Gunakan Video') }}</span>
                        </x-filament::button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
