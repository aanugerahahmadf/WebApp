<?php

namespace App\Livewire\Shared\EditPasswordComponent;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

/**
 * @mixin Component
 */
class EditPasswordComponent extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public ?array $data = [];

    protected static int $sort = 20;

    public function mount(): void
    {
        $this->form->fill();
    }

    public static function getSort(): int
    {
        return static::$sort;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('Perbarui Kata Sandi'))
                    ->aside()
                    ->icon('heroicon-o-lock-closed')
                    ->description(__('Pastikan akun Anda menggunakan kata sandi yang panjang dan acak agar tetap aman.'))
                    ->schema([
                        TextInput::make('current_password')
                            ->label(__('Kata sandi saat ini'))
                            ->password()
                            ->required()
                            ->currentPassword()
                            ->revealable(),
                        TextInput::make('password')
                            ->label(__('Kata sandi baru'))
                            ->password()
                            ->required()
                            ->rule(Password::default())
                            ->autocomplete('new-password')
                            ->dehydrateStateUsing(fn ($state): string => Hash::make($state))
                            ->live(debounce: 500)
                            ->same('passwordConfirmation')
                            ->revealable(),
                        TextInput::make('passwordConfirmation')
                            ->label(__('Konfirmasi kata sandi'))
                            ->password()
                            ->required()
                            ->dehydrated(false)
                            ->revealable(),
                    ]),
            ])
            ->model(Filament::auth()->user())
            ->statePath('data');
    }

    public function updatePassword(): void
    {
        try {
            $data = $this->form->getState();

            $user = Filament::auth()->user();

            $user->update([
                'password' => $data['password'],
            ]);
        } catch (Halt $exception) {
            return;
        }

        if (request()->hasSession() && array_key_exists('password', $data)) {
            request()->session()->put([
                'password_hash_'.Filament::getAuthGuard() => $data['password'],
            ]);
        }

        $this->form->fill();

        Notification::make()
            ->success()
            ->title(__('Kata sandi berhasil diperbarui!'))
            ->send();
    }

    public function render()
    {
        return view('User.livewire.shared.edit-password-form.edit-password-form');
    }
}
