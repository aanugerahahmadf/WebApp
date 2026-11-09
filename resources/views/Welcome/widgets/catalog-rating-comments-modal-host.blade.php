{{--
    Host modal "Komentar Penilaian Katalog".

    Modalnya bukan halaman: tidak ada route, tidak ada item menu. Widget
    CatalogRatingCommentsWidget mengirim event `open-rating-comments`, state
    Alpine di akar widget yang.mount komponen, dan komponen itu mengirim
    `close-rating-comments` saat ditutup.

    Host ini ikut TIDAK TERPAKAI di runtime: satu-satunya yang memanggilnya
    adalah view kartu catalog-rating-comments, dan kartu itu sengaja tidak
    didaftarkan lagi di `Home::getWidgets()`. Kelas dan view-nya tetap ada di
    disk mengikuti aturan "ganti nama, jangan hapus"; komentar pembacaannya
    hidup di section detail katalog dan halaman `/my-reviews`.

    Komponen dulu baru di-mount setelah dibuka supaya query daftar penuh tidak
    ikut berjalan pada setiap kunjungan Beranda.
--}}
<div
    x-on:open-rating-comments.window="ratingCommentsOpen = true"
    x-on:close-rating-comments.window="ratingCommentsOpen = false"
>
    <template x-if="ratingCommentsOpen">
        @livewire(\App\Livewire\Welcome\CatalogRatingCommentsModal\CatalogRatingCommentsModal::class)
    </template>
</div>
