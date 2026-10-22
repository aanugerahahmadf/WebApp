{{--
    Panel-scoped override of filament-panels::components.user-menu for the
    'welcome' panel. Registered in AppServiceProvider by prepending this
    directory to the 'filament-panels' view namespace when the current panel is
    'welcome' -- only this one file lives here, so every other Filament view
    still comes from the package.

    Why the menu is dropped instead of restyled: this panel is the storefront a
    guest lands on, and Filament only renders the user menu for a signed-in
    user, so an avatar trigger would leave the guest and signed-in states
    looking nothing alike. The account control rendered from the
    'panels::global-search.after' hook covers both states instead: a user icon
    whose dropdown holds Sign In To Account for guests, and the same icon
    linking straight to /user/home once signed in.

    Consequence: the account actions this menu carried (profile, sign out) are
    no longer in this panel's topbar. They remain on the user panel, which is
    where the auth and profile pages live.
--}}
{{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_BEFORE) }}
{{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_AFTER) }}
