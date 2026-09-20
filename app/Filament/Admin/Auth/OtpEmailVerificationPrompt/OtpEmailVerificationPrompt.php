<?php

namespace App\Filament\Admin\Auth\OtpEmailVerificationPrompt;

use App\Models\User\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\EmailVerification\EmailVerificationPrompt;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OtpEmailVerificationPrompt extends EmailVerificationPrompt
{
    use InteractsWithFormActions;

    protected static string $view = 'User.pages.auth.otp-email-verification-prompt.otp-email-verification-prompt';

    public ?array $data = [];

    public function mount(): void
    {
        if (Filament::auth()->check() && $this->getVerifiable()->hasVerifiedEmail()) {
            redirect()->intended(Filament::getUrl());

            return;
        }

        $this->form->fill();

        $userId = Filament::auth()->id();
        if ($userId && ! Cache::has('otp_sent_'.$userId)) {
            $this->sendEmailVerificationNotification($this->getVerifiable());
            Cache::put('otp_sent_'.$userId, true, now()->addMinutes(5));
        }
    }

    protected function sendEmailVerificationNotification(MustVerifyEmail $user): void
    {
        if ($user->hasVerifiedEmail()) {
            return;
        }

        /** @var User $user */
        $otp = random_int(100000, 999999);
        Cache::put('otp_'.$user->id, $otp, now()->addMinutes(5));

        try {
            Mail::send('User.emails.otp.otp', [
                'title' => __('Verifikasi Email'),
                'description' => __('Kami menerima permintaan untuk memverifikasi alamat email Anda. Silakan gunakan kode berikut untuk menyelesaikan proses verifikasi. Kode ini berlaku selama 5 menit.'),
                'otp' => $otp,
            ], function ($message) use ($user): void {
                $message->to($user->email)->subject(__('Kode Verifikasi Email'));
            });
        } catch (\Exception $e) {
            Log::error("Gagal kirim email verifikasi ke {$user->email}: ".$e->getMessage());
        }
    }

    public function verify(): void
    {
        $data = $this->form->getState();
        /** @var User $user */
        $user = $this->getVerifiable();

        $cachedOtp = Cache::get('otp_'.$user->id);

        if ($cachedOtp && (string) $cachedOtp === (string) $data['otp']) {
            $user->markEmailAsVerified();
            Cache::forget('otp_'.$user->id);
            Cache::forget('otp_sent_'.$user->id);

            Notification::make()->title(__('Email berhasil diverifikasi!'))->success()->send();

            $this->redirect(Filament::getUrl());
        } else {
            Notification::make()
                ->title(__('Kode verifikasi tidak valid atau telah kadaluarsa.'))
                ->danger()
                ->send();
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                ViewField::make('otp')
                    ->label(__('Kode Verifikasi'))
                    ->view('User.auth.otp-field.otp-field')
                    ->required(),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('verify')
                ->label(__('Verifikasi'))
                ->submit('verify'),
        ];
    }

    protected function hasFullWidthFormActions(): bool
    {
        return false;
    }

    public function getHeading(): string|Htmlable
    {
        return __('Verifikasi Email Anda');
    }

    public function getSubheading(): string
    {
        return __('Silakan masukkan 6 digit kode verifikasi yang telah kami kirimkan ke alamat email Anda.');
    }

    public function resendNotificationAction(): Action
    {
        return Action::make('resendNotification')
            ->label(__('Kirim ulang kode'))
            ->color('gray')
            ->action(function (): void {
                $this->sendEmailVerificationNotification($this->getVerifiable());

                Notification::make()
                    ->title(__('Kode verifikasi baru telah dikirim.'))
                    ->success()
                    ->send();
            });
    }

    public function logoutAndReturnToLogin(): void
    {
        Filament::auth()->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        $this->redirect(Filament::getLoginUrl());
    }

    public function loginAction(): Action
    {
        return parent::loginAction()
            ->label(__('Sudah punya akun? Masuk'))
            ->url(Filament::getLoginUrl());
    }
}
