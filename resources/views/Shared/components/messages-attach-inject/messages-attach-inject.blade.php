{{--
    Jembatan lampiran dari modal ke komponen percakapan.

    Kamera, Audio, dan Contact menghasilkan `File` di sisi browser lalu
    menyerahkannya ke `window.messagesAttachFile()`. Fungsi itu menembakkan
    event window `messages-attach-file`; jembatan di dalam komponen percakapan
    (lihat resources/views/*/livewire/messages/messages/messages.blade.php)
    yang mengunggah filenya lewat `$wire.upload()` lalu memanggil
    `$wire.sendPendingAttachment()`.

    PENTING 1 -- kenapa bukan FilePond.

    Field `FileUpload` milik Filament di halaman ini bergantung pada FilePond,
    dan komponen FilePond-nya tidak pernah selesai diinisialisasi: asset
    `file-upload.js` dimuat lewat `x-load-src` (lazy load) yang menggantung,
    jadi input file pun tidak pernah punya `wire:model`. Akibatnya lampiran
    hasil tangkapan tidak pernah sampai ke server -- tombol "Kirim Audio"
    tampak berhasil, tapi gelembung pesannya tidak pernah muncul.

    Karena itu jalur lampiran ini berdiri sendiri: `$wire.upload()` +
    properti `pendingAttachment` di komponen, lalu `sendPendingAttachment()`.

    PENTING 2 -- modal berada di luar akar Livewire.

    Modal (kamera/audio/kontak) dirender di level HALAMAN, jadi tidak punya
    akses ke `$wire`. Karena itu ia menembakkan event window, dan jembatan di
    dalam komponen percakapan yang bekerja dengan `$wire`.
--}}
<script>
    (function () {
        if (window.messagesAttachFile) return;

        /* Serahkan file ke komponen percakapan. True kalau diterima. */
        window.messagesAttachFile = function (file) {
            if (!file) return false;

            window.dispatchEvent(new CustomEvent('messages-attach-file', {
                detail: { file: file },
            }));

            return true;
        };
    })();
</script>
