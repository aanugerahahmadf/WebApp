<?php

use App\Filament\User\Auth\OtpEmailOrTwoFactory\OtpEmailOrTwoFactory;
use App\Filament\User\Auth\OtpEmailVerificationPrompt\OtpEmailVerificationPrompt;
use App\Filament\User\Auth\SecurityQuestion\SecurityQuestion;
use App\Filament\User\Auth\TwoFactorAuth\TwoFactorAuth;
use App\Filament\User\Pages\Home\Home;
use App\Models\User\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use MixCode\FilamentMulti2fa\Enums\TwoFactorAuthType;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Role;

/*
|--------------------------------------------------------------------------
| Dua alur Sign Up, diuji sebagai rantai
|--------------------------------------------------------------------------
|
|   Flow 1: Sign Up -> Email OTP -> Security Question "Ya"
|                -> TwoFactorAuth (aktif) -> Sign In
|                -> OtpEmailOrTwoFactory -> Home
|
|   Flow 2: Sign Up -> Email OTP -> Security Question "Tidak"
|                -> Sign In -> Home
|
| Yang diuji adalah TRANSIISI antar tahap, bukan tampilan. Rantai auth rusak
| paling sering di titik yang tidak ada test-nya: halaman yang benar ada tapi
| tidak pernah-lead ke halaman berikutnya, atau ada tahap yang dilewati diam-diam.
|
| Karena itu yang dikunci justru perpindahan itu:
|   - Email OTP terverifikasi harus mendarat di Security Question, bukan Home.
|     Dulu ia langsung ke Home, dan SecurityQuestion tidak pernah dipanggil
|     satu kali pun dari mana saja.
|   - Stage "Sign In" harus NYATA. Filament memantulkan user yang sudah login
|     balik ke panel (Login::mount()), jadi tanpa sign-out tahap ini dilewati
|     tanpa jejak dan 2FA tidak pernah diuji.
|   - Penanda bypass (`2fa_passed`, `trusted_device_validated`) harus ikut
|     dibuang saat sign-out; kalau tidak, Sign In berikutnya lolos 2FA.
|
*/

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
    Role::firstOrCreate(['name' => 'customer']);
    Filament::setCurrentPanel(Filament::getPanel('user'));
});

/** Akun hasil Sign Up: profil form sudah lengkap, email belum diverifikasi. */
function makeFreshSignup(array $overrides = []): User
{
    $user = User::factory()->create(array_merge([
        'password' => Hash::make('senin-pagi-2026'),
        'social_type' => null,
        'email_verified_at' => null,
        'two_factor_type' => TwoFactorAuthType::None->value,
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ], $overrides));

    $user->assignRole('customer');

    return $user;
}

// `currentTotp()` dipindah ke tests/Pest.php (dipakai 2 file test).

// ---------------------------------------------------------------------------
// Tahap 1 -- Email OTP
// ---------------------------------------------------------------------------

test('email yang belum diverifikasi tidak dikejar middleware panel', function (): void {
    $user = makeFreshSignup();

    /*
     * PERUBAHAHAN KONTRAK (merge panel user -> panel welcome).
     *
     * Dulu panel user memasang middleware `EnsureEmailIsVerified` ke SETIAP
     * route, jadi akun yang emailnya belum terverifikasi langsung dibuang ke
     * halaman prompt. Di panel welcome (path '/') middleware itu TIDAK boleh
     * dipasang: panel ini juga melayani tamu, dan middleware_verifikasi
     * ikut menimpa halaman publik -- etalase toko ikut terkunci untuk
     * orang yang belum punya akun.
     *
     * Verifikasi sekarang diurus oleh flow-nya sendiri: Sign Up ->
     * halaman OTP (OtpEmailVerificationPrompt) -> Complete Profile. Jadi
     * akun yang belum verifikasi TIDAK dipaksa pindah halaman, tapi
     * halaman prompt-nya tetap hidup dan bisa dipakai.
     */
    $this->actingAs($user)
        ->get('/home')
        ->assertOk();

    // Halaman promptnya tetap hidup dan tetap bisa dipakai.
    $this->actingAs($user)
        ->get(route('filament.welcome.auth.email-verification.prompt'))
        ->assertOk();
});

