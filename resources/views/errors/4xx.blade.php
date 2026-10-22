{{--
    View `errors::4xx` -- semua status 4xx yang tidak punya view sendiri.

    Laravel 12 mencari view untuk sebuah status dengan urutan:
      1. `errors::{status}`  (mis. `errors::405`)
      2. `errors::4xx` / `errors::5xx`
      3. `null` -- kalau tidak ada, framework merender halaman error
         bawaan SYMFONY (bukan Laravel): "Oops! An Error Occurred", tanpa
         gaya, tanpa bahasa aplikasi.

    Karena (3) itu jelek dan tidak memakai terjemahan, step 2 wajib ada.

    Kode statusnya diambil dari exception (lihat `Shared.errors.glass.glass`),
    jadi halaman 405 menampilkan angka 405 -- bukan 400.
--}}
@include('Shared.errors.glass.glass', ['status' => 0])
