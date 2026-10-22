{{--
    View `errors::5xx` -- semua status 5xx yang tidak punya view sendiri.

    Kenapa file ini perlu: lihat catatan di `resources/views/errors/4xx.blade.php`.
    Tanpa `errors::5xx`, status seperti 502 atau 504 merender halaman error
    bawaan Symfony, tanpa gaya dan tanpa terjemahan aplikasi.

    Kode statusnya diambil dari exception (lihat `Shared.errors.glass.glass`).
--}}
@include('Shared.errors.glass.glass', ['status' => 0])
