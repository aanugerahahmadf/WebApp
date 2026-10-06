@props([
    'breadcrumbs' => [],
])

@php
    $iconClasses = 'fi-breadcrumbs-item-separator flex h-5 w-5 text-gray-400 dark:text-gray-500';

    /*
     * Teal, bukan abu-abu.
     *
     * Abu-abu (text-gray-500 dark:text-gray-400) hilang di mode gelap: dasar
     * panel gelap hampir sama nadanya, jadi crumb jadi tak terbaca.
     * text-gray-950 dark:text-white mengikuti warna judul halaman di panel
     * User, sehingga tetap terbaca di light, dark, dan mode sistem.
     *
     * Komentar ini PHP, bukan komentar Blade: blok @php hanya berisi PHP,
     * dan komentar Blade di dalamnya jadi ParseError saat view dikompilasi --
     * yang gejalanya halaman 500 di SELURUH panel User, bukan cuma di sini.
     */
    $itemLabelClasses = 'fi-breadcrumbs-item-label text-sm font-bold text-gray-950 dark:text-white';
@endphp

<nav {{ $attributes->class(['fi-breadcrumbs']) }}>
    <ol class="fi-breadcrumbs-list flex flex-wrap items-center gap-x-2">
        @foreach ($breadcrumbs as $url => $label)
            <li class="fi-breadcrumbs-item flex items-center gap-x-2">
                @if (! $loop->first)
                    <x-filament::icon
                        alias="breadcrumbs.separator"
                        icon="heroicon-m-chevron-right"
                        @class([
                            $iconClasses,
                            'rtl:hidden',
                        ])
                    />

                    <x-filament::icon
                        {{-- @deprecated Use `breadcrubs.separator.rtl` instead of `breadcrumbs.separator` for RTL. --}}
                        :alias="['breadcrumbs.separator.rtl', 'breadcrumbs.separator']"
                        icon="heroicon-m-chevron-left"
                        @class([
                            $iconClasses,
                            'ltr:hidden',
                        ])
                    />
                @endif

                @if (is_int($url))
                    <span class="{{ $itemLabelClasses }}">
                        {{ $label }}
                    </span>
                @else
                    <a
                        {{ \Filament\Support\generate_href_html($url) }}
                        class="{{ $itemLabelClasses }} transition duration-75 hover:text-gray-700 dark:hover:text-gray-200"
                    >
                        {{ $label }}
                    </a>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
