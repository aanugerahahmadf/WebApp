@props([
    'active' => false,
    'activeIcon' => null,
    'badge' => null,
    'badgeColor' => null,
    'badgeTooltip' => null,
    'icon' => null,
    'shouldOpenUrlInNewTab' => false,
    'url' => null,
])

<style>
    .fi-dropdown:hover .fi-topbar-group-toggle-icon {
        transform: rotate(180deg);
    }
</style>

@php
    $tag = $url ? 'a' : 'button';
@endphp

<li
    @class([
        'fi-topbar-item',
        // @deprecated `fi-topbar-item-active` has been replaced by `fi-active`.
        'fi-active fi-topbar-item-active' => $active,
    ])
>
    <{{ $tag }}
        @if ($url)
            {{ \Filament\Support\generate_href_html($url, $shouldOpenUrlInNewTab) }}
        @else
            type="button"
        @endif
        @class([
            'fi-topbar-item-button flex items-center justify-center gap-x-2 rounded-lg px-3 py-2 outline-none transition duration-75',
        ])
    >
        @if ($icon || $activeIcon)
            <x-filament::icon
                :icon="($active && $activeIcon) ? $activeIcon : $icon"
                @class([
                    'fi-topbar-item-icon h-5 w-5',
                    'text-gray-400 dark:text-gray-500' => ! $active,
                    'text-primary-600 dark:text-primary-400' => $active,
                ])
            />
        @endif

        <span
            @class([
                'fi-topbar-item-label text-sm font-medium',
                'text-gray-700 dark:text-gray-200',
            ])
        >
            {{ $slot }}
        </span>

        @if (filled($badge))
            <x-filament::badge
                :color="$badgeColor"
                size="sm"
                :tooltip="$badgeTooltip"
            >
                {{ $badge }}
            </x-filament::badge>
        @endif

        @if (! $url)
            <x-filament::icon
                icon="heroicon-m-chevron-down"
                icon-alias="panels::topbar.group.toggle-button"
                @class([
                    'fi-topbar-group-toggle-icon h-5 w-5 transition-transform duration-200',
                    'text-primary-600 dark:text-primary-400',
                ])
            />
        @endif
    </{{ $tag }}>
</li>