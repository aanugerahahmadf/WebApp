<?php

use App\Filament\User\Auth\OtpEmailOrTwoFactory\OtpEmailOrTwoFactory;
use App\Filament\User\Auth\SecurityQuestion\SecurityQuestion;
use App\Filament\User\Auth\TwoFactorAuth\TwoFactorAuth;
use App\Filament\User\Pages\Home\Home;
use App\Livewire\User\CompleteProfileComponent\CompleteProfileComponent;
use App\Models\User\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use MixCode\FilamentMulti2fa\Enums\TwoFactorAuthType;
use Spatie\Permission\Models\Role;

/*
|--------------------------------------------------------------------------
| Dua alur Complete Profile, diuji sebagai rantai
|--------------------------------------------------------------------------
|
|   Flow 1: Complete Profile -> Security Question "Ya"
|                -> TwoFactorAuth (aktif) -> Sign In
|                -> OtpEmailOrTwoFactory -> Home
|
|   Flow 2: Complete Profile -> Security Question "Tidak"
|                -> Sign In -> Home
|
| Alur ini MIRROR dari SignUpFlowTest, tapi lewat pintu masuk yang berbeda:
| akun sosial (Google) yang profilnya kosong ditahan middleware
| EnsureProfileComplete dan harus lewat CompleteProfilePage dulu.
|
| Yang diuji adalah TRANSISI antar tahap, bukan tampilan -- sama seperti
| SignUpFlowTest, karena rantai auth rusak paling sering di titik yang tidak
| ada test-nya.
|
| Sama seperti SignUpFlowTest, bedanya di sini adalah memverifikasi bahwa
| CompleteProfileComponent::save() benar-benar LEAD ke SecurityQuestion,
| bukan diam-diam ke Home. Edge itu tidak ada testnya di file lain:
| CompleteProfileSingleColumnTest hanya mengukur layout, FaceScanModalGuardTest
| hanya modal scan wajah, dan keduanya tidak pernah memanggil save().
|
*/

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'customer']);
    Filament::setCurrentPanel(Filament::getPanel('user'));
});

/**
 * Akun sosial (Google): profil kosong, jadi tertahan CompleteProfilePage.
 * Email sudah terverifikasi karena yang diuji tahap SETELAH account dibuat.
 */
function makeGoogleUserNeedingProfile(array $overrides = []): User
{
    $user = User::factory()->create(array_merge([
        'password' => Hash::make('senin-pagi-2026'),
        'social_type' => 'google',
        'email_verified_at' => now(),
        'first_name' => null,
        'mid_name' => null,
        'last_name' => null,
        'whatsapp' => null,
        'gender' => null,
        'address' => null,
        'occupation' => null,
        'identity_type' => null,
        'two_factor_type' => TwoFactorAuthType::None->value,
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ], $overrides));

    $user->assignRole('customer');

    return $user;
}

/**
 * Isi form Complete Profile dengan data yang valid. Region diisi lewat kolom
 * `*_name` manual, bukan cascade Select, supaya test tidak bergantung pada
 * tabel wilayah yang bisa berubah.
 */
function completeProfileFormData(): array
{
    return [
        'first_name' => 'Ratu',
        'mid_name' => 'Ayu',
        'last_name' => 'Pratiwi',
        'username' => 'ratuayu',
        // WhatsappField::group() menyimpan dua key: kode negara (dehydrated=false,
        // jadi hanya untuk tampilan prefix) dan nomor nasionalnya.
        'whatsapp_country_code' => '+62|ID',
        'whatsapp' => '81234567890',
        'identity_type' => 'ktp',
        'ktp_number' => '3273010101990001',
        'birth_place_date' => 'Bandung, 01/01/1999',
        'country' => 'Indonesia',
        'province_name' => 'Jawa Barat',
        'city_name' => 'Bandung',
        'district_name' => 'Sumur Bandung',
        'village_name' => 'Dago',
        'address' => 'Jl. Ir. H. Juanda No. 1, Bandung',
        'gender' => 'female',
        'occupation' => 'wirausaha',
        'marital_status' => 'single',
        'mother_name' => 'Siti Aminah',
        'income_range' => 'middle',
        'source_of_funds' => 'income',
    ];
}

// ---------------------------------------------------------------------------
// Edge yang belum ada testnya: Complete Profile -> Security Question
// ---------------------------------------------------------------------------

test('complete profile lead ke security question, bukan ke home', function (): void {
    $user = makeGoogleUserNeedingProfile();

    // Sanity check: akun ini memang belum lengkap, jadi memang tertahan.
    expect($user->isProfileComplete())->toBeFalse()
        ->and($user->shouldCompleteProfile())->toBeTrue();

    $this->actingAs($user);

    Livewire::test(CompleteProfileComponent::class)
        ->fillForm(completeProfileFormData())
        ->call('save')
        ->assertHasNoFormErrors()
        // Inilah edge yang diuji. Kalau ini berubah jadi Home::getUrl(),
        // SecurityQuestion tidak akan pernah tampil untuk akun sosial dan
        // seluruh Flow 1 di halaman ini jadi tidak terjangkau.
        ->assertRedirect(SecurityQuestion::getUrl());

    // Dan profilnya benar-benar tersimpan, jadi hop berikutnya bukan halaman
    // kosong yang cuma redirect.
    expect($user->fresh()->isProfileComplete())->toBeTrue();
});

