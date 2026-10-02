{{--
    Breadcrumb halaman Auth (Sign In / Sign Up / OTP / Complete Profile).

    Dirender lewat render hook `panels::simple-page.start` (lihat
    UserPanelProvider), bukan di-@include dari masing-masing view. Alasannya
    posisi: `page.simple` menaruh slot apa pun DI BAWAH header/logo, sedangkan
    yang diminta breadcrumb di ATAS logo. Hook tersebut dirender sebelum
    <header> logo, jadi satu titik pas untuk semua halaman auth dan tidak ada
    view yang harus ingat memanggilnya.

    Rata kiri (justify-start) dan sejajar margin kiri kartu.

    $authPage: instance halaman Livewire-nya. Dikirim eksplisit karena view ini
    dirender dari render hook (di luar lifecycle komponen), dengan fallback ke
    $this supaya partial tetap bisa dipakai langsung dari dalam sebuah view.
--}}
@php
    $authPage = $authPage ?? null;

    if (! $authPage && is_object($this) && method_exists($this, 'getBreadcrumbs')) {
        $authPage = $this;
    }

    $authCrumbs = $authPage ? $authPage->getBreadcrumbs() : [];
@endphp
@if (! empty($authCrumbs))
    {{-- Opening tag sengaja satu baris: consumer (test, parser) mencari penanda
         literal `<nav aria-label="breadcrumb"`, jadi jangan dipecah. --}}
    <nav aria-label="breadcrumb" class="mb-6 flex flex-wrap items-center justify-start gap-x-1.5 gap-y-1 text-left text-sm rtl:justify-end">
        @foreach ($authCrumbs as $crumbUrl => $crumbLabel)
            @if (! $loop->first)
                <span class="text-gray-400 dark:text-gray-600" aria-hidden="true">/</span>
            @endif
            @if (is_string($crumbUrl))
                <a
                    href="{{ $crumbUrl }}"
                    class="text-gray-500 transition hover:text-gray-800 hover:underline dark:text-gray-400 dark:hover:text-gray-100"
                >
                    {{ $crumbLabel }}
                </a>
            @else
                <span class="font-medium text-gray-800 dark:text-gray-100" aria-current="page">
                    {{ $crumbLabel }}
                </span>
            @endif
        @endforeach
    </nav>
@endif
