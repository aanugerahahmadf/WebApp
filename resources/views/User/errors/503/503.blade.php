{{--
    Halaman error 503 (storefront -- panel User).

    Seluruh tampilan berasal dari satu view bersama:
    Shared.errors.glass.glass (kaca + animasi). File ini hanya meneruskan
    kode status; isi, warna, dan tombolnya ada di
    App\Support\ErrorPage\ErrorPage.
--}}
@include('Shared.errors.glass.glass', ['status' => 503])