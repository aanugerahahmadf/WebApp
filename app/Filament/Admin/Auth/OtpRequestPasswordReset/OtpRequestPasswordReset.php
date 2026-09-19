<?php

namespace App\Filament\Admin\Auth\OtpRequestPasswordReset;

use App\Models\User\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OtpRequestPasswordReset extends BaseRequestPasswordReset
{
    public function getHeading(): string|Htmlable
    {
        return __('Lupa Kata Sandi?');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('Masukkan alamat email Anda untuk menerima kode verifikasi OTP.');
    }

    public function request(): void
    {
        $data = $this->form->getState();
        $email = $data['email'];

        $user = User::where('email', $email)->first(['*']);

        // Send OTP via email using Cache
        if ($user) {
            $otp = random_int(100000, 999999);
            Cache::put('password_reset_otp_'.$email, $otp, now()->addMinutes(30));

            try {
                Mail::send('User.emails.otp.otp', [
                    'title' => __('Atur Ulang Kata Sandi'),
                    'description' => __('Kami menerima permintaan untuk mengatur ulang kata sandi Anda. Silakan gunakan kode verifikasi di bawah ini untuk melanjutkan. Kode ini berlaku selama 30 menit.'),
                    'otp' => $otp,
                ], function ($message) use ($email): void {
                    $message->to($email)->subject(__('Kode Atur Ulang Kata Sandi'));
                });
            } catch (\Exception $e) {
                Log::error("Gagal kirim email OTP ke $email: ".$e->getMessage());
                // Tetap lanjut biar user nggak stuck di error page, walau email gagal kirim
            }
        }

        Notification::make()
            ->title(__('Jika akun tersedia, kode pengaturan ulang kata sandi telah dikirim.'))
            ->success()
            ->send();

        // Redirect ke halaman Verifikasi OTP dulu
        $this->redirect(VerifyOtp::getUrl([
            'email' => $email,
        ]));
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label(__('Alamat Email'))
            ->email()
            ->required()
            ->autocomplete()
            ->autofocus();
    }

    public function loginAction(): Action
    {
        return parent::loginAction()
            ->label(__('Kembali ke Halaman Masuk'))
            ->url(Filament::getLoginUrl());
    }
}
