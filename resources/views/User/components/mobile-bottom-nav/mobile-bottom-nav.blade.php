@php
    use function Filament\Support\generate_href_html;
@endphp

<style data-navigate-track>
    .fi-bottom-nav {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        z-index: 35;   /* above fi-main (z-index:0), below modals (z-index:40+) */
        display: flex;
        align-items: center;
        justify-content: space-around;
        height: 4rem;
        padding-bottom: env(safe-area-inset-bottom, 0px);
        border-top-width: 1px;
        border-top-style: solid;
        /* Match topbar: bg-white / dark:bg-gray-900 */
        background-color: rgb(255 255 255);
        border-top-color: rgb(229 231 235); /* gray-200 */
        box-shadow: 0 -1px 3px 0 rgb(0 0 0 / 0.05), 0 -1px 2px -1px rgb(0 0 0 / 0.05);
        backdrop-filter: none;
        -webkit-backdrop-filter: none;
        /* Never allow parent overflow/stacking context to clip this */
        transform: translateZ(0);
        will-change: transform;
    }

    .dark .fi-bottom-nav {
        /* Match topbar dark: dark:bg-gray-900 dark:ring-white/10 */
        background-color: rgb(17 24 39); /* gray-900 — same as topbar dark */
        border-top-color: rgb(255 255 255 / 0.1); /* white/10 — same as topbar ring */
    }

    .fi-bottom-nav-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        flex: 1;
        gap: 0.25rem;
        padding: 0.5rem 0;
        border: none;
        background: none;
        text-decoration: none;
        cursor: pointer;
        position: relative;
        -webkit-tap-highlight-color: transparent;
        transition: color 0.15s ease;
        color: rgb(156 163 175);
    }

    .dark .fi-bottom-nav-item {
        color: rgb(107 114 128);
    }

    .fi-bottom-nav-item.fi-active {
        color: #ca8a04;
    }

    .dark .fi-bottom-nav-item.fi-active {
        color: #facc15;
    }

    .fi-bottom-nav-label {
        font-size: 0.625rem;
        line-height: 1;
        font-weight: 500;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
        text-align: center;
        padding: 0 0.25rem;
    }

    .fi-bottom-nav-icon-wrapper {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .fi-bottom-nav-badge {
        position: absolute;
        top: -0.25rem;
        right: -0.5rem;
        min-width: 1rem;
        height: 1rem;
        border-radius: 9999px;
        color: #fff;
        font-size: 0.625rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0 0.25rem;
        line-height: 1;
    }

    .fi-bottom-nav-badge-dot {
        position: absolute;
        top: -0.125rem;
        right: -0.125rem;
        width: 0.5rem;
        height: 0.5rem;
        border-radius: 9999px;
    }

    /* ── Desktop: sembunyikan bottom nav ── */
    @media (min-width: 1024px) {
        .fi-bottom-nav {
            display: none !important;
        }
    }

    /* ── Mobile: sembunyikan sidebar & tombol hamburger ── */
    @media (max-width: 1023px) {
        .fi-sidebar,
        .fi-sidebar-open,
        .fi-sidebar-close-overlay,
        .fi-sidebar-header,
        .fi-sidebar-nav {
            display: none !important;
        }

        .fi-topbar-open-sidebar-btn,
        .fi-topbar-close-sidebar-btn {
            display: none !important;
        }

        .fi-topbar > nav {
            padding-left: 1rem !important;
        }

        .fi-topbar .fi-icon-btn-icon,
        .fi-topbar .fi-notifications-open-btn svg {
            width: 1.5rem !important;
            height: 1.5rem !important;
        }

        .fi-topbar .fi-icon-btn {
            width: 2.5rem !important;
            height: 2.5rem !important;
        }

        .fi-topbar-notif-badge {
            transform: translate(30%, -30%);
        }

        /* ══════════════════════════════════════════════════════════════
           FIX: Bottom nav fixed + .fi-main-ctn jadi scroll container
           saat scroll di mobile.

           Topbar dibuat position:fixed oleh User.css/Admin.css (semua panel) —
           tidak di-override di sini agar tidak konflik.
        ══════════════════════════════════════════════════════════════ */
        html, body {
            height: 100% !important;
            overflow: hidden !important;
        }

        .fi-layout {
            height: 100% !important;
            overflow: hidden !important;
            min-height: unset !important;
        }

        /* .fi-main-ctn sekarang jadi scroll container */
        .fi-main-ctn {
            height: 100% !important;
            min-height: unset !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            -webkit-overflow-scrolling: touch !important;
            /* Padding bawah agar konten tidak tertutup bottom nav */
            padding-bottom: calc(4rem + env(safe-area-inset-bottom, 0px)) !important;
        }
    }
</style>

<nav
    x-data="{}"
    class="fi-bottom-nav"
    aria-label="Bottom navigation"
>
    @foreach ($items as $item)
        @php
            $isActive = $item->isActive();
            $icon = $isActive && $item->getActiveIcon() ? $item->getActiveIcon() : $item->getIcon();
            $badge = $item->getBadge();
            $badgeColor = $item->getBadgeColor() ?? 'primary';
            $badgeCssColor = is_string($badgeColor) ? "var(--{$badgeColor}-500)" : 'var(--primary-500)';
        @endphp

        <a
            {{ generate_href_html($item->getUrl()) }}
            @class(['fi-bottom-nav-item', 'fi-active' => $isActive])
            @if ($isActive) aria-current="page" @endif
            wire:navigate.hover
        >
            <span class="fi-bottom-nav-icon-wrapper">
                <x-filament::icon
                    :icon="$icon"
                    class="fi-bottom-nav-item-icon h-6 w-6"
                />

                @if ($badge !== null && $badge !== '')
                    @if (is_numeric($badge))
                        <span class="fi-bottom-nav-badge" style="background-color: {{ $badgeCssColor }}">{{ $badge }}</span>
                    @else
                        <span class="fi-bottom-nav-badge-dot" style="background-color: {{ $badgeCssColor }}"></span>
                    @endif
                @endif
            </span>

            <span class="fi-bottom-nav-label">{{ $item->getLabel() }}</span>
        </a>
    @endforeach

    @if ($moreButtonEnabled)
        <button
            type="button"
            x-on:click="$store.sidebar.open()"
            class="fi-bottom-nav-item"
            aria-label="{{ $moreButtonLabel }}"
        >
            <span class="fi-bottom-nav-icon-wrapper">
                <x-filament::icon
                    icon="heroicon-o-bars-3"
                    class="fi-bottom-nav-item-icon h-6 w-6"
                />
            </span>

            <span class="fi-bottom-nav-label">{{ $moreButtonLabel }}</span>
        </button>
    @endif
</nav>
