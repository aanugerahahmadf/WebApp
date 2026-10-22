{{--
    View `errors::minimal` -- layout yang dipakai view bawaan Laravel
    (`errors::401`, `errors::404`, dst).

    PENTING: penamaannya FILE, bukan folder.

    Namespace `errors::` menunjuk ke folder `resources/views/errors`, jadi
    `errors::minimal` mencari `resources/views/errors/minimal.blade.php`.
    Folder `errors/minimal/minimal.blade.php` akan terbaca sebagai view
    bernama `errors::minimal.minimal` -- bukan `errors::minimal` -- jadi
    halaman polos bawaan Laravel yang tetap dipakai, tanpa error sama sekali.
--}}
@php
    $gxCode = (int) $__env->yieldContent('code', '0');
    $gxMessage = trim((string) $__env->yieldContent('message', ''));
@endphp

@include('Shared.errors.glass.glass', [
    'status' => $gxCode > 0 ? $gxCode : 0,
    'messageOverride' => $gxMessage,
])
