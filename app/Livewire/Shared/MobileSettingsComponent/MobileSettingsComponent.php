<?php

namespace App\Livewire\Shared\MobileSettingsComponent;

use App\Models\UserLanguage\UserLanguage;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Joaopaulolndev\FilamentEditProfile\Concerns\HasSort;
use Livewire\Component;

/**
 * @mixin Component
 *
 * @property string $selectedLocale
 */
class MobileSettingsComponent extends Component implements HasForms
{
    use HasSort;
    use InteractsWithForms;

    public $selectedLocale;

    protected static int $sort = 25;

    public static function getSort(): int
    {
        return static::$sort;
    }

    public function mount(): void
    {
        $this->selectedLocale = app()->getLocale();
    }

    public function changeLanguage($locale): void
    {
        $locals = config('filament-language-switcher.locals', []);

        if (array_key_exists($locale, $locals)) {
            // 1. Force update Session
            session()->put('locale', $locale);
            app()->setLocale($locale);

            // 2. Find authenticated user across all potential guards
            $user = null;
            $guards = ['web', 'filament', 'admin', 'api', 'mobile'];
            foreach ($guards as $guard) {
                if (Auth::guard($guard)->check()) {
                    $user = Auth::guard($guard)->user();
                    break;
                }
            }

            // 3. Persist to Database if user found
            if ($user) {
                // Use the same robust updateOrCreate logic
                UserLanguage::updateOrCreate(
                    ['model_id' => (string) $user->id, 'model_type' => 'App\Models\User\User'],
                    ['lang' => $locale]
                );

                // Nuclear purge of user-specific caches
                cache()->forget("user_lang_{$user->id}");
                cache()->forget("active_trans_map_{$locale}");
            }

            $this->selectedLocale = $locale;
            $this->dispatch('locale-changed');

            Notification::make()
                ->title(__('Bahasa Berhasil Diubah'))
                ->success()
                ->send();

        }
    }

    public function render(): View
    {
        return view('User.livewire.shared.mobile-settings-component.mobile-settings-component');
    }
}
