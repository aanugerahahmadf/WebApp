<?php

namespace App\Livewire\User\NotificationSettingsComponent;

use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Contracts\View\View;
use Joaopaulolndev\FilamentEditProfile\Concerns\HasSort;
use Livewire\Component;

/**
 * @mixin Component
 */
class NotificationSettingsComponent extends Component implements HasForms
{
    use HasSort;
    use InteractsWithForms;

    protected static int $sort = 26;

    public static function getSort(): int
    {
        return static::$sort;
    }

    public function render(): View
    {
        return view('User.livewire.notification-settings-component.notification-settings-component');
    }
}
