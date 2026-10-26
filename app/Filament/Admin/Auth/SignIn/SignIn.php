<?php

namespace App\Filament\Admin\Auth\SignIn;

use App\Models\Admin\Admin;
use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Halaman Sign-In panel admin.
 *
 * Guard panel admin adalah `admin` (tabel `admins`), jadi
 * `Filament::auth()` di sini sudah menunjuk ke guard itu -- tidak ada
 * jalur "coba user dulu, lalu admin" seperti di panel storefront.
 *
 * Yang ditambahkan di sini:
 *
 *   - tidak ada pendaftaran (akun admin dibuat dari luar),
 *   - akun nonaktif DITOLAK dengan pesan jelas, bukan diam-diam,
 *   - crumb dan judul ikut bahasa.
 */
class SignIn extends BaseLogin
{

    public function getHeading(): string|Htmlable
    {
        return __('Sign In');
    }

    /**
     * Judul halaman ini juga jadi `<title>` di tab browser. Base class
     * Filament mengembalikan "Login", jadi tanpa override ini heading
     * kartunya "Sign In" tapi tabnya tetap "Login".
     */
    public function getTitle(): string|Htmlable
    {
        return $this->getHeading();
    }

        public function getBreadcrumbs(): array
    {
        return [];
    }

    public function registerAction(): Action
    {
        return Action::make('register')
            ->label('')
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
     * Field identitas: EMAIL **atau** USERNAME.
     *
     * Tabel `admins` punya KEDUA kolom: `username` (unik) dan `email`
     * (unik). Dulu form ini hanya punya satu field berlabel "Email address"
     * dengan validasi email -- jadi admin yang memakai username ("superadmin")
     * tidak bisa masuk sama sekali: nomornya ditolak sebagai "bukan email"
     * sebelum kredensial dicek.
     *
     * Sekarang fieldnya menerima keduanya, sama seperti:
     *   - halaman Sign-In storefront (`App\Filament\User\Auth\SignIn\SignIn`),
     *   - aplikasi Flutter (satu field berlabel "Email / Username").
     *
     * Validasi `->email()` BAWAAN Filament sengaja TIDAK dipakai: username
     * tidak boleh gagal validasi format. Pemeriksaan format pindah ke saat
     * PENYEARIAN kredensial (lihat {@see static::findAdminByIdentifier()}).
     */
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('login')
            ->label(__('Email / Username'))
            ->required()
            ->autocomplete()
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    /**
     * Kredensial: email ATAU username + kata sandi, terhadap guard `admin`.
     *
     * Field `login` dipetakan ke `email` bila lolos validasi email, selebihnya
     * dianggap `username`.
     */
    protected function getCredentialsFromFormData(array $data): array
    {
        $login = $data['login'] ?? null;

        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        return [
            $field => $login,
            'password' => $data['password'] ?? null,
        ];
    }

    /**
     * Cari admin berdasarkan email ATAU username.
     *
     * Dua kolom, dua peluang: kolom yang "paling mungkin" dicek dulu (email
     * kalau input-nya memang berbentuk email, selain itu username), lalu kolom
     * satu lagi sebagai cadangan -- username yang mengandung "@" tetap harus
     * bisa masuk.
     */
    protected function findAdminByIdentifier(mixed $identifier): ?Admin
    {
        if (! is_string($identifier)) {
            return null;
        }

        $identifier = trim($identifier);

        if ($identifier === '') {
            return null;
        }

        $preferred = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $fallback = $preferred === 'email' ? 'username' : 'email';

        return Admin::query()
            ->where($preferred, $identifier)
            ->first()
            ?? Admin::query()
                ->where($fallback, $identifier)
                ->first();
    }

    public function authenticate(): ?LoginResponse
    {
        $data = $this->form->getState();

        $login = $data['login'] ?? null;
        $password = $data['password'] ?? null;
        $remember = (bool) ($data['remember'] ?? false);

        $admin = $this->findAdminByIdentifier($login);

        // Akun nonaktif: tolak dengan pesan yang JELAS. Kalau tidak, ia
        // terlihat seperti "kata sandi salah" -- padahal kata sandinya
        // benar dan adminsnya hanya dinonaktifkan.
        if ($admin && ! $admin->isActive()) {
            $this->throwFailureValidationException(
                __('Akun ini dinonaktifkan. Hubungi administrator tim.')
            );
        }

        if (! $admin || ! Hash::check((string) $password, $admin->getAuthPassword())) {
            $this->throwFailureValidationException();
        }

        \Illuminate\Support\Facades\Auth::guard('admin')->login($admin, $remember);

        session()->regenerate();

        // PENTING: response login harus milik PANEL ADMIN.
        //
        // `app(LoginResponse::class)` mengambil binding dari CONTAINER --
        // dan binding itu milik panel Welcome (storefront), jadi admin yang
        // baru sign in akan mendarat di /home, bukan /admin/home.
        //
        // Class konkret Filament (bukan contract-nya) selalu mengembalikan
        // `Filament::getUrl()` dari panel yang SEDANG dilayani.
        return app(\Filament\Http\Responses\Auth\LoginResponse::class);
    }

    protected function throwFailureValidationException(?string $message = null): never
    {
        $message ??= __('Email/Username atau kata sandi tidak cocok.');

        throw ValidationException::withMessages([
            'data.login' => $message,
        ]);
    }
}
