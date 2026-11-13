<?php

namespace App\Filament\User\Auth\TwoFactorAuth;

use App\Filament\Shared\Pages\TwoFactorAuthenticatorPage\TwoFactorAuthenticatorPage;
use App\Filament\Welcome\Pages\Home\Home;
use Filament\Facades\Filament;
use Illuminate\Contracts\Support\Htmlable;
use MixCode\FilamentMulti2fa\Enums\TwoFactorAuthType;

/**
 * "Strengthen your account?" -- halaman setup 2FA di tengah alur auth.
 *
 * Google Gateway Belum Terdaftar:
 *   1. Menjawab Iya: Verify OTP -> Complete Profile -> Security Question (Iya)
 *        -> Two Factor Auth (Berhasil) -> Auth.php -> Home
 *   2. Menjawab Tidak: Verify OTP -> Complete Profile
 *        -> Security Question (Tidak) -> Home
 *
 * Alurnya sama persis dengan TwoFactorySetup di Pengaturan: pilih tipe (Email /
 * Authenticator App / None), verifikasi OTP atau TOTP, simpan, plus daftar
 * perangkat tepercaya. Semuanya diwarisi dari mix-code/filament-multi-2fa lewat
 * TwoFactorAuthenticatorPage, jadi keduanya tidak mungkin terlihat berbeda.
 *
 * Yang berbeda hanya dua hal, dan keduanya memang harus:
 *
 *   - URL: dua-factor-auth, bukan di subtree Keamanan.
 *
 *   - Setelah 2FA aktif (Menjawab Iya): tetap login, redirect ke SignIn.php,
 *     lalu SignIn::mount() memantul ke Home. Tanpa logout, tanpa OTP kedua.
 *
 * Opsi "None" tetap tersedia di sini seperti di halaman Pengaturan: pengguna
 * boleh memutuskan tidak memakai 2FA, dan halaman ini tidak memaksanya.
 */
class TwoFactorAuth extends TwoFactorAuthenticatorPage
{
    protected ?string $maxWidth = 'md';

    public static function getSlug(): string
    {
        return 'two-factor-auth';
    }

    public function mount(): void
    {
        $user = auth()->user();

        // Akun admin/superadmin selalu langsung ke user home.
        if ($user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['super_admin', 'admin'])) {
            $this->redirect(Home::getUrl());

            return;
        }

        // Sudah aktif: tidak perlu setup lagi, dan halaman paket akan
        // menolak tipe yang sudah dipakai ("You have already verified your
        // account with this method before").
        if ($user?->hasSetupTwoFactor()) {
            $this->redirect(Home::getUrl());

            return;
        }

        parent::mount();
    }

    public function getHeading(): string|Htmlable
    {
        return __('Aktifkan Aplikasi Autentikasi');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('Pindai kode QR dengan aplikasi seperti Google Authenticator atau Duo Mobile, lalu masukkan kode 6 digit untuk memastikan semuanya benar.');
    }

    /**
     * Redirect setelah setup berhasil.
     *
     * Alur Google Gateway Belum Terdaftar (Menjawab Iya):
     *   Verify OTP -> Complete Profile -> Security Question (Iya)
     *     -> Two Factor Auth (Berhasil) -> SignIn.php -> Home.
     *
     * Paket menandai `2fa_passed=true` di dalam transaksi sukses, lalu
     * memanggil global `redirect()` yang TIDAK melakukan redirect Livewire
     * (return value dibuang). Karena itu di sini call `$this->redirect()`
     * eksplisit setelah parent sukses -- user TETAP login, tanpa logout,
     * tanpa OTP kedua. SignIn.php (mount: sudah login) memantul ke Home.
     *
     * Kalau kode salah, paket berhenti tanpa menandai `2fa_passed`,
     * dan method ini tidak redirect (user tetap di form).
     */
    public function setup(): void
    {
        parent::setup();

        // Sukses -> redirect ke SignIn.php (Menjawab Iya: TwoFactorAuth ->
        // SignIn.php -> Home). Gagal -> parent sudah berhenti tanpa
        // menandai 2fa_passed, jadi tidak redirect di sini.
        if (session('2fa_passed') === true
            || ($this->data['two_factor_type'] ?? null) === TwoFactorAuthType::None->value) {
            $this->redirect($this->getRedirectUrl());
        }
    }

    public function verifyTOTP(): void
    {
        parent::verifyTOTP();

        if (session('2fa_passed') === true) {
            $this->redirect($this->getRedirectUrl());
        }
    }

    public function verifyOTP(): void
    {
        parent::verifyOTP();

        if (session('2fa_passed') === true) {
            $this->redirect($this->getRedirectUrl());
        }
    }

    protected function getRedirectUrl(): string
    {
        // Alur: Two Factor Auth (Berhasil) -> SignIn.php -> Home
        return \Filament\Facades\Filament::getPanel('welcome')->getLoginUrl();
    }
}