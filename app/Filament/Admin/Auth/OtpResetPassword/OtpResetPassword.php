<?php

namespace App\Filament\Admin\Auth\OtpResetPassword;

use App\Filament\Admin\Auth\VerifyOtp\VerifyOtp;
use App\Models\Admin\Admin;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Http\Responses\Auth\Contracts\PasswordResetResponse;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\PasswordReset\ResetPassword as BaseResetPassword;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cache;

/**
 * Atur ulang kata sandi admin (setelah OTP terverifikasi).
 *
 * Akun admin hidup di tabel `admins` -- bukan `users`.
 */
class OtpResetPassword extends BaseResetPassword
{

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

        // OTP harus sudah diverifikasi di halaman sebelumnya.
        if (! Cache::get('admin_otp_verified_for_'.$email)) {
            Notification::make()
                ->title(__('Sesi verifikasi habis. Silakan verifikasi ulang OTP Anda.'))
                ->danger()
                ->send();

            $this->redirect(VerifyOtp::getUrl(['email' => $email]));

            return null;
        }

        $admin = Admin::query()->where('email', $email)->first();

        if (! $admin) {
            Notification::make()
                ->title(__('Admin tidak ditemukan.'))
                ->danger()
                ->send();

            return null;
        }

        // Password MENTAH -- model Admin punya cast 'password' => 'hashed'
        // yang meng-hash otomatis saat disimpan. JANGAN Hash::make di sini.
        $admin->password = $data['password'];
        $admin->save();

        Cache::forget('admin_otp_verified_for_'.$email);
        Cache::forget('admin_password_reset_otp_'.$email);

        Notification::make()
            ->title(__('Kata sandi berhasil diatur ulang.'))
            ->success()
            ->send();

        $this->redirect(Filament::getLoginUrl());

        return null;
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
