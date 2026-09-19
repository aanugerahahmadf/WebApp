<?php

namespace App\Filament\User\Auth\OtpResetPassword;

use App\Models\User\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Http\Responses\Auth\Contracts\PasswordResetResponse;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\PasswordReset\ResetPassword as BaseResetPassword;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class OtpResetPassword extends BaseResetPassword
{
    public function getView(): string
    {
        return 'User.auth.otp-reset-password.otp-reset-password';
    }

    public function getHeading(): string|Htmlable
    {
        return __('Atur Ulang Kata Sandi');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('Silakan buat kata sandi baru untuk akun Anda.');
    }

    public function mount(?string $email = null, ?string $token = null): void
    {
        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());

            return;
        }

        $this->email = request()->query('email', '');

        $this->form->fill([
            'email' => $this->email,
        ]);

        $this->token = 'otp';
    }

    public function resetPassword(): ?PasswordResetResponse
    {
        $data = $this->form->getState();
        $email = $data['email'] ?? $this->email;

        // Cek apakah OTP sudah diverifikasi di halaman sebelumnya
        if (! Cache::get('otp_verified_for_'.$email)) {
            Notification::make()
                ->title(__('Sesi verifikasi habis. Silakan verifikasi ulang OTP Anda.'))
                ->danger()
                ->send();

            $this->redirect(VerifyOtp::getUrl(['email' => $email]));

            return null;
        }

        $user = User::where('email', $email)->first(['*']);

        if ($user) {
            $user->password = Hash::make($data['password']);
            $user->save();

            Cache::forget('otp_verified_for_'.$email);
            Cache::forget('password_reset_otp_'.$email);

            Notification::make()
                ->title(__('Kata sandi berhasil diatur ulang.'))
                ->success()
                ->send();

            $this->redirect(Filament::getLoginUrl());

            return null;
        } else {
            Notification::make()
                ->title(__('Pengguna tidak ditemukan.'))
                ->danger()
                ->send();

            return null;
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        $this->getEmailFormComponent(),
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
                        View::make('User.auth.otp-reset-password.actions'),
                    ])
                    ->columns(1),
            ]);
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label(__('Alamat Email'))
            ->disabled()
            ->dehydrated();
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->label(__('Kata Sandi Baru'));
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        return parent::getPasswordConfirmationFormComponent()
            ->label(__('Konfirmasi Kata Sandi Baru'));
    }

    public function resetPasswordAction(): Action
    {
        return parent::resetPasswordAction()
            ->label(__('Atur Ulang Kata Sandi'));
    }
}
