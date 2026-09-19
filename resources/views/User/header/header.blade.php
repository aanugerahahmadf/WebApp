@php
    $isMobile = \App\Providers\NativeServiceProvider\NativeServiceProvider::isAnyMobile();
    // Dapatkan ID WeddingFlowersDecorasi secara dinamis agar tidak 404 jika ID bukan 1
    $firstWeddingFlowersDecorasiId = 1;
@endphp
<header
    class="fixed top-0 left-0 right-0 z-50 flex items-center justify-between px-4 bg-white border-b border-gray-200 dark:bg-gray-900 dark:border-white/10 sm:px-6 lg:px-6"
    style="
        pointer-events: auto;
        -webkit-tap-highlight-color: transparent;
        height: 56px;
        padding-top: env(safe-area-inset-top, 0px);
        min-height: calc(56px + env(safe-area-inset-top, 0px));
    ">

    {{-- Logo & Brand Name: hidden di mobile, tampil di web --}}
    @if(!$isMobile)
        <a href="{{ \App\Providers\NativeServiceProvider\NativeServiceProvider::normalizeUrl(url('/')) }}"
            class="flex items-center shrink-0 {{ (isset($hideLogo) && $hideLogo) ? 'hidden' : '' }}">
            <img src="{{ asset('favicon.ico') }}" alt="{{ __('Dekorasi Bunga Pernikahan Logo') }}"
                class="w-8 h-8 rounded shrink-0">
            <span class="ml-3 text-lg font-bold tracking-tight text-gray-900 dark:text-gray-100 hidden sm:block">
                {{ __('Dekorasi Bunga Pernikahan') }}
            </span>
        </a>
    @else
        {{-- Mobile: spacer kiri agar nav rata kanan --}}
        <div class="flex-1"></div>
    @endif

    <nav class="flex items-center {{ $isMobile ? 'gap-2' : 'gap-2 lg:gap-3' }}">
        {{-- Sembunyikan di device mobile (Android/iOS) dan tampilkan di PC/Laptop/Macbook --}}
        @if(!$isMobile)
            <div class="hidden lg:flex items-center gap-2 lg:gap-3">
                @auth
                    <a href="{{ \App\Providers\NativeServiceProvider\NativeServiceProvider::normalizeUrl(route('filament.user.pages.home')) }}"
                        class="flex items-center justify-center px-5 h-10 text-sm min-w-10 dark:text-[#EDEDEC] text-[#1b1b18] ring-1 ring-gray-950/10 dark:ring-white/20 hover:bg-gray-50 dark:hover:bg-white/5 rounded-md font-medium transition-all active:scale-95 whitespace-nowrap">
                        {{ __('Beranda') }}
                    </a>
                @else
                    <a href="{{ \App\Providers\NativeServiceProvider\NativeServiceProvider::normalizeUrl(route('filament.user.auth.login')) }}"
                        class="flex items-center justify-center px-5 h-10 text-sm min-w-10 dark:text-[#EDEDEC] text-[#1b1b18] ring-1 ring-gray-950/10 dark:ring-white/20 hover:bg-gray-50 dark:hover:bg-white/5 rounded-md font-medium transition-all active:scale-95 whitespace-nowrap">
                        {{ __('Log in') }}
                    </a>
                    <a href="{{ \App\Providers\NativeServiceProvider\NativeServiceProvider::normalizeUrl(route('filament.user.auth.register')) }}"
                        class="flex items-center justify-center px-5 h-10 text-sm min-w-10 dark:text-[#EDEDEC] text-[#1b1b18] ring-1 ring-gray-950/10 dark:ring-white/20 hover:bg-gray-50 dark:hover:bg-white/5 rounded-md font-medium transition-all active:scale-95 whitespace-nowrap">
                        {{ __('Register') }}
                    </a>
                @endauth
            </div>
        @endif

        {{-- Theme Switcher --}}
        <button
            x-data="{ theme: null }"
            x-init="
                $watch('theme', () => {
                    if (theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                        document.documentElement.classList.add('dark');
                    } else {
                        document.documentElement.classList.remove('dark');
                    }
                    localStorage.setItem('theme', theme);
                });
                theme = localStorage.getItem('theme') || 'system';
            "
            type="button"
            aria-label="{{ __('Toggle Dark Mode') }}"
            x-on:click="theme = (theme === 'light' || theme === 'system' ? 'dark' : 'light')"
            class="flex items-center justify-center w-10 h-10 rounded-md ring-1 ring-gray-950/10 dark:ring-white/20 transition hover:bg-gray-50 focus:bg-gray-50 dark:hover:bg-white/5 dark:focus:bg-white/5">
            <x-heroicon-o-sun x-show="theme !== 'dark'" class="w-5 h-5 text-gray-500 dark:text-gray-400" />
            <x-heroicon-o-moon x-show="theme === 'dark'" class="w-5 h-5 text-gray-500 dark:text-gray-400" x-cloak />
        </button>

        {{-- Language Switcher: tampil di semua platform --}}
        @include('User.filament-language-switcher.language-switcher.language-switcher')
    </nav>
</header>

{{-- Spacer agar konten tidak tertutup header --}}
<div style="height: calc(56px + env(safe-area-inset-top, 0px));"></div>
