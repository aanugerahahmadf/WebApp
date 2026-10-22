<?php

namespace App\Filament\User\Auth\SignIn;

use App\Filament\User\Auth\Auth\Auth;
use App\Filament\User\Auth\Concerns\HasAuthBreadcrumbs;
use App\Models\User\User;
use App\Models\Admin\Admin;
use App\Support\StorefrontUrls\StorefrontUrls;
use Illuminate\Support\Facades\Auth as AuthFacade;
use Illuminate\Support\Facades\Hash;
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
use MarcoGermani87\FilamentCaptcha\Forms\Components\CaptchaField;

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
     * Parent crumb Sign-In adalah halaman auth (`/auth`), bukan Welcome
     * Home.
     *
     * Alur auth bercabang dari satu halaman: `/auth` memegang dua pintu
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
     * catalog), bukan selalu ke beranda.
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

        // Pendaftaran sudah HIDUP lagi (`->registration(SignUp::class)` di
        // UserPanelProvider, slug `signup`), jadi `/signup` bukan lagi
        // URL mati dan tidak lagi masuk daftar "unusable". Kalau tetap
        // diblokir di sini, tombol "kembali" ke halaman pendaftaran yang
        // benar-benar ada akan ditolak tanpa alasan.

        $isUnusable = fn (string $url): bool => str_contains($url, 'livewire')
            || str_contains($url, $this->authPagePath())
            || str_contains($url, 'password-reset')
            || str_contains($url, 'verify-otp')
            || str_contains($url, '/admin');

        // Prioritas: detail / index paket & katalog welcome.
        foreach ($candidates as $url) {
            if ($isUnusable($url)) {
                continue;
            }

            if (str_contains($url, '/flowerdecorationscatalog')) {
                return $url;
            }
        }

        // Cadangan: halaman storefront lain yang valid. Panel welcome pindah
        // ke path '/' (shopee-style), jadi URL halamannya kini /home,
        // /messages, /settings, dst. -- penanda '/welcome' hanya tersisa di
        // bookmark lama. StorefrontUrls mengenali keduanya.
        foreach ($candidates as $url) {
            if ($isUnusable($url)) {
                continue;
            }

            if (StorefrontUrls::isStorefrontUrl($url)) {
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
                        // HANYA SEKALI. View ini memuat "Ingat Saya" +
                        // persetujuan; dulu didaftarkan dua kali (baris
                        // duplikat) sehingga kedua checkbox tampil dobel di
                        // halaman Sign In.
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
        return CaptchaField::make('captcha')
            ->label(__('Kode Keamanan'))
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

        // CAPTCHA divalidasi oleh CaptchaField (validasi bawaan plugin).
        // Rate limit sebelum menyentuh database, sama seperti parent.
        try {
            $this->rateLimit(5);
        } catch (\DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();

        $credentials = $this->getCredentialsFromFormData($data);
        $remember = (bool) ($data['remember'] ?? false);

        $authenticated = false;

        // 1. Jalur normal: akun belanja di tabel users.
        if (AuthFacade::guard('web')->attempt($credentials, $remember)) {
            $authenticated = true;
        } else {
            // 2. Admin tim internal boleh masuk lewat halaman ini juga. Kredensialnya
            //    milik tabel admins (guard admin); kalau cocok, pastikan ada
            //    identitas belanja yang tertaut lalu login sebagai user itu.
            $login = $data['login'];
            $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

            $admin = Admin::query()->where($field, $login)->first();

            if (
                $admin
                && $admin->active_status
                && Hash::check($data['password'], $admin->password)
            ) {
                $user = $admin->ensureStorefrontIdentity($data['password']);

                AuthFacade::guard('web')->login($user, $remember);
                $authenticated = true;
            }
        }

        if (! $authenticated) {
            $this->throwFailureValidationException();
        }

        $user = AuthFacade::guard('web')->user();

        if (
            ($user instanceof \Filament\Models\Contracts\FilamentUser)
            && (! $user->canAccessPanel(\Filament\Facades\Filament::getCurrentPanel()))
        ) {
            AuthFacade::guard('web')->logout();

            $this->throwFailureValidationException();
        }

        session()->regenerate();

        Notification::make()
            ->title(__('Selamat Datang Kembali!'))
            ->body(__('Anda telah berhasil masuk ke sistem Dekorasi Bunga Pernikahan pada :time.', ['time' => now()->format('H:i:s')]))
            ->success()
            ->send();

        return app(\Filament\Http\Responses\Auth\Contracts\LoginResponse::class);
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
