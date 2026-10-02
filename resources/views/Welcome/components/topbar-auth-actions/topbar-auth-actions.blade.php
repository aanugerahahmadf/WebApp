{{--
    Top-right auth actions for the 'welcome' panel.

    Sits where Filament renders the user menu, minus the avatar: guests get
    Masuk, signed-in visitors get Beranda. Both point at the user panel, which is
    where the auth pages live -- this panel registers none, so it has no login or
    registration URL of its own. URLs come in as props from WelcomePanelProvider
    so the template stays free of route-name knowledge.

    Rendered from the 'panels::global-search.after' hook rather than
    USER_MENU_BEFORE, because Filament only renders the user menu (and with it
    any hook inside it) once `filament()->auth()->check()` passes. Guests are
    the whole reason these buttons exist.

    The theme switcher comes along because Filament keeps it inside the user
    menu, which this panel does not render. It is a dropdown rather than
    Filament's stock three-button strip: next to Masuk the strip crowded the
    topbar, and on a phone it wrapped onto a second row. The stock
    <x-filament-panels::theme-switcher /> has no dropdown variant to reuse, so
    this is hand-rolled from Filament's own dropdown parts, which is what keeps
    the panel, rows and transitions looking native.

    Picking a row assigns `theme`. The wrapper's $watch turns that into the
    'theme-changed' event, and that event is the whole contract with the rest of
    the app -- it writes localStorage.theme and flips the `dark` class. This file
    never touches storage itself, exactly like the stock switcher.

    The trigger's three icons live in <template x-if> rather than x-show: a
    template's contents are not in the document until Alpine clones them, so
    there is no pre-Alpine frame showing every icon at once (this project has no
    global [x-cloak] rule to lean on instead).

    Which row is active is carried by the row tint alone. A tick beside the
    label was tried and cut: at this size the extra glyph became the loudest
    thing in a menu that only holds three entries.

    The active row is tinted with `x-bind:class`, not Blade's `:class`. On a
    Blade component `:class` is a PHP attribute binding, so it would evaluate
    `theme` as a constant and throw; `x-bind:class` rides along as a plain
    attribute for Alpine to bind.

    For the same reason the mode is interpolated as '{{ $theme }}' rather than
    @js($theme): Blade expands attributes on a component into a PHP array, and a
    directive inside one of those values is copied through untouched, so @js
    would reach the browser as the literal text "@js($theme)" and every row
    would throw on click. Plain interpolation is safe regardless -- the only
    possible values are the three keys of $themeOptions above.
--}}
<div class="fi-welcome-auth-actions flex items-center gap-2 lg:gap-3">
    @auth
        <a href="{{ $homeUrl }}"
            class="flex items-center justify-center px-5 h-10 text-sm min-w-10 dark:text-[#EDEDEC] text-[#1b1b18] ring-1 ring-gray-950/10 dark:ring-white/20 hover:bg-gray-50 dark:hover:bg-white/5 rounded-md font-medium transition-all active:scale-95 whitespace-nowrap">
            {{ __('Beranda') }}
        </a>
    @else
        <a href="{{ $loginUrl }}"
            class="flex items-center justify-center px-5 h-10 text-sm min-w-10 dark:text-[#EDEDEC] text-[#1b1b18] ring-1 ring-gray-950/10 dark:ring-white/20 hover:bg-gray-50 dark:hover:bg-white/5 rounded-md font-medium transition-all active:scale-95 whitespace-nowrap">
            {{ __('Sign In') }}
        </a>
    @endauth

    <div class="hidden sm:block">
        @include('Welcome.components.theme-switcher.theme-switcher')
    </div>
</div>
