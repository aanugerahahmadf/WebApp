{{--
    MODAL REKAM AUDIO (voice note) untuk halaman Messages.

    Dipakai oleh tiga panel sekaligus (Admin, User, Welcome) lewat satu file.
    Dibuka lewat event window `messages-audio-open` dari item menu lampiran.

    Hasil rekaman (blob) diserahkan ke `window.messagesAttachFile()` supaya
    ikut alur lampiran yang sama dengan kamera -- lalu FilePond yang menampilkan
    pratinjau dan sendMessage() yang menyimpannya.

    PENTING 1 -- nama state berawalan `audio`.
    Semua state di sini berawalan `audio` supaya tidak bentrok dengan state
    `open` milik menu lampiran atau komponen lain di halaman yang sama. Alpine
    menggabungkan seluruh scope leluhur, jadi nama generik seperti `open` akan
    menimpa nilai milik komponen ini.

    PENTING 2 -- `navigator.mediaDevices` bisa tidak ada di konteks non-HTTPS.
    Kalau tidak ada, jangan melempar error yang tidak jelas: tampilkan pesan
    agar pengguna tahu penyebabnya.
--}}
<div
    x-data="{
        audioOpen: false,
        audioRecording: false,
        audioStream: null,
        audioRecorder: null,
        audioChunks: [],
        audioElapsed: 0,
        audioTimer: null,
        audioPreviewUrl: null,
        audioBlob: null,
        audioError: null,
        audioDurationLabel: '00:00',

        audioSupported() {
            return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
        },

        audioOpenRecorder() {
            if (!this.audioSupported()) {
                this.audioError = @js(__('Mikrofon tidak tersedia di peramban ini.'));
                this.audioOpen = true;
                return;
            }

            this.audioError = null;
            this.audioRelease();
            this.audioOpen = true;
            document.body.classList.add('overflow-hidden');
        },

        audioClose() {
            this.audioStopRecording();
            this.audioRelease();
            this.audioOpen = false;
            document.body.classList.remove('overflow-hidden');
        },

        audioRelease() {
            if (this.audioPreviewUrl) {
                URL.revokeObjectURL(this.audioPreviewUrl);
                this.audioPreviewUrl = null;
            }
            this.audioBlob = null;
        },

        audioFormat(seconds) {
            const m = Math.floor(seconds / 60).toString().padStart(2, '0');
            const s = Math.floor(seconds % 60).toString().padStart(2, '0');
            return m + ':' + s;
        },

        audioStart() {
            if (!this.audioSupported() || this.audioRecording) return;

            this.audioError = null;
            this.audioChunks = [];
            this.audioElapsed = 0;

            navigator.mediaDevices.getUserMedia({ audio: true })
                .then((stream) => {
                    this.audioStream = stream;

                    const mimeType = [
                        'audio/webm;codecs=opus',
                        'audio/webm',
                        'audio/ogg;codecs=opus',
                        'audio/mp4',
                        'audio/mpeg',
                        'audio/wav',
                    ].find((type) => MediaRecorder.isTypeSupported(type)) || '';

                    this.audioRecorder = new MediaRecorder(stream, mimeType ? { mimeType } : undefined);
                    this.audioChunks = [];

                    this.audioRecorder.ondataavailable = (event) => {
                        if (event.data && event.data.size > 0) this.audioChunks.push(event.data);
                    };

                    this.audioRecorder.onstop = () => {
                        const type = this.audioRecorder.mimeType || mimeType || 'audio/webm';
                        const blob = new Blob(this.audioChunks, { type });
                        this.audioBlob = blob;
                        this.audioPreviewUrl = URL.createObjectURL(blob);
                        this.audioDurationLabel = this.audioFormat(this.audioElapsed);
                        this.audioRecording = false;
                        this.audioStopStream();
                    };

                    this.audioRecorder.start(250);
                    this.audioRecording = true;

                    this.audioTimer = setInterval(() => { this.audioElapsed += 1; }, 1000);
                })
                .catch(() => {
                    this.audioError = @js(__('Mikrofon tidak dapat diakses. Pastikan izin mikrofon diberikan.'));
                });
        },

        audioStopRecording() {
            if (this.audioRecorder && this.audioRecording) {
                this.audioRecorder.stop();
                this.audioRecording = false;
            }

            if (this.audioTimer) {
                clearInterval(this.audioTimer);
                this.audioTimer = null;
            }
        },

        audioStopStream() {
            if (this.audioStream) {
                this.audioStream.getTracks().forEach((track) => track.stop());
                this.audioStream = null;
            }
        },

        audioRetake() {
            this.audioStopRecording();
            this.audioRelease();
            this.audioOpenRecorder();
        },

        audioExtension() {
            const type = (this.audioBlob && this.audioBlob.type) || '';

            if (type.includes('ogg')) return 'ogg';
            if (type.includes('mp4')) return 'm4a';
            if (type.includes('mpeg')) return 'mp3';
            if (type.includes('wav')) return 'wav';
            return 'webm';
        },

        audioSend() {
            if (!this.audioBlob) return;

            const file = new File(
                [this.audioBlob],
                'voice-' + Date.now() + '.' + this.audioExtension(),
                { type: this.audioBlob.type || 'audio/webm' },
            );

            if (window.messagesAttachFile && window.messagesAttachFile(file)) {
                this.audioClose();

                // Jembatan di dalam komponen percakapan yang mengunggah lalu
                // mengirim pesanannya, jadi gelembung pesannya langsung muncul.
            } else {
                this.audioError = @js(__('Lampiran tidak dapat disiapkan. Coba lagi.'));
            }
        },
    }"
    x-on:messages-audio-open.window="audioOpenRecorder()"
    x-on:keydown.escape.window="audioOpen && audioClose()"
    x-on:livewire:navigating.window="audioClose()"
    class="contents"
>
    <template x-teleport="body">
        <div
            x-cloak
            x-show="audioOpen"
            x-on:click.self="audioClose()"
            class="fixed inset-0 z-[9998] flex items-center justify-center overflow-y-auto bg-gray-950/60 p-2 sm:p-4"
            role="dialog"
            aria-modal="true"
            aria-label="{{ __('Audio') }}"
        >
            <div
                x-on:click.stop
                class="fi-glass-modal w-full max-w-md rounded-xl bg-white shadow-xl ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
            >
                <div class="flex items-center justify-between gap-2 border-b border-gray-200 px-3 py-2.5 dark:border-white/10 sm:px-6 sm:py-4">
                    <h3 class="truncate text-base font-semibold text-gray-950 dark:text-white">{{ __('Audio') }}</h3>

                    <button type="button" x-on:click="audioClose()" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300" aria-label="{{ __('Tutup') }}">
                        <x-filament::icon icon="/images/icons/x.svg" class="h-5 w-5" />
                    </button>
                </div>

                <div class="p-4 sm:p-6">
                    {{-- Keadaan siap: tombol mikrofon besar. --}}
                    <div x-show="!audioRecording && !audioPreviewUrl && !audioError" class="flex flex-col items-center py-6 text-center">
                        <p class="mb-5 text-sm text-gray-500 dark:text-gray-400">{{ __('Tekan mikrofon untuk mulai merekam suara.') }}</p>

                        <button
                            type="button"
                            x-on:click="audioStart()"
                            class="flex h-20 w-20 items-center justify-center rounded-full bg-primary-600 text-white shadow-lg transition-colors hover:bg-danger-500"
                            title="{{ __('Mulai Rekam') }}"
                        >
                            <x-filament::icon icon="/images/icons/microphone.svg" class="h-9 w-9" />
                        </button>
                    </div>

                    {{-- Keadaan merekam. --}}
                    <div x-show="audioRecording" class="flex flex-col items-center py-6 text-center">
                        <span class="mb-4 inline-flex items-center gap-2 rounded-full bg-danger-600/90 px-3 py-1 text-xs font-medium text-white">
                            <span class="h-2 w-2 animate-pulse rounded-full bg-white"></span>
                            {{ __('Merekam...') }}
                        </span>

                        <p class="mb-5 text-3xl font-semibold tabular-nums text-gray-950 dark:text-white" x-text="audioFormat(audioElapsed)">00:00</p>

                        <button
                            type="button"
                            x-on:click="audioStopRecording()"
                            class="flex h-20 w-20 items-center justify-center rounded-full bg-danger-600 text-white shadow-lg transition-colors hover:bg-danger-500"
                            :title="audioRecording ? @js(__('Stop Rekam')) : @js(__('Mulai Rekam'))"
                        >
                            <span class="block h-7 w-7 rounded-sm bg-white"></span>
                        </button>
                    </div>

                    {{-- Keadaan pratinjau. --}}
                    <div x-show="audioPreviewUrl" class="flex flex-col gap-4">
                        <audio :src="audioPreviewUrl" controls playsinline class="w-full"></audio>

                        <p class="text-center text-sm text-gray-500 dark:text-gray-400">
                            {{ __('Durasi') }}: <span class="font-medium text-gray-950 dark:text-white" x-text="audioDurationLabel">00:00</span>
                        </p>

                        <div class="flex justify-end gap-3">
                            <x-filament::button color="gray" x-on:click="audioRetake()">
                                {{ __('Retake') }}
                            </x-filament::button>

                            <x-filament::button color="primary" x-on:click="audioSend()">
                                {{ __('Kirim Audio') }}
                            </x-filament::button>
                        </div>
                    </div>

                    {{-- Keadaan gagal. --}}
                    <div x-show="audioError" class="flex flex-col items-center py-6 text-center">
                        <x-filament::icon icon="/images/icons/warning.svg" class="mb-4 h-12 w-12 text-danger-500" />
                        <span class="text-sm font-medium text-gray-950 dark:text-white" x-text="audioError"></span>

                        <x-filament::button color="primary" size="sm" class="mt-4" x-on:click="audioError = null; audioOpenRecorder()">
                            {{ __('Coba Lagi') }}
                        </x-filament::button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
