{{--
    Mobile topbar notification bell.
    Rendered via GLOBAL_SEARCH_AFTER hook on mobile only.
    Replaces the language switcher that appears on web/desktop.
--}}
@if (filament()->auth()->check() && filament()->hasDatabaseNotifications())
    @livewire(Filament\Livewire\DatabaseNotifications::class, [
        'lazy' => filament()->hasLazyLoadedDatabaseNotifications(),
    ])
@endif