test('profil yang sudah lengkap tidak Asking security question lagi', function (): void {
    // Kalau guard mount() CompleteProfilePage hilang, akun yang sudah lengkap
    // akan ditahan di form padahal tidak ada yang perlu dilengkapi -- dan
    // SecurityQuestion-nya juga tidak akan pernah ditanyakan.
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'social_type' => 'google',
        'first_name' => 'Ratu',
        'mid_name' => 'Ayu',
        'last_name' => 'Pratiwi',
        'whatsapp' => '081234567890',
        'gender' => 'female',
        'address' => 'Jl. Ir. H. Juanda No. 1, Bandung',
        'occupation' => 'wirausaha',
        'identity_type' => 'ktp',
    ]);

    expect($user->isProfileComplete())->toBeTrue()
        ->and($user->shouldCompleteProfile())->toBeFalse();

    $this->actingAs($user)
        ->get('/home')
        ->assertOk();
});

// ---------------------------------------------------------------------------
// Flow 2 -- Security Question "Tidak": Sign In -> Home
// ---------------------------------------------------------------------------

test('flow 2: jawaban tidak setelah complete profile keluar ke sign in', function (): void {
    $user = makeGoogleUserNeedingProfile();

    $this->actingAs($user);

    Livewire::test(SecurityQuestion::class)
        ->callAction('skip');

    // Logout wajib: tanpa itu /signin memantulkan balik ke panel dan
    // tahap Sign In dilewati tanpa jejak.
    $this->assertGuest();

    expect(session('2fa_passed'))->toBeNull()
        ->and(session('trusted_device_validated'))->toBeNull();

    $this->get('/signin')->assertOk();
});

test('flow 2: setelah sign in tanpa 2FA, langsung home', function (): void {
    // Profil harus LENGKAP dulu di sini. User Google yang profilnya kosong
    // memang benar-benar diarahkan ke CompleteProfilePage setelah Sign In,
    // bukan Home -- itu middleware EnsureProfileComplete yang menahan. Pakai
    // akun berprofil lengkap supaya test ini menguji "tidak ada 2FA -> Home",
    // bukan tertahan di gate profil.
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'social_type' => 'google',
        'first_name' => 'Ratu',
        'mid_name' => 'Ayu',
        'last_name' => 'Pratiwi',
        'whatsapp' => '081234567890',
        'gender' => 'female',
        'address' => 'Jl. Ir. H. Juanda No. 1, Bandung',
        'occupation' => 'wirausaha',
        'identity_type' => 'ktp',
        'two_factor_type' => TwoFactorAuthType::None->value,
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ])->assignRole('customer');

    expect($user->hasSetupTwoFactor())->toBeFalse()
        ->and($user->isProfileComplete())->toBeTrue();

    $this->actingAs($user)
        ->get('/home')
        ->assertOk();
});

test('akun google dengan profil kosong setelah sign in ditahan di complete profile', function (): void {
    // Kebalikan dari test di atas: yang benar DITAHAN, bukan diloloskan.
    // Kalau gate ini hilang, user google yang profilnya kosong akan mendarat
    // di Home tanpa pernah melengkapi profil.
    $user = makeGoogleUserNeedingProfile();

    $this->actingAs($user)
        ->get('/home')
        ->assertRedirect(route('filament.welcome.pages.complete-profile'));
});

// ---------------------------------------------------------------------------
// Flow 1 -- Security Question "Ya": 2FA -> Sign In -> OtpEmailOrTwoFactory -> Home
// ---------------------------------------------------------------------------

test('flow 1: jawaban ya setelah complete profile masuk ke aktivasi 2FA', function (): void {
    $user = makeGoogleUserNeedingProfile();

    $this->actingAs($user);

    Livewire::test(SecurityQuestion::class)
        ->callAction('strengthen')
        ->assertRedirect(TwoFactorAuth::getUrl());
});

test('flow 1: 2FA aktif lalu sign in nyata, 2FA diminta di OTP factory', function (): void {
    $user = makeGoogleUserNeedingProfile([
        'two_factor_type' => TwoFactorAuthType::Totp->value,
        'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
        'two_factor_confirmed_at' => now(),
    ]);

    // Sesi BARU -- 2fa_passed tidak disetel, persis seperti setelah sign-out.
    $this->assertNull(session('2fa_passed'));

    $this->actingAs($user)
        ->get('/home')
        // Tanpa diminta kode, middleware akan meloloskan user ke Home dan
        // OtpEmailOrTwoFactory tidak akan pernah tampil.
        ->assertRedirect(OtpEmailOrTwoFactory::getUrl());
});

test('flow 1: setelah 2FA diaktifkan, sign in bisa dibuka', function (): void {
    $user = makeGoogleUserNeedingProfile();

    $this->actingAs($user);

    $page = Livewire::test(TwoFactorAuth::class)
        ->fillForm(['two_factor_type' => TwoFactorAuthType::Totp->value], 'setupForm')
        ->call('setup');

    $secret = $user->fresh()->two_factor_secret;

    expect($secret)->not->toBeNull();

    $page->fillForm(['otp' => currentTotp($secret)], 'verifyTOTPForm')
        ->call('verifyTOTP')
        ->assertRedirect(Filament::getPanel('user')->getLoginUrl());

    $this->assertGuest();

    expect(session('2fa_passed'))->toBeNull()
        ->and(session('trusted_device_validated'))->toBeNull();

    $this->get('/signin')->assertOk();
});

// ---------------------------------------------------------------------------
// Titik pertemuan kedua alur
// ---------------------------------------------------------------------------

test('kedua alur bertemu: security question hanya tampil sekali', function (): void {
    $user = makeGoogleUserNeedingProfile();

    $this->actingAs($user);

    // Menjawab "Ya" menandai sudah dijawab, jadi mendarat lagi di halaman
    // yang sama tidak menanyanya dua kali.
    Livewire::test(SecurityQuestion::class)
        ->callAction('strengthen')
        ->assertRedirect(TwoFactorAuth::getUrl());

    Livewire::test(SecurityQuestion::class)
        ->assertRedirect(Home::getUrl());
});
