<?php

namespace App\Filament\User\Auth\SignIn;

use App\Filament\User\Auth\Auth\Auth;
use App\Filament\User\Auth\Concerns\HasAuthBreadcrumbs;
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
use Mews\Captcha\Facades\Captcha;

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
     * Parent crumb Sign-In adalah halaman auth (`/user/auth`), bukan Welcome
     * Home.
     *
     * Alur auth bercabang dari satu halaman: `/user/auth` memegang dua pintu
     * (Sign In + Masuk Dengan Google), dan halaman ini salah satu cabangnya.
     * Jadi di sinilah "Beranda" DIHAPUS -- crumb-nya jadi "Masuk / Sign In",
     * dan klik "Masuk" mengembalikan pengguna ke pemilih pintu, bukan melompat
     * ke storefront dan mencari-cari tombol Sign In lagi.
     *
     * crumb parent memakai Auth::crumbLabel() ("Welcome Back"), bukan
     * getHeading() halaman ini ("Sign In"), supaya crumb di halaman ini tetap
     * "Welcome Back / Sign In" -- nama cabangnya sendiri, bukan nama halaman
     * induknya.
     */
    public function getBreadcrumbs(): array
    {
        return [
            Auth::getUrl() => Auth::crumbLabel(),
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

        $retiredRegisterPaths = $this->retiredRegisterPaths();

        $isUnusable = fn (string $url): bool => str_contains($url, 'livewire')
            || str_contains($url, $this->authPagePath())
            // `/user/signup` & `/user/register`: halaman pendaftaran sudah
            // dihapus, keduanya hanya redirect ke `/user/auth`. Kalau URL lama
            // ini dipakai sebagai tujuan tombol "kembali", pengguna cuma
            // terlempar ke halaman auth lagi.
            || collect($retiredRegisterPaths)->contains(fn (string $path): bool => str_contains($url, $path))
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
                        $this->getCaptchaFormComponent(),
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

    protected function getCaptchaFormComponent(): Component
    {
        return TextInput::make('captcha')
            ->label(__('Kode Keamanan'))
            ->placeholder(__('Masukkan kode dari gambar'))
            ->required()
            ->autocomplete('off')
            ->extraAttributes([
                'class' => 'captcha-input',
                'data-captcha' => 'true',
            ])
            ->suffix(function (): string {
                return '<div class="captcha-image-wrapper">
                    <img src="' . route('captcha.image') . '" alt="CAPTCHA" class="captcha-image" onclick="this.src=\'' . route('captcha.image') . '?reload=' . time() . '\'" title="' . __('Klik untuk refresh') . '">
                    <button type="button" class="captcha-refresh" onclick="document.querySelector(\'.captcha-image\').src=\'' . route('captcha.image') . '?reload=\' + Date.now()" aria-label="' . __('Refresh CAPTCHA') . '">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    </button>
                </div>';
            })
            ->columnSpanFull();
    }

    /**
     * Form ini HANYA menerima Email atau Username.
     *
     * Sebelumnya label menjanjikan "KTP / Passport / SIM / NPWP / Username /
     * Email" sementara form() hanya memproses email + username -- jadi nomor
     * dokumen yang diketik pengguna ditolak tanpa penjelasan. Sekarang label
     * dan perilaku sudah sama, dan cocok dengan app Flutter yang juga memakai
     * satu field berlabel "Email / Username".
     *
     * Ukuran field juga sudah kembali ke bawaan Filament: wrapper class
     * `signin-identifier` (yang menyalakan `white-space: nowrap` di blade view)
     * dihapus bersama style block-nya.
     */
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('login')
            ->label(__('Email / Username'))
            ->required()
            ->autocomplete()
            ->autofocus();
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

    /**
     * Kredensial hanya boleh berisi satu field identitas: email atau username.
     *
     * Field `login` dipetakan ke `email` bila lolos validasi email, selebihnya
     * dianggap `username`. Nomor KTP/Passport/SIM/NPWP tidak lagi jadi alias
     * login, jadi query cukup menyentuh satu kolom ter-index.
     */
    protected function getCredentialsFromFormData(array $data): array
    {
        $login = $data['login'];

        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        return [
            $field => $login,
            'password' => $data['password'],
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

        // Validate CAPTCHA
        $captchaInput = $this->data['captcha'] ?? null;
        if (! $captchaInput || ! Captcha::check($captchaInput)) {
            Notification::make()
                ->title(__('Kode Keamanan Salah'))
                ->body(__('Kode keamanan yang Anda masukkan tidak valid. Silakan coba lagi.'))
                ->danger()
                ->send();
            throw ValidationException::withMessages([
                'data.captcha' => __('Kode keamanan tidak valid.'),
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
