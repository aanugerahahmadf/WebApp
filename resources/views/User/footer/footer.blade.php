@php
    $isMobile = \App\Providers\NativeServiceProvider\NativeServiceProvider::isAnyMobile();
    $isAuth   = str_contains(request()->route()?->getName() ?? '', 'auth');
@endphp

<footer
    x-data="{}"
    x-bind:class="$store.sidebar && $store.sidebar.isOpen ? 'fi-footer-sidebar-open' : ''"
    class="fixed bottom-0 left-0 right-0 z-10 flex items-center justify-center
        {{ $isAuth ? 'fi-auth-footer' : 'fi-main-footer' }}"
    style="pointer-events: none;">
    <div
        class="px-6 py-2 text-[11px] font-semibold text-center text-gray-900 dark:text-gray-100"
        style="pointer-events: auto;">
        &copy; {{ date('Y') }}
        <span class="text-primary-600 dark:text-primary-400">{{ __('Dekorasi Bunga Pernikahan') }}</span>.
        {{ __('Seluruh hak cipta dilindungi undang-undang.') }}
    </div>
</footer>

<style>
    /* Main footer (non-auth pages) */
    .fi-main-footer {
        height: calc(3.5rem + env(safe-area-inset-bottom, 0px));
        background-color: white;
        border-top: 1px solid rgba(0, 0, 0, 0.08);
        padding-bottom: env(safe-area-inset-bottom, 0px);
    }

    .dark .fi-main-footer {
        background-color: rgb(17 24 39);
        border-top-color: rgba(255, 255, 255, 0.10);
    }

    /* Auth footer (login/register pages) */
    .fi-auth-footer {
        position: static !important;
        background-color: transparent !important;
        border-top: none !important;
        height: auto !important;
        margin-top: 1.5rem;
        padding-bottom: calc(1.5rem + env(safe-area-inset-bottom, 0px)) !important;
    }

    /* User panel: footer hanya tampil di desktop (>=1024px).
       Mobile (< 1024px): footer disembunyikan & digantikan bottom-nav (fixed, z-35)
       yang menggantikan footer. Kalau dibiarkan, footer (fixed bottom:0, putih)
       menumpuk di bawah bottom-nav dan menutup baris bawah konten/grid. */
    @media (max-width: 1023px) {
        .fi-footer-sidebar-open,
        .fi-main-footer {
            display: none !important;
        }
    }

    /* Desktop: footer hanya (3.5rem), tidak ada bottom-nav */
    @media (min-width: 1024px) {
        /* User panel: comprehensive padding for ALL layouts to prevent footer overlap */
        body.fi-panel-user:not(:has(.fi-auth-footer)) .fi-layout,
        body.fi-panel-user:not(:has(.fi-auth-footer)) .fi-main,
        body.fi-panel-user:not(:has(.fi-auth-footer)) .fi-main-ctn,
        body.fi-panel-user:not(:has(.fi-auth-footer)) .fi-simple-layout,
        body.fi-panel-user:not(:has(.fi-auth-footer)) .fi-simple-main-ctn,
        body.fi-panel-user:not(:has(.fi-auth-footer)) .fi-simple-main,
        body.fi-panel-user:not(:has(.fi-auth-footer)) .fi-page,
        body.fi-panel-user:not(:has(.fi-auth-footer)) .fi-content {
            padding-bottom: calc(3.5rem + env(safe-area-inset-bottom, 0px)) !important;
        }
    }
    /*
     * Mobile (< 1024px): footer disembunyikan & digantikan bottom-nav.
     * Padding bawah untuk konten mobile sudah ditangani SATU sumber saja oleh
     * .fi-main-ctn (scroll container) di mobile-bottom-nav.blade.php.
     * Dulu ditambah 7.5rem di sini → double padding → pita latar kosong besar
     * di bawah grid / baris bawah grid ikut tertutup.
     */
</style>