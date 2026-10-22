<?php

namespace App\Filament\Admin\Auth\OtpRequestPasswordReset;

use App\Filament\Admin\Auth\Concerns\HasAuthBreadcrumbs;
use App\Filament\Admin\Auth\VerifyOtp\VerifyOtp;
use App\Models\Admin\Admin;
use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Minta atur ulang kata sandi admin (via kode OTP ke email).
 *
 * Akun admin hidup di tabel `admins` (guard `admin`) -- BUKAN `users`.
 * Jadiots yang dicari di sini adalah model `Admin`.
 *
 * Balasan ke pengguna SELALU sama, baik akunnya ada atau tidak: kalau
 * halamannyavpgsql berbeda, halaman ini jadi alat untuk mencari email
 * admin yang terdaftar.
 */
class OtpRequestPasswordReset extends BaseRequestPasswordReset
{
    use HasAuthBreadcrumbs;

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

        $admin = Admin::query()->where('email', $email)->first();

        if ($admin && $admin->isActive()) {
            $otp = random_int(100000, 999999);
            Cache::put('admin_password_reset_otp_'.$email, $otp, now()->addMinutes(30));

            try {
                Mail::send('Admin.emails.otp.otp', [
                    'title' => __('Atur Ulang Kata Sandi'),
                    'description' => __('Kami menerima permintaan untuk mengatur ulang kata sandi Anda. Silakan gunakan kode verifikasi di bawah ini untuk melanjutkan. Kode ini berlaku selama 30 menit.'),
                    'otp' => $otp,
                ], function ($message) use ($email): void {
                    $message->to($email)->subject(__('Kode Atur Ulang Kata Sandi'));
                });
            } catch (\Exception $e) {
                Log::error("Gagal kirim email OTP ke $email: ".$e->getMessage());

                // Tetap lanjut: halaman error lebih buruk daripada OTP yang
                // tidak sampai.
            }
        }

        Notification::make()
            ->title(__('Jika akun tersedia, kode pengaturan ulang kata sandi telah dikirim.'))
            ->success()
            ->send();

        $this->redirect(VerifyOtp::getUrl([
            'email' => $email,
        ]));
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        $this->getEmailFormComponent(),
                    ])
                    ->columns(1),
            ]);
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

    protected function getRequestFormAction(): Action
    {
        return Action::make('request')
            ->label(__('Kirim Kode OTP'))
            ->submit('request');
    }

    public function loginAction(): Action
    {
        return parent::loginAction()
            ->label(__('Kembali ke Halaman Sign In'));
    }
}
