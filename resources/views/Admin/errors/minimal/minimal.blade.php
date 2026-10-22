{{--
    Layout "minimal" panel Admin.

    Seluruh tampilan (kaca + animasi) berasal dari satu view bersama:
    `Shared.errors.glass.glass`. File ini hanya meneruskan judul & kode
    status ke sana.

    Kontrak Laravel: `@yield('code')` dan `@yield('message')`.

    Kode status 0 berarti "tidak ada section 'code'" -- lihat catatan di
    `resources/views/errors/minimal/minimal.blade.php`. Yang mengambilnya
    dari exception adalah `Shared.errors.glass.glass`.
--}}
@php
    $gxCode = (int) $__env->yieldContent('code', '0');
    $gxMessage = trim((string) $__env->yieldContent('message', ''));
@endphp

@include('Shared.errors.glass.glass', [
    'status' => $gxCode > 0 ? $gxCode : 0,
    'messageOverride' => $gxMessage,
])
