<?php

namespace App\Filament\User\Auth\SignIn;

use App\Filament\User\Auth\Concerns\HasAuthBreadcrumbs;
use App\Filament\Welcome\Pages\Home\Home;
use App\Models\User\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;

class SignIn extends BaseLogin
{
    use HasAuthBreadcrumbs;

    public function getView(): string
    {
        return 'User.auth.sign-in.sign-in';
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->label(__('Kata Sandi'));
    }

    protected function getRememberFormComponent(): Component
    {
        return parent::getRememberFormComponent()
            ->label(__('Ingat Saya'))
            ->required();
    }

    public function getHeading(): string|Htmlable
    {
        return __('Sign In');
    }

    /**
     * Judul halaman ini juga jadi `<title>` di tab browser. Base class Filament
     * mengembalikan `filament-panels::pages/auth/login.title` ("Login"), jadi
     * tanpa override ini heading kartu sudah "Sign In" tapi tabnya tetap
     * "Login". Ikut getHeading() supaya keduanya tidak bisa berbeda.
     */
    public function getTitle(): string|Htmlable
    {
        return $this->getHeading();
    }

    /**
     * Parent crumb Sign-In selalu Welcome Home, bukan halaman asal klik.
     *
     * Sign In adalah pintu masuk auth dari storefront publik yang bisa dibuka
     * semua orang, jadi parent crumb yang "mengikuti asal klik" dari trait
     * HasAuthBreadcrumbs tidak pernah informatif di sini: kandidat /welcome/*
     * apa pun hanya menghasilkan label generik "Kembali", dan kandidat lain
     * ditolak. "Beranda" selalu benar, dan kliknya mengembalikan user ke
     * storefront dari manapun mereka datang.
     *
     * Halaman auth lain tetap memakai trait apa adanya -- parent-nya memang
     * bermakna di sana (Sign Up -> Sign In, OTP -> Lupa Kata Sandi, dst).
     */
    public function getBreadcrumbs(): array
    {
        return [
            Home::getUrl(panel: 'welcome') => __('Beranda'),
            $this->getAuthBreadcrumbsCurrentLabel(),
        ];
    }

    /**
     * Tujuan tombol back di halaman login: kembali ke halaman yang
     * diklik pengguna sebelum diarahkan ke login (detail package /
     * product), bukan selalu ke beranda.
     *
     * Sumber: `url.intended` yang disimpan saat guest membuka modal
     * Add to Cart (atau aksi guest lain), plus `url()->previous()`
     * sebagai sinyal terbaru. Kandidat yang menunjuk ke livewire atau
     * halaman auth/admin dilewati; fallback ke beranda welcome.
     */
    public function getBackUrl(): string
    {
        $fallback = route('filament.welcome.pages.home');

        $candidates = [];

        $previous = url()->previous();
        if (is_string($previous) && $previous !== '') {
            $candidates[] = $previous;
        }

        $intended = session()->get('url.intended');
        if (is_string($intended) && $intended !== '') {
            $candidates[] = $intended;
        }

        $isUnusable = fn (string $url): bool => str_contains($url, 'livewire')
            || str_contains($url, $this->authPagePath())
            || str_contains($url, $this->registrationPagePath())
            || str_contains($url, 'password-reset')
            || str_contains($url, 'verify-otp')
            || str_contains($url, '/admin');

        // Prioritas: detail / index paket & produk welcome.
        foreach ($candidates as $url) {
            if ($isUnusable($url)) {
                continue;
            }

            if (str_contains($url, '/welcome/flowerdecorationspackagecatalog') || str_contains($url, '/welcome/flowerdecorationscatalog')) {
                return $url;
            }
        }

        // Cadangan: halaman welcome lain yang valid.
        foreach ($candidates as $url) {
            if ($isUnusable($url)) {
                continue;
            }

            if (str_contains($url, '/welcome')) {
                return $url;
            }
        }

        return $fallback;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        $this->getEmailFormComponent(),
                        $this->getPasswordFormComponent(),
                        View::make('User.social-buttons.agreement-checkboxes.agreement-checkboxes'),
                        View::make('User.social-buttons.auth-buttons.auth-buttons')
                            ->viewData(['authMode' => 'login']),
                    ])
                    ->columns(1),
                Hidden::make('agreement'),
                Hidden::make('remember'),
            ])
            ->statePath('data');
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('login')
            ->label(__('KTP / Passport / SIM / NPWP / Username / Email'))
            ->required()
            ->autocomplete()
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1])
            ->extraFieldWrapperAttributes(['class' => 'signin-identifier']);
    }

    public function registerAction(): Action
    {
        return Action::make('register')
            ->label('')
            ->hidden();
    }

    public function loginAction(): Action
    {
        return parent::loginAction()
            ->hidden();
    }

    public function passwordResetAction(): Action
    {
        return parent::passwordResetAction()
            ->label(__('Lupa Kata Sandi?'));
    }

    protected function getAuthenticateFormAction(): Action
    {
        return parent::getAuthenticateFormAction()
            ->label(__('Sign In'));
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        $login = $data['login'];
        $password = $data['password'];

        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $user = User::where($field, $login)
            ->orWhere('ktp_number', $login)
            ->orWhere('passport_number', $login)
            ->orWhere('sim_number', $login)
            ->orWhere('npwp_number', $login)
            ->first();
        if ($user) {
            if ($user->email === $login) {
                $field = 'email';
            } elseif ($user->ktp_number === $login) {
                $field = 'ktp_number';
            } elseif ($user->passport_number === $login) {
                $field = 'passport_number';
            } elseif ($user->sim_number === $login) {
                $field = 'sim_number';
            } elseif ($user->npwp_number === $login) {
                $field = 'npwp_number';
            } else {
                $field = 'username';
            }
        }

        return [
            $field => $login,
            'password' => $password,
        ];
    }

    public function authenticate(): ?LoginResponse
    {
        // Enforce mandatory checkboxes (Agreement & Remember)
        if (! ($this->data['agreement'] ?? false)) {
            Notification::make()
                ->title(__('Perhatian'))
                ->body(__('Anda harus menyetujui syarat dan ketentuan untuk melanjutkan.'))
                ->warning()
                ->send();
            throw ValidationException::withMessages([
                'data.agreement' => __('Anda harus menyetujui syarat dan ketentuan untuk melanjutkan.'),
            ]);
        }

        if (! ($this->data['remember'] ?? false)) {
            Notification::make()
                ->title(__('Perhatian'))
                ->body(__('Anda harus mencentang Ingat Saya untuk melanjutkan.'))
                ->warning()
                ->send();
            throw ValidationException::withMessages([
                'data.remember' => __('Anda harus mencentang Ingat Saya untuk melanjutkan.'),
            ]);
        }

        $response = parent::authenticate();

        if ($response) {
            Notification::make()
                ->title(__('Selamat Datang Kembali!'))
                ->body(__('Anda telah berhasil masuk ke sistem Weeding Decorasi Bunga pada :time.', ['time' => now()->format('H:i:s')]))
                ->success()
                ->send();
        }

        return $response;
    }

    protected function throwFailureValidationException(): never
    {
        Notification::make()
            ->title(__('Otentikasi Gagal'))
            ->body(__('Kami tidak dapat memverifikasi kredensial Anda. Silakan periksa email/username dan kata sandi Anda, lalu coba lagi.'))
            ->danger()
            ->send();

        throw ValidationException::withMessages([
            'data.login' => __('filament-panels::pages/auth/login.messages.failed'),
        ]);
    }
}
