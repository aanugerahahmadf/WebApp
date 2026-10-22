<?php

namespace App\Filament\Admin\Auth\OtpEmailVerificationPrompt;

use App\Filament\Admin\Auth\Concerns\HasAuthBreadcrumbs;
use App\Models\Admin\Admin;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\View;
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

/**
 * Verifikasi email admin lewat kode OTP.
 *
 * Panel admin memakai guard `admin`, jadi yang diverifikasi adalah model
 * `Admin` -- bukan `User`.
 */
class OtpEmailVerificationPrompt extends EmailVerificationPrompt
{
    use HasAuthBreadcrumbs;
    use InteractsWithFormActions;

    public ?array $data = [];

    public int $resendCooldown = 0;

    public function mount(): void
    {
        if (Filament::auth()->check() && $this->getVerifiable()->hasVerifiedEmail()) {
            $this->redirect(Filament::getUrl());

            return;
        }

        $this->form->fill();

        $userId = Filament::auth()->id();
        $lastSent = Cache::get('admin_otp_last_sent_'.$userId);

        if ($lastSent) {
            $diff = now()->timestamp - $lastSent;

            if ($diff < 300) {
                $this->resendCooldown = 300 - $diff;
            }
        }

        if ($userId && ! Cache::has('admin_otp_sent_'.$userId)) {
            $this->sendEmailVerificationNotification($this->getVerifiable());
            Cache::put('admin_otp_sent_'.$userId, true, now()->addMinutes(5));
            Cache::put('admin_otp_last_sent_'.$userId, now()->timestamp, now()->addMinutes(10));
            $this->resendCooldown = 300;
        }
    }

    protected function sendEmailVerificationNotification(MustVerifyEmail $user): void
    {
        if ($user->hasVerifiedEmail()) {
            return;
        }

        /** @var Admin $user */
        $otp = random_int(100000, 999999);
        Cache::put('admin_otp_'.$user->id, $otp, now()->addMinutes(5));

        try {
            Mail::send('Admin.emails.otp.otp', [
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
        /** @var Admin $user */
        $user = $this->getVerifiable();

        $cachedOtp = Cache::get('admin_otp_'.$user->id);

        if ($cachedOtp && (string) $cachedOtp === (string) $data['otp']) {
            $user->markEmailAsVerified();
            Cache::forget('admin_otp_'.$user->id);
            Cache::forget('admin_otp_sent_'.$user->id);

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
                Section::make()
                    ->schema([
                        ViewField::make('otp')
                            ->label(__('Kode Verifikasi'))
                            ->view('Admin.auth.otp-field.otp-field')
                            ->required(),
                        View::make('Admin.pages.auth.otp-email-verification-prompt.actions'),
                    ])
                    ->columns(1),
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
            ->label(__('Kirim Ulang Kode'))
            ->color('gray')
            ->action(function (): void {
                $userId = Filament::auth()->id();

                $lastSent = Cache::get('admin_otp_last_sent_'.$userId);

                if ($lastSent && (now()->timestamp - $lastSent) < 300) {
                    Notification::make()
                        ->title(__('Harap tunggu sebelum meminta kode baru.'))
                        ->danger()
                        ->send();

                    return;
                }

                $this->sendEmailVerificationNotification($this->getVerifiable());
                Cache::put('admin_otp_last_sent_'.$userId, now()->timestamp, now()->addMinutes(10));
                $this->resendCooldown = 300;

                Notification::make()
                    ->title(__('Kode verifikasi baru telah dikirim.'))
                    ->success()
                    ->send();

                $this->dispatch('otp-resent');
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
            ->label(__('Kembali ke Halaman Sign In'));
    }
}
