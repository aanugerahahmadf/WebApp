{{--
    Phone-only bottom navigation.

    Rendered through a `panels::body.end` render hook scoped to each panel.
    The server already refuses to render this on desktop and on auth pages (see
    App\Support\MobileNav::shouldRender); the `.fi-bottom-nav` CSS additionally
    hides it above the mobile breakpoint so a mid-session resize cannot leave a
    stray bar behind.

    Items are declared per panel in config/app-platform.php.
--}}
@php
    $panel = \Filament\Facades\Filament::getCurrentPanel();

    $items = \App\Support\MobileNav\MobileNav::items($panel?->getId());

    /*
     * Both panels use topNavigation() + spa(), so a tap on the bar should be a
     * Livewire navigation rather than a full page load. Guarded anyway: if a
     * panel is later switched to a classic layout, wire:navigate would fight the
     * sidebar and the fallback to a normal link is correct there.
     */
    $useSpaNavigation = $panel?->hasTopNavigation() === true
        && $panel?->hasSpaMode() === true;
@endphp

@if ($items !== [])
    <nav
        id="fi-bottom-nav"
        class="fi-bottom-nav"
        aria-label="{{ __('Navigasi Utama') }}"
    >
        @foreach ($items as $item)
            @php
                $url = $item->getUrl();
                $active = $item->isActive();
                $icon = $active && $item->getActiveIcon() ? $item->getActiveIcon() : $item->getIcon();
            @endphp

            <a
                href="{{ $url }}"
                class="fi-bottom-nav-item{{ $active ? ' fi-active' : '' }}"
                @if ($active) aria-current="page" @endif
                @if ($useSpaNavigation) wire:navigate @endif
            >
                <span class="fi-bottom-nav-item-icon">
                    <x-filament::icon
                        :icon="$icon"
                        class="fi-bottom-nav-icon h-6 w-6"
                    />

                    @if (filled($badge = $item->getBadge()))
                        <span class="fi-bottom-nav-badge">{{ $badge }}</span>
                    @endif
                </span>

                <span class="fi-bottom-nav-item-label">{{ $item->getLabel() }}</span>
            </a>
        @endforeach
    </nav>

    <script>
        // Tells Mobile.css to reserve space at the bottom of the page so the
        // last table row / form action is never trapped under the bar.
        (function () {
            function mark() {
                document.documentElement.setAttribute('data-mobile-nav', '1');
            }

            mark();

            document.addEventListener('livewire:navigated', mark);

            if (window.Livewire && window.Livewire.hook) {
                window.Livewire.hook('morph.updated', mark);
            }
        })();
    </script>
@endif
