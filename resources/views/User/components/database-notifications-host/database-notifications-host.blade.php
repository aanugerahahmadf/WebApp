{{--
    Modal database notifications (Livewire).
    Trigger disembunyikan via filament-panels::topbar.database-notifications-trigger;
    tombol bell ada di topbar/index.blade.php (semua platform).
--}}
@livewire(\Filament\Livewire\DatabaseNotifications::class, [
    'lazy' => filament()->hasLazyLoadedDatabaseNotifications(),
])
