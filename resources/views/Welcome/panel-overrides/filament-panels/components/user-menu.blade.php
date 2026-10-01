{{--
    Panel-scoped override of filament-panels::components.user-menu for the
    'welcome' panel. Registered in AppServiceProvider by prepending this
    directory to the 'filament-panels' view namespace when the current panel is
    'welcome' -- only this one file lives here, so every other Filament view
    still comes from the package.

    Why the menu is dropped instead of restyled: this panel is the storefront a
    guest lands on, and a guest never gets a user menu at all, so an avatar
    trigger would leave the signed-in and signed-out states looking nothing
    alike. The Masuk / Beranda buttons rendered from the
    'panels::global-search.after' hook cover both states instead.

    Consequence: the account actions this menu carried (profile, sign out) are
    no longer in this panel's topbar. They remain on the user panel, which is
    where the auth and profile pages live.
--}}
{{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_BEFORE) }}
{{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_AFTER) }}
