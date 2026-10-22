{{--
    MODAL STIKER untuk halaman Messages.

    Dipakai tiga panel sekaligus (Admin, User, Welcome) lewat satu file.
    Dibuka lewat event window `messages-sticker-open` dari item menu lampiran.

    Stiker dikirim sebagai karakter emoji lewat event window
    `messages-send-sticker`; jembatan di dalam komponen percakapan (lihat
    resources/views/*/livewire/messages/messages/messages.blade.php) yang
    memanggil $wire.sendSticker().

    PENTING -- jangan pakai tanda kutip GANDA di dalam atribut x-data.
    Atributnya diapit kutip ganda, jadi satu kutip ganda saja sudah
    memotong atribut dan seluruh scope Alpine-nya mati (SyntaxError).
--}}
@php
    /*
     * Daftar stiker. Dipakai emoji, bukan gambar: tidak perlu aset gambar,
     * ukurannya menyesuaikan, dan tetap terbaca di layar kecil maupun di
     * notifikasi.
     */
    $stickerList = [
        '😀', '😃', '😄', '😁', '😆', '😅', '🤣', '😂', '🙂', '🙃',
        '😉', '😊', '😇', '🥰', '😍', '🤩', '😘', '😗', '😚', '😙',
        '🥲', '😋', '😛', '😜', '🤪', '😝', '🤑', '🤗', '🤭', '🤫',
        '🤔', '🤐', '🤨', '😐', '😑', '😶', '😏', '😒', '🙄', '😬',
        '🤥', '😌', '😔', '😪', '🤤', '😴', '😷', '🤒', '🤕', '🤢',
        '🤮', '🤧', '🥵', '🥶', '🥴', '😵', '🤯', '🤠', '🥳', '😎',
        '🤓', '🧐', '😕', '😟', '🙁', '😮', '😯', '😲', '😳', '🥺',
        '😦', '😧', '😨', '😰', '😥', '😢', '😭', '😱', '😖', '😣',
        '😞', '😓', '😩', '😫', '🥱', '😤', '😡', '😠', '🤬', '😈',
        '👿', '💀', '☠️', '💩', '🤡', '👹', '👺', '👻', '👽', '🤖',
        '😺', '😸', '😹', '😻', '😼', '😽', '🙀', '😿', '😾',
        '❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '🤎', '💔',
        '💯', '✨', '🔥', '🎉', '🎊', '👍', '👎', '👏', '🙌', '🤝',
        '💐', '🌸', '🌹', '💍', '👰', '🤵', '💒', '🎀', '💌', '📩',
    ];
@endphp

<div
    x-data="{
        stickerOpen: false,

        stickerOpenModal() {
            this.stickerOpen = true;
            document.body.classList.add('overflow-hidden');
        },

        stickerClose() {
            this.stickerOpen = false;
            document.body.classList.remove('overflow-hidden');
        },

        stickerSend(sticker) {
            window.dispatchEvent(new CustomEvent('messages-send-sticker', {
                detail: { sticker: sticker },
            }));

            this.stickerClose();
        },
    }"
    x-on:messages-sticker-open.window="stickerOpenModal()"
    x-on:keydown.escape.window="stickerOpen && stickerClose()"
    x-on:livewire:navigating.window="stickerClose()"
    class="contents"
>
    <template x-teleport="body">
        <div
            x-cloak
            x-show="stickerOpen"
            x-on:click.self="stickerClose()"
            class="fixed inset-0 z-[9998] flex items-center justify-center overflow-y-auto bg-gray-950/60 p-2 sm:p-4"
            role="dialog"
            aria-modal="true"
            aria-label="{{ __('New sticker') }}"
        >
            <div
                x-on:click.stop
                class="fi-glass-modal w-full max-w-md rounded-xl bg-white shadow-xl ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
            >
                <div class="flex items-center justify-between gap-2 border-b border-gray-200 px-3 py-2.5 dark:border-white/10 sm:px-6 sm:py-4">
                    <h3 class="truncate text-base font-semibold text-gray-950 dark:text-white">{{ __('Stiker') }}</h3>

                    <button type="button" x-on:click="stickerClose()" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300" aria-label="{{ __('Tutup') }}">
                        <x-filament::icon icon="/images/icons/x.svg" class="h-5 w-5" />
                    </button>
                </div>

                <div class="max-h-[60vh] overflow-y-auto p-3 sm:p-4">
                    {{--
                        Jumlah kolom memakai style inline, bukan kelas
                        `grid-cols-*`: kelas itu hanya ada kalau bundle CSS
                        sudah dibangun ulang setelah file ini dibuat, dan kalau
                        belum, grid-nya jatuh jadi satu kolom panjang.
                    --}}
                    <div class="grid gap-1" style="grid-template-columns: repeat(auto-fill, minmax(2.5rem, 1fr));">
                        @foreach ($stickerList as $sticker)
                            <button
                                type="button"
                                x-on:click="stickerSend('{{ $sticker }}')"
                                class="grid h-10 w-10 place-items-center rounded-lg text-2xl transition-colors hover:bg-gray-100 dark:hover:bg-white/10"
                                title="{{ __('Kirim stiker') }}"
                            >{{ $sticker }}</button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
