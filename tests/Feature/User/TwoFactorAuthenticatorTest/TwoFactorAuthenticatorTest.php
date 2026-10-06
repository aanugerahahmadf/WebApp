<?php

/**
 * Autentikasi dua faktor (Authenticator App) di panel User.
 *
 * Dikerjakan dengan mix-code/filament-multi-2fa: TOTP asli lewat
 * PragmaRX\Google2FA, QR code, dan middleware CheckTrustedDevice yang
 * membuat Sign In berikutnya ikut meminta kode.
 *
 * Test ini mengunci dua hal:
 *
 *   1. Alur yang benar -- tombol dari halaman Password & Security
 *     Navigasi ke halaman setup, dan setelah 2FA aktif halaman User
 *      dialihkan ke halaman verifikasi OTP.
 *
 *   2. Alur palsu lama tidak boleh kembali. Secara lama halaman itu
 *      menampilkan secret key manual di tempat, dan 'verifikasi'-nya hanya
 *      memakai aturan digits:6 -- tanpa cek TOTP sama sekali. Kode 6 digit
 *      berapa pun akan lolos dan 2FA aktif, jadi siapa pun bisa masuk dengan
 *      mengetik angka sembarang.
 */

use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\PasswordSecurityPage;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SignInAndRecovery\TwoFactorySetup\TwoFactorySetup;
use App\Models\User\User;
use Filament\Facades\Filament;
use MixCode\FilamentMulti2fa\Enums\TwoFactorAuthType;
use MixCode\FilamentMulti2fa\Pages\OTPVerify;


// Page::getRouteName() milik Filament butuh panel aktif. Tanpa panel yang
// diset, filament() mengembalikan null dan pemanggilannya gagal -- bukan
// karena halamannya salah, tapi karena tidak ada konteks panel.
beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('user'));
});
/*
 * Alur yang benar
 */

it('mendaftarkan halaman setup dan verifikasi OTP di panel User saja', function (): void {
    $routes = collect(Route::getRoutes()->getRoutes())->map(fn ($r) => $r->getName());

    // Hanya panel User. Panel Welcome melayani tamu dan panel Admin dipakai
    // staf, sehingga 2FA tidak relevan di sana.
    expect($routes)->toContain(TwoFactorySetup::getRouteName())
        ->and($routes)->toContain(OTPVerify::getRouteName())
        ->and(TwoFactorySetup::getRouteName())->toStartWith('filament.user.')
        ->and(OTPVerify::getRouteName())->toStartWith('filament.user.');
});

it('tidak mendaftarkan halaman setup milik paket', function (): void {
    // Plugin UserMulti2faPlugin sengaja tidak mendaftarkan
    // MixCode\FilamentMulti2fa\Pages\TwoFactorySetup: halaman setup di app ini
    // milik sendiri dan punya tempat sendiri di bawah PasswordSecurityPage.
    // Kalau paket ikut terdaftar, ada dua halaman setup yang saling
    // menduplikasi dan menu "2FA Setup" muncul di user menu.
    $routes = collect(Route::getRoutes()->getRoutes())->map(fn ($r) => $r->getName());

    expect($routes)->not->toContain('filament.user.pages.two-factory-setup');
});

it('membuka halaman setup untuk pengguna yang sudah login', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(TwoFactorySetup::getUrl())
        ->assertOk();
});

it('mengarahkan tombol aplikasi autentikasi ke halaman setup', function (): void {
    $view = (string) file_get_contents(
        resource_path('views/User/pages/settings-page/password-security-page/sign-in-and-recovery/two-factor/two-factor.blade.php')
    );

    // Tombol harus berpindah ke halaman setup, bukan form inline.
    // View memakai FQCN, bukan nama singkat, jadi tidak ada import yang bisa
    // diam-diam mengarah ke kelas yang salah.
    expect($view)
        ->toContain(TwoFactorySetup::class.'::getUrl(')
        ->not->toContain('wire:click="prepareAuthenticator"')
        ->not->toContain('authenticatorSecret');
});

it('menjamin kode 6 digit sembarang tidak bisa mengaktifkan 2FA', function (): void {
    // Penjaga Explicit: method verifyAuthenticator() yang dulu hanya
    // memvalidasi digits:6 sudah dihapus. Kalau suatu saat dikembalikan,
    // 2FA bisa diaktifkan tanpa kode TOTP yang benar.
    expect(method_exists(PasswordSecurityPage::class, 'verifyAuthenticator'))->toBeFalse()
        ->and(method_exists(PasswordSecurityPage::class, 'prepareAuthenticator'))->toBeFalse();
});

/*
 * Penegakan setelah 2FA aktif
 */

it('minta kode OTP setelah 2FA aktif', function (): void {
    $user = User::factory()->create([
        'two_factor_type' => TwoFactorAuthType::Totp,
        'two_factor_confirmed_at' => now(),
    ]);

    $this->actingAs($user)
        ->get('/user/home')
        ->assertRedirect(OTPVerify::getUrl());
});

it('tidak meminta OTP kalau 2FA belum diaktifkan', function (): void {
    $user = User::factory()->create([
        'two_factor_type' => TwoFactorAuthType::None,
    ]);

    $this->actingAs($user)
        ->get('/user/home')
        ->assertOk();
});

it('tidak meminta OTP lagi setelah kode terverifikasi di sesi yang sama', function (): void {
    $user = User::factory()->create([
        'two_factor_type' => TwoFactorAuthType::Totp,
        'two_factor_confirmed_at' => now(),
    ]);

    $this->actingAs($user)
        ->withSession(['2fa_passed' => true])
        ->get('/user/home')
        ->assertOk();
});

/*
 * Model
 */

it('menyimpan secret dan tipe 2FA lewat trait package', function (): void {
    $user = User::factory()->create([
        'two_factor_type' => TwoFactorAuthType::None,
    ]);

    expect($user->hasSetupTwoFactor())->toBeFalse();

    // Secret asli dari Google2FA, bukan string random seperti implementasi lama.
    $user->generateTwoFactorAuthenticatorAppOTPCode();

    expect($user->two_factor_secret)
        ->not->toBeNull()
        ->and(strlen($user->two_factor_secret))->toBeGreaterThan(10)
        ->and($user->two_factor_confirmed_at)->toBeNull();

    // Secret harus bisa dipakai verifier TOTP -- inilah yang tidak pernah
    // dicek oleh implementasi lama.
    $valid = (new PragmaRX\Google2FA\Google2FA)->getCurrentOtp($user->two_factor_secret);

    expect((new PragmaRX\Google2FA\Google2FA)->verifyKey($user->two_factor_secret, $valid))->toBeTrue()
        ->and((new PragmaRX\Google2FA\Google2FA)->verifyKey($user->two_factor_secret, '000000'))->toBeFalse();
});

it('menunjuk relasi perangkat terpercaya ke model milik package', function (): void {
    $user = User::factory()->create();

    // Tabel trust_devices memakai device_signature / expires_at (schema
    // package). Relasi lama menunjuk model dengan device_fingerprint.
    expect($user->trustedDevices()->getRelated())->toBeInstanceOf(
        MixCode\FilamentMulti2fa\Models\TrustDevice::class
    );
});

afterEach(function (): void {
    Filament::setCurrentPanel(null);
});