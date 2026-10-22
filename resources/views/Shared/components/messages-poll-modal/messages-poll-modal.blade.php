{{--
    MODAL BUAT POLLING untuk halaman Messages.

    Dipakai tiga panel sekaligus (Admin, User, Welcome) lewat satu file.
    Dibuka lewat event window `messages-poll-open` dari item menu lampiran.

    Datanya diserahkan ke komponen percakapan lewat event window
    `messages-create-poll`; jembatan di dalam komponen (lihat
    resources/views/*/livewire/messages/messages/messages.blade.php) yang
    memanggil $wire.createPoll().

    PENTING -- jangan pakai tanda kutip GANDA di dalam atribut x-data.
    Atributnya diapit kutip ganda, jadi satu kutip ganda saja sudah
    memotong atribut dan seluruh scope Alpine-nya mati (SyntaxError).
--}}
<div
    x-data="{
        pollOpen: false,
        pollQuestion: '',
        pollOptions: ['', ''],
        pollError: null,
        pollOptionLabel: @js(__('Pilihan')),
        pollRemoveLabel: @js(__('Hapus pilihan')),

        pollOpenModal() {
            this.pollQuestion = '';
            this.pollOptions = ['', ''];
            this.pollError = null;
            this.pollOpen = true;
            document.body.classList.add('overflow-hidden');
        },

        pollClose() {
            this.pollOpen = false;
            document.body.classList.remove('overflow-hidden');
        },

        pollAddOption() {
            if (this.pollOptions.length >= 10) return;
            this.pollOptions.push('');
        },

        pollRemoveOption(index) {
            if (this.pollOptions.length <= 2) return;
            this.pollOptions.splice(index, 1);
        },

        pollFilledOptions() {
            return this.pollOptions.filter((option) => option.trim() !== '');
        },

        pollCanSend() {
            return this.pollQuestion.trim() !== '' && this.pollFilledOptions().length >= 2;
        },

        pollSend() {
            if (!this.pollCanSend()) {
                this.pollError = @js(__('Isi pertanyaannya dan pilih minimal dua pilihan.'));
                return;
            }

            window.dispatchEvent(new CustomEvent('messages-create-poll', {
                detail: {
                    question: this.pollQuestion.trim(),
                    options: this.pollFilledOptions().map((option) => option.trim()),
                },
            }));

            this.pollClose();
        },
    }"
    x-on:messages-poll-open.window="pollOpenModal()"
    x-on:keydown.escape.window="pollOpen && pollClose()"
    x-on:livewire:navigating.window="pollClose()"
    class="contents"
>
    <template x-teleport="body">
        <div
            x-cloak
            x-show="pollOpen"
            x-on:click.self="pollClose()"
            class="fixed inset-0 z-[9998] flex items-center justify-center overflow-y-auto bg-gray-950/60 p-2 sm:p-4"
            role="dialog"
            aria-modal="true"
            aria-label="{{ __('Poll') }}"
        >
            <div
                x-on:click.stop
                class="fi-glass-modal w-full max-w-md rounded-xl bg-white shadow-xl ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
            >
                <div class="flex items-center justify-between gap-2 border-b border-gray-200 px-3 py-2.5 dark:border-white/10 sm:px-6 sm:py-4">
                    <h3 class="truncate text-base font-semibold text-gray-950 dark:text-white">{{ __('Buat Poll') }}</h3>

                    <button type="button" x-on:click="pollClose()" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300" aria-label="{{ __('Tutup') }}">
                        <x-filament::icon icon="/images/icons/x.svg" class="h-5 w-5" />
                    </button>
                </div>

                <div class="flex flex-col gap-3 p-4 sm:p-6">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                        {{ __('Pertanyaan') }} <span class="text-danger-500">*</span>
                        <input
                            type="text"
                            x-model="pollQuestion"
                            class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                            placeholder="{{ __('Contoh: Paket dekorasi apa yang paling Anda sukai?') }}"
                        >
                    </label>

                    <div>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Pilihan') }} <span class="text-danger-500">*</span></span>

                        <div class="mt-1 flex flex-col gap-2">
                            <template x-for="(option, index) in pollOptions" :key="index">
                                <div class="flex items-center gap-2">
                                    <input
                                        type="text"
                                        x-model="pollOptions[index]"
                                        class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                                        x-bind:placeholder="pollOptionLabel + ' ' + (index + 1)"
                                    >
                                    <button
                                        type="button"
                                        x-on:click="pollRemoveOption(index)"
                                        x-show="pollOptions.length > 2"
                                        class="shrink-0 rounded-lg p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-danger-500 dark:hover:bg-white/10"
                                        x-bind:title="pollRemoveLabel"
                                    >
                                        <x-filament::icon icon="/images/icons/x.svg" class="h-4 w-4" />
                                    </button>
                                </div>
                            </template>
                        </div>

                        <button
                            type="button"
                            x-on:click="pollAddOption()"
                            x-show="pollOptions.length < 10"
                            class="mt-2 inline-flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-medium text-primary-600 transition-colors hover:bg-danger-50 dark:text-primary-400 dark:hover:bg-white/5"
                        >
                            <x-filament::icon icon="/images/icons/plus.svg" class="h-4 w-4" />
                            {{ __('Tambah pilihan') }}
                        </button>
                    </div>

                    <div x-show="pollError" class="rounded-lg bg-danger-50 px-3 py-2 text-sm text-danger-700 dark:bg-danger-500/10 dark:text-danger-300" x-text="pollError"></div>

                    <div class="flex justify-end gap-3 pt-1">
                        <x-filament::button color="gray" x-on:click="pollClose()">
                            {{ __('Batal') }}
                        </x-filament::button>

                        <x-filament::button color="primary" x-on:click="pollSend()" x-bind:disabled="!pollCanSend()">
                            {{ __('Kirim Poll') }}
                        </x-filament::button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