test('setelah OTP valid, akun form mendarat di security question', function (): void {
    $user = makeFreshSignup();
    $user->forceFill(['email_verified_at' => null])->save();

    $this->actingAs($user);

    $component = Livewire::test(OtpEmailVerificationPrompt::class);

    // Kode DIBACA dari cache, bukan diketik manual: `mount()` halaman ini
    // mengirim OTP sendiri lalu menimpanya dengan kode acak, jadi nilai yang
    // kita seed SEBELUM mount() sudah tidak berlaku ketika verify() jalan.
    $otp = Cache::get('otp_'.$user->id);

    expect($otp)->not->toBeNull();

    // ViewField tidak bisa di-fill dengan fillForm(), jadi set data langsung.
    $component
        ->set('data.otp', (string) $otp)
        ->call('verify')
        ->assertRedirect(SecurityQuestion::getUrl());

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

// ---------------------------------------------------------------------------
// Tahap 2 -- Security Question, cabang "Tidak" (Flow 2)
// ---------------------------------------------------------------------------

test('flow 2: jawaban tidak keluar ke sign in dan logout', function (): void {
    $user = makeFreshSignup(['email_verified_at' => now()]);

    $this->actingAs($user);

    Livewire::test(SecurityQuestion::class)
        ->callAction('skip');

    // Logout itu wajib: tanpa itu /signin memantulkan balik ke panel.
    $this->assertGuest();

    // Dan penanda bypass harus ikut hilang, kalau tidak Sign In berikutnya
    // lolos 2FA tanpa diminta kode.
    expect(session('2fa_passed'))->toBeNull()
        ->and(session('trusted_device_validated'))->toBeNull();

    $this->get('/signin')->assertOk();
});

test('flow 2: setelah sign in tanpa 2FA, langsung home', function (): void {
    $user = makeFreshSignup(['email_verified_at' => now()]);

    // Sesi bersih, 2FA tidak aktif: middleware tidak boleh mengganggu.
    expect($user->hasSetupTwoFactor())->toBeFalse();

    $this->actingAs($user)
        ->get('/home')
        ->assertOk();
});

// ---------------------------------------------------------------------------
// Tahap 2 -- Security Question, cabang "Ya" (Flow 1)
// ---------------------------------------------------------------------------

test('flow 1: jawaban ya masuk ke halaman aktivasi 2FA', function (): void {
    $user = makeFreshSignup(['email_verified_at' => now()]);

    $this->actingAs($user);

    Livewire::test(SecurityQuestion::class)
        ->callAction('strengthen')
        ->assertRedirect(TwoFactorAuth::getUrl());
});

test('flow 1: 2FA aktif, tahap sign in nyata, lalu 2FA diminta', function (): void {
    $secret = (new Google2FA)->generateSecretKey();

    $user = makeFreshSignup([
        'email_verified_at' => now(),
        'two_factor_type' => TwoFactorAuthType::Totp->value,
        'two_factor_secret' => $secret,
        'two_factor_confirmed_at' => now(),
    ]);

    // Sesi BARU -- 2fa_passed tidak disetel, persis seperti setelah sign-out.
    $this->assertNull(session('2fa_passed'));

    $this->actingAs($user)
        ->get('/home')
        // Inilah yang akan hilang kalau tahap Sign In dilewati diam-diam:
        // tanpa diminta kode, middleware akan meloloskan user ke Home.
        ->assertRedirect(OtpEmailOrTwoFactory::getUrl());
});

test('flow 1: setelah 2FA diaktifkan, sign in mengarah ke form sign in', function (): void {
    $user = makeFreshSignup(['email_verified_at' => now()]);

    $this->actingAs($user);

    $page = Livewire::test(TwoFactorAuth::class)
        ->fillForm(['two_factor_type' => TwoFactorAuthType::Totp->value], 'setupForm')
        ->call('setup');

    $secret = $user->fresh()->two_factor_secret;

    expect($secret)->not->toBeNull();

    $page->fillForm(['otp' => currentTotp($secret)], 'verifyTOTPForm')
        ->call('verifyTOTP')
        ->assertRedirect(Filament::getPanel('user')->getLoginUrl());

    // Sign-out harus terjadi di tahap yang sama.
    $this->assertGuest();

    expect(session('2fa_passed'))->toBeNull()
        ->and(session('trusted_device_validated'))->toBeNull();

    // Dan form Sign In harus benar-benar bisa dibuka -- kalau tidak, Sign In
    // di rantai ini hanya nominally ada.
    $this->get('/signin')->assertOk();
});

test('flow 1: kode 2FA yang salah tidak mengeluarkan user dari sesi', function (): void {
    $user = makeFreshSignup(['email_verified_at' => now()]);

    $this->actingAs($user);

    $page = Livewire::test(TwoFactorAuth::class)
        ->fillForm(['two_factor_type' => TwoFactorAuthType::Totp->value], 'setupForm')
        ->call('setup');

    $page->fillForm(['otp' => '000000'], 'verifyTOTPForm')
        ->call('verifyTOTP')
        ->assertHasFormErrors(['otp'], 'verifyTOTPForm');

    // Gagal = belum selesai setup, jadi user TIDAK boleh ikut logout.
    $this->assertAuthenticated();

    expect($user->fresh()->hasSetupTwoFactor())->toBeFalse();
});

test('dua alur bertemu di satu tempat: security question hanya tampil sekali', function (): void {
    $user = makeFreshSignup(['email_verified_at' => now()]);

    $this->actingAs($user);

    // Menjawab "Ya" menandai sudah dijawab, jadi mendarat lagi di halaman
    // yang sama tidak menanyanya dua kali.
    Livewire::test(SecurityQuestion::class)
        ->callAction('strengthen')
        ->assertRedirect(TwoFactorAuth::getUrl());

    Livewire::test(SecurityQuestion::class)
        ->assertRedirect(Home::getUrl());
});

