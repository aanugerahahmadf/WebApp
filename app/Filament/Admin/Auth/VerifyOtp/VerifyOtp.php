<?php

namespace App\Filament\Admin\Auth\VerifyOtp;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\SimplePage;
use Filament\Panel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

class VerifyOtp extends SimplePage
{
    use InteractsWithFormActions;

    protected static string $view = 'User.auth.verify-otp.verify-otp';

    protected static string $layout = 'filament-panels::components.layout.simple';

    public $email = '';

    public $otp = '';

    public static function getUrl(array $parameters = []): string
    {
        return route('filament.admin.auth.verify-otp', $parameters);
    }

    public function mount(): void
    {
        $this->email = request()->query('email', '');

        if (blank($this->email)) {
            $this->redirect(route('filament.admin.auth.password-reset.request'));
        }

        $this->form->fill([
            'email' => $this->email,
        ]);
    }

    public function verify(): void
    {
        $data = $this->form->getState();
        $email = $data['email'];
        $otp = $data['otp'];

        $cachedOtp = Cache::get('password_reset_otp_'.$email);

        if ($cachedOtp && (string) $cachedOtp === (string) $otp) {
            // Tandai bahwa OTP sudah valid untuk email ini (berlaku 30 menit)
            Cache::put('otp_verified_for_'.$email, true, now()->addMinutes(30));

            Notification::make()
                ->title(__('Kode OTP valid! Silakan atur kata sandi baru.'))
                ->success()
                ->send();

            $this->redirect(URL::temporarySignedRoute(
                'filament.admin.auth.password-reset.reset',
                now()->addMinutes(30),
                [
                    'email' => $email,
                    'token' => 'otp',
                ]
            ));
        } else {
            Notification::make()
                ->title(__('Kode OTP tidak valid atau telah kadaluarsa.'))
                ->danger()
                ->send();
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('email')
                    ->label(__('Alamat Email'))
                    ->disabled()
                    ->dehydrated(),
                ViewField::make('otp')
                    ->label(__('6 Digit Kode OTP'))
                    ->view('User.auth.otp-field.otp-field')
                    ->required(),
            ]);
    }

    public function getHeading(): string
    {
        return __('Verifikasi Kode OTP');
    }

    public function getSubheading(): string
    {
        return __('Masukkan 6 digit kode yang kami kirim ke email Anda.');
    }

    public function getFormActions(): array
    {
        return [
            $this->getVerifyFormAction(),
        ];
    }

    protected function getVerifyFormAction(): Action
    {
        return Action::make('verify')
            ->label(__('Verifikasi Kode'))
            ->submit('verify');
    }

    public function loginAction(): Action
    {
        return Action::make('login')
            ->label(__('Sudah punya akun? Masuk'))
            ->url(Filament::getLoginUrl())
            ->color('gray');
    }

    protected function hasFullWidthFormActions(): bool
    {
        return false;
    }

    public static function registerRoutes(Panel $panel): void
    {
        Route::get('/password-reset/verify', static::class)
            ->name('auth.verify-otp')
            ->middleware(['web']);
    }
}
