{{--
    Markup gelembung aksi pesan -- SATU instance per panel chat, bukan satu per
    pesan. Sumber kebenarannya adalah `payload` yang diisi Alpine dari atribut
    `data-msg-payload` pada baris pesan yang sedang ditahan; isinya dirender
    ulang di sini oleh `x-for`.

    Panel di-teleport ke <body> supaya tidak pernah terpotong kotak chat yang
    memakai `overflow-y-auto`. See message-bubble-script.blade.php untuk
    alasannya.

    Bentuk dan warnanya mengikuti iOS Messages: panel gelap membulat, deretan
    emoji mendatar di atas, daftar aksi di bawahnya, dan ujung panah yang
    menunjuk ke pesan asal. Panel sengaja gelap di mode terang maupun gelap --
    gelembung aksi di iOS tidak pernah terang, dan latar gelap membuat emoji
    yang warnanya sudah kuat tetap terbaca.
--}}
<template x-teleport="body">
    <div x-ref="panel"
        x-show="open"
        x-cloak
        @click.stop
        @pointerdown.stop
        @contextmenu.prevent
        :style="style"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="fixed z-[9998] flex max-h-[min(75vh,26rem)] w-60 flex-col overflow-hidden rounded-2xl bg-gray-900 text-white shadow-2xl ring-1 ring-white/10"
        role="menu">

        {{-- Ujung panah. Segitiga (bukan kotak diputar 45 derajat) supaya tidak
             ada garis ring yang memotong diagonalnya. --}}
        <div x-show="!panelBelow"
            aria-hidden="true"
            class="absolute -top-2 h-0 w-0 border-b-[10px] border-l-[6px] border-r-[6px] border-l-transparent border-r-transparent border-b-gray-900"
            :style="'left: ' + caret + '; margin-left: -6px;'"></div>
        <div x-show="panelBelow"
            aria-hidden="true"
            class="absolute -bottom-2 h-0 w-0 border-t-[10px] border-l-[6px] border-r-[6px] border-l-transparent border-r-transparent border-t-gray-900"
            :style="'left: ' + caret + '; margin-left: -6px;'"></div>

        {{-- Deretan emoji. Lebarnya seragam (h-9 w-9) supaya baris tidak
             bergeser saat jumlah emoji berbeda antar panel. Baris ini
             disembunyikan kalau daftarnya kosong -- misalnya pesan yang sudah
             dihapus untuk pengguna ini, yang tidak punya aksi reaksi sama sekali
             -- supaya gelembung tidak menyisakan ruang kosong dan garis
             pemisah yang menggantung. --}}
        <div x-show="emojis().length > 0"
            class="flex shrink-0 items-center gap-0.5 overflow-x-auto px-2 py-2.5 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            <template x-for="emoji in emojis()" :key="emoji">
                <button type="button"
                    @click="react(emoji)"
                    class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-xl leading-none transition hover:bg-white/10 active:scale-90"
                    x-text="emoji"></button>
            </template>
        </div>

        {{-- Daftar aksi. Sendiri bisa digulir: pesan yang panjang atau pengguna
             dengan font besar bisa membuat isi gelembung lebih tinggi dari
             viewport, dan bagian yang memotong adalah daftar aksi, bukan emoji.
             Garis pemisah hanya digambar kalau baris emoji benar-benar ada. --}}
        <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain py-1"
            :class="emojis().length > 0 ? 'border-t border-white/10' : ''">
            <template x-for="action in actions()" :key="action.key">
                <button type="button"
                    @click="run(action)"
                    role="menuitem"
                    class="flex w-full items-center gap-3 px-3.5 py-2 text-left text-sm transition hover:bg-white/10"
                    :class="action.tone === 'danger' ? 'text-red-400 hover:bg-red-500/10' : 'text-white/90'">
                    <span class="grid h-4 w-4 shrink-0 place-items-center" x-html="action.icon"></span>
                    <span class="truncate" x-text="action.label"></span>
                </button>
            </template>
        </div>
    </div>
</template>
