{{--
    Grid foto katalog.

    Kolom FOTO memakai view ini, bukan `ImageColumn` bawaan Filament:
    kolom bawaan merender SEMUA foto dalam satu collection sebagai baris
    horizontal, padahal sel kolom cuma ~56px -- jadi lima fotonya meluber
    keluar sel dan memunculkan scrollbar horizontal.

    State kolomnya = accessor `Catalog::gallery_urls` -- array berisi
    ['id', 'url', 'thumb', 'collection', 'order'].

    NAMA KOLOMNYA HARUS `gallery_urls`, bukan `catalog_image` dan bukan
    `photos`. Dua-duanya bukan atribut model: `$getState()` mengembalikan
    null, array fotonya kosong, dan selnya tampil KOSONG tanpa error --
    persis seperti tidak ada fotonya sama sekali.

    LIMA foto per katalog (2 kolom x 3 baris, baris terakhir isi satu).
    Fewer dari lima berarti katalog itu memang lebih sedikit fotonya --
    tidak dipadding, karena memalsukan foto cuma menutupi data yang hilang.
--}}
@php
    $photos = $getState();

    if (! is_array($photos)) {
        $photos = is_iterable($photos) ? iterator_to_array($photos) : [];
    }

    // Buang entri tanpa URL: `src=""` bikin browser gambar kosong yang
    // kelihatan seperti sel yang rusak.
    $photos = array_values(array_filter(
        $photos,
        fn ($photo): bool => is_array($photo) && filled($photo['thumb'] ?? $photo['url'] ?? null),
    ));
@endphp

<div class="grid w-14 grid-cols-2 gap-0.5">
    @foreach (array_slice($photos, 0, 5) as $photo)
        <img
            src="{{ $photo['thumb'] ?? $photo['url'] }}"
            alt="{{ $photo['collection'] ?? __('Foto') }}"
            class="h-6 w-6 rounded object-cover"
            loading="lazy"
        />
    @endforeach
</div>
