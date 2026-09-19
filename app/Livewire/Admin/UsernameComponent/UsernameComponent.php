<?php

namespace App\Livewire\Admin\UsernameComponent;

use App\Models\User\User;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

/**
 * @mixin Component
 */
class UsernameComponent extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    protected static int $sort = 2;

    public static function getSort(): int
    {
        return static::$sort;
    }

    public function mount(): void
    {
        $user = Auth::user();
        if ($user) {
            $this->form->fill([
                'username' => $user->username,
            ]);
        }
    }

    public function form(Form $form): Form
    {
        $user = Auth::user();

        return $form
            ->statePath('data')
            ->schema([
                Section::make(__('Username'))
                    ->aside()
                    ->icon('heroicon-o-identification')
                    ->description(__('Perbarui username Anda'))
                    ->schema([
                        TextInput::make('username')
                            ->label(__('Username'))
                            ->placeholder(__('Masukkan username Anda'))
                            ->required()
                            ->minLength(3)
                            ->maxLength(255)
                            ->unique(table: 'users', column: 'username', ignorable: $user)
                            ->autocomplete('username')
                            ->columnSpan('full'),
                    ]),
            ]);
    }

    public function save(): void
    {
        try {
            $data = $this->form->getState();

            Log::info('UsernameComponent: Saving username', ['data' => $data]);

            /** @var User $user */
            $user = Auth::user();

            if (! $user) {
                throw new \Exception('User not authenticated');
            }

            $user->username = $data['username'];
            $user->save();

            // Refresh user instance to get updated data
            $user = $user->fresh();

            Log::info('UsernameComponent: Username saved successfully', ['user_id' => $user->id, 'username' => $user->username]);

            // Show success notification
            Notification::make()
                ->title(__('Username berhasil diperbarui!'))
                ->success()
                ->send();

            $this->dispatch('profile-updated');
        } catch (\Exception $e) {
            Log::error('UsernameComponent: Error saving username', ['error' => $e->getMessage()]);

            // Show error notification
            Notification::make()
                ->title(__('Gagal memperbarui username'))
                ->body(__('Terjadi kesalahan saat memperbarui username Anda. Silakan coba lagi.'))
                ->danger()
                ->send();

            throw $e;
        }
    }

    public function render(): View
    {
        return view('Admin.livewire.username-component.username-component');
    }
}
