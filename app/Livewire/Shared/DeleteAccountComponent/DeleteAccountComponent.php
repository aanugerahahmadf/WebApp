<?php

namespace App\Livewire\Shared\DeleteAccountComponent;

use App\Models\User\User;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

/**
 * @mixin Component
 */
class DeleteAccountComponent extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static int $sort = 60;

    public static function getSort(): int
    {
        return static::$sort;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('Hapus Akun'))
                    ->description(__('Hapus akun Anda secara permanen.'))
                    ->aside()
                    ->icon('heroicon-o-trash')
                    ->schema([
                        Forms\Components\ViewField::make('deleteAccount')
                            ->label(__('Hapus Akun'))
                            ->hiddenLabel()
                            ->view('User.forms.components.delete-account-description.delete-account-description'),
                        Actions::make([
                            Actions\Action::make('deleteAccount')
                                ->label(__('Hapus Akun'))
                                ->icon('heroicon-m-trash')
                                ->color('danger')
                                ->requiresConfirmation()
                                ->modalHeading(__('Hapus Akun'))
                                ->modalDescription(__('Apakah Anda yakin ingin menghapus akun Anda? Setelah akun Anda dihapus, semua sumber daya dan datanya akan dihapus secara permanen. Harap masukkan kata sandi Anda untuk mengonfirmasi bahwa Anda ingin menghapus akun Anda secara permanen.'))
                                ->modalSubmitActionLabel(__('Ya, hapus'))
                                ->form([
                                    Forms\Components\TextInput::make('password')
                                        ->password()
                                        ->revealable()
                                        ->label(__('Kata Sandi'))
                                        ->required(),
                                ])
                                ->action(function (array $data) {
                                    /** @var User $user */
                                    $user = Auth::user();

                                    if (! $user) {
                                        return;
                                    }

                                    if (! Hash::check($data['password'], $user->password)) {
                                        $this->sendErrorDeleteAccount(__('Kata sandi yang diberikan tidak cocok dengan catatan kami.'));

                                        return;
                                    }

                                    $user->delete();

                                    Auth::logout();

                                    return redirect('/');
                                }),
                        ])->alignment(Alignment::End),
                    ]),
            ]);
    }

    public function sendErrorDeleteAccount(string $message): void
    {
        Notification::make()
            ->danger()
            ->title($message)
            ->send();
    }

    public function render()
    {
        return view('User.livewire.shared.delete-account-form.delete-account-form');
    }
}
