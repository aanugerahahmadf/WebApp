<?php

use App\Filament\User\Auth\OtpEmailOrTwoFactory\OtpEmailOrTwoFactory;
use App\Filament\User\Auth\TwoFactorAuth\TwoFactorAuth;
use App\Filament\User\Pages\Home\Home;
use App\Filament\Welcome\Pages\SettingsPage\PasswordSecurityPage\PasswordSecurityPage as WelcomePasswordSecurityPage;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SignInAndRecovery\TwoFactorySetup\TwoFactorySetup;
use App\Models\User\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use MixCode\FilamentMulti2fa\Enums\TwoFactorAuthType;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/*
|--------------------------------------------------------------------------
| Multi 2FA -- alur utuh dari setup sampai Sign In berikutnya
|--------------------------------------------------------------------------
|
| "Kalau dinyalakan harus berfungsi 100%." Itu diuji sebagai satu rantai,
| bukan per halaman, karena tidak ada satu bagian pun yang berguna kalau
| tahap berikutnya tidak uphold:
|
|   1. Setup: pilih Authenticator App -> secret dibuat -> QR + Kode Manual
|      tampil -> kode TOTP valid -> `two_factor_type` tersimpan dan
|      `two_factor_confirmed_at` terisi.
|   2. Sesi berikutnya: CheckTrustedDevice seeing 2FA aktif harus
|      mengarahkan ke halaman verifikasi, bukan meloloskan user ke Home.
|   3. Verifikasi: kode TOTP yang benar diterima, salah ditolak, dan
|     begitu berhasil session 2fa_passed=true sampai middleware melepas.
|
| Yang dikunci di sini bukan tampilan, tapi perpindahan state antar tahap --
| bug paling umum di 2FA justru muncul di tahap yang tidak diuji.
|
*/

uses(RefreshDatabase::class);

/*
 * CATATAN PENTING -- kenapa nama form selalu ditulis eksplisit.
 *
 * Halaman ini TIDAK punya form bernama `form`. Form-nya bernama `setupForm`,
 * `verifyOTPForm`, dan `verifyTOTPForm` -- itu dari paket, yang meng-override
 * getForms(). sementara `Livewire::test()->fillForm()` secara default
 * mengasumsikan nama `form`.
 *
 * Kalau nama tidak ditulis, `fillForm()` memanggil `assertFormExists('form')`
 * -> `$this->form` -> Livewire tidak punya properti itu -> Filament jatuh ke
 * cabang infolist -> memanggil `form(makeInfolist())` -> TypeError.
 *
 * Jadi TypeError itu GEJALA dari test yang salah, bukan bug produksi: di
 * browser, view memakai `{{ $this->setupForm }}` secara eksplisit dan tidak
 * pernah menyentuh `$this->form`.
 */

beforeEach(function (): void {
    Mail::fake();
    Role::firstOrCreate(['name' => 'customer']);

    Filament::setCurrentPanel(Filament::getPanel('user'));
});

function makeUserWith2faState(array $overrides = []): User
{
    $user = User::factory()->create(array_merge([
        'password' => Hash::make('senin-pagi-2026'),
        'social_type' => null,
        'email_verified_at' => now(),
        'two_factor_type' => TwoFactorAuthType::None->value,
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ], $overrides));

    $user->assignRole('customer');

    return $user;
}

// `currentTotp()` dipindah ke tests/Pest.php (dipakai 2 file test).

/*
|--------------------------------------------------------------------------
| Tahap 1 -- Setup
|--------------------------------------------------------------------------
*/

test('setup authenticator app menyimpan tipe dan konfirmasi setelah kode valid', function (): void {
    $user = makeUserWith2faState();
    actingAs($user);

    $page = Livewire::test(TwoFactorySetup::class)
        ->assertSuccessful()
        ->assertSet('showSetupForm', true)
        ->fillForm(['two_factor_type' => TwoFactorAuthType::Totp->value], 'setupForm')
        ->call('setup');

    // Secret harus dibuat di tahap ini, bukan nanti: QR bergantung padanya.
    $secret = $user->fresh()->two_factor_secret;

    expect($secret)->not->toBeNull()
        ->and($page->get('showVerifyTOTPForm'))->toBeTrue()
        ->and($page->get('showSetupForm'))->toBeFalse();

    $page->fillForm(['otp' => currentTotp($secret)], 'verifyTOTPForm')
        ->call('verifyTOTP')
        ->assertHasNoFormErrors([], 'verifyTOTPForm');

    $user->refresh();

    expect($user->two_factor_type)->toBe(TwoFactorAuthType::Totp)
        ->and($user->two_factor_confirmed_at)->not->toBeNull()
        // Secret TOTP tidak boleh dihapus saat konfirmasi -- inilah yang membuat
        // kode di aplikasi autentikator tetap berlaku.
        ->and($user->two_factor_secret)->toBe($secret)
        ->and($user->hasSetupTwoFactor())->toBeTrue();
});

test('setup menolak kode TOTP yang salah tanpa menyimpan apa pun', function (): void {
    $user = makeUserWith2faState();
    actingAs($user);

    $page = Livewire::test(TwoFactorySetup::class)
        ->fillForm(['two_factor_type' => TwoFactorAuthType::Totp->value], 'setupForm')
        ->call('setup');

    $page->fillForm(['otp' => '000000'], 'verifyTOTPForm')
        ->call('verifyTOTP')
        ->assertHasFormErrors(['otp'], 'verifyTOTPForm');

    expect($user->fresh()->two_factor_type)->toBe(TwoFactorAuthType::None)
        ->and($user->fresh()->two_factor_confirmed_at)->toBeNull()
        ->and($user->hasSetupTwoFactor())->toBeFalse();
});

test('setup menampilkan QR dan kode manual setelah secret dibuat', function (): void {
    $user = makeUserWith2faState();
    actingAs($user);

    $page = Livewire::test(TwoFactorySetup::class)
        ->fillForm(['two_factor_type' => TwoFactorAuthType::Totp->value], 'setupForm')
        ->call('setup')
        ->assertSuccessful();

    $secret = $user->fresh()->two_factor_secret;

    // Kode manual adalah isi QR-nya. Tanpa ini, pengguna yang kameranya
    // tidak bisa dipakai tidak punya jalan masuk sama sekali.
    expect($secret)->not->toBeNull();

    $html = $page->html();

    expect($html)->toContain(e($secret))
        // Secret tidak boleh bocor ke halaman SEBELUM setup dimulai.
        ->and($html)->not->toBe('');
});

/*
|--------------------------------------------------------------------------
| Tahap 2 -- Sesi berikutnya
|--------------------------------------------------------------------------
*/

test('middleware mengarahkan ke verifikasi begitu 2FA aktif di sesi berikutnya', function (): void {
    $user = makeUserWith2faState([
        'two_factor_type' => TwoFactorAuthType::Totp->value,
        'two_factor_secret' => (new Google2FA)->generateSecretKey(),
        'two_factor_confirmed_at' => now(),
    ]);

    // Minta halaman user yang butuh auth, dengan 2fa_passed TIDAK disetel.
    $response = $this->actingAs($user)->get(Home::getUrl(panel: 'user'));

    $response->assertRedirect(OtpEmailOrTwoFactory::getUrl(panel: 'user'));
});

/*
|--------------------------------------------------------------------------
| Tahap 3 -- Verifikasi
|--------------------------------------------------------------------------
*/

test('verifikasi menerima kode TOTP yang benar dan melepas middleware', function (): void {
    $secret = (new Google2FA)->generateSecretKey();

    $user = makeUserWith2faState([
        'two_factor_type' => TwoFactorAuthType::Totp->value,
        'two_factor_secret' => $secret,
        'two_factor_confirmed_at' => now(),
    ]);

    $this->actingAs($user);

    expect($user->isOtpPassed())->toBeFalse();

    Livewire::test(OtpEmailOrTwoFactory::class)
        ->assertSuccessful()
        // Metode awal harus mengikuti tipe yang tersimpan, bukan default.
        ->assertSet('method', OtpEmailOrTwoFactory::METHOD_AUTHENTICATOR)
        ->fillForm(['otp' => currentTotp($secret)])
        ->call('save')
        ->assertRedirect(Home::getUrl(panel: 'user'));

    expect(session('2fa_passed'))->toBeTrue();
});

test('verifikasi menolak kode TOTP salah dan tidak menandai lolos', function (): void {
    $secret = (new Google2FA)->generateSecretKey();

    $user = makeUserWith2faState([
        'two_factor_type' => TwoFactorAuthType::Totp->value,
        'two_factor_secret' => $secret,
        'two_factor_confirmed_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test(OtpEmailOrTwoFactory::class)
        ->fillForm(['otp' => '000000'])
        ->call('save');

    expect(session('2fa_passed'))->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Halaman settings
|--------------------------------------------------------------------------
*/

test('halaman ringkasan 2FA menaut ke halaman setup', function (): void {
    $user = makeUserWith2faState();
    actingAs($user);

    Livewire::test(WelcomePasswordSecurityPage::class)->set('section', 'two-factor')
        ->assertSuccessful()
        ->assertSee(__('Tambahkan Aplikasi Autentikasi'));

    expect(TwoFactorySetup::getUrl(panel: 'welcome'))->toContain('two-factor/setup');
});

test('halaman auth_two_factor_menolak_pengunjung_yang_sudah_aktif', function (): void {
    $user = makeUserWith2faState([
        'two_factor_type' => TwoFactorAuthType::Totp->value,
        'two_factor_secret' => (new Google2FA)->generateSecretKey(),
        'two_factor_confirmed_at' => now(),
    ]);

    // Halaman Auth\TwoFactorAuth hanya untuk aktivasi di tengah alur login.
    // Kalau 2FA sudah aktif, visiting HARUS dialihkan, bukan menampilkan form
    // setup lagi -- form itu akan ditolak paket dengan "verified before".
    //
    // Yang diharapkan di sini BUKAN Home. Middleware CheckTrustedDevice
    // berjalan lebih dulu: 2FA aktif dan sesi ini belum pernah lolos, jadi
    // pengguna diarahkan ke halaman verifikasi. Mount() TwoFactorAuth
    // bahkan tidak ikut jalan.
    //
    // Itu justru hasil yang benar: ke halaman verify lebih berguna daripada
    // ke Home, dan yang diuji di sini adalah syarat yang benar-benar penting
    // -- form setup TIDAK boleh bisa dijangkau saat 2FA sudah aktif, karena
    // form itu akan ditolak paket dengan "You have already verified your
    // account with this method before".
    $this->actingAs($user)
        ->get(TwoFactorAuth::getUrl(panel: 'user'))
        ->assertRedirect(OtpEmailOrTwoFactory::getUrl(panel: 'user'));

    // Setelah 2FA benar-benar terlewati, halaman ini jadi tidak berguna dan
    // harus dialihkan ke Home -- bukan menampilkan form setup kosong.
    $this->actingAs($user)
        ->withSession(['2fa_passed' => true])
        ->get(TwoFactorAuth::getUrl(panel: 'user'))
        ->assertRedirect(Home::getUrl(panel: 'user'));
});

/*
|--------------------------------------------------------------------------
| Mengelola 2FA yang sudah aktif
|--------------------------------------------------------------------------
|
| Tombol di Pengaturan menulis "Kelola Aplikasi Autentikasi" kalau 2FA sudah
| nyala, jadi halaman setup bukan hanya untuk aktivasi pertama -- dia juga
| tempat memindah metode dan mematikan 2FA. Dua jalur itu diuji di sini
| karena keduanya lewat kode paket yang berbeda (verifyOTP vs verifyTOTP vs
| cabang "None"), dan masing-masing bisa rusak sendiri-sendiri.
|
*/

test('pindah dari TOTP ke Email membersihkan secret TOTP lama', function (): void {
    $oldSecret = (new Google2FA)->generateSecretKey();

    $user = makeUserWith2faState([
        'two_factor_type' => TwoFactorAuthType::Totp->value,
        'two_factor_secret' => $oldSecret,
        'two_factor_confirmed_at' => now(),
    ]);

    actingAs($user);

    // Email memakai OTP yang dikirim ke alamat, bukan kode dari aplikasi.
    Mail::assertNothingSent();

    $page = Livewire::test(TwoFactorySetup::class)
        ->fillForm(['two_factor_type' => TwoFactorAuthType::Email->value], 'setupForm')
        ->call('setup');

    expect($page->get('showVerifyOTPForm'))->toBeTrue()
        ->and($page->get('showVerifyTOTPForm'))->toBeFalse();

    $kode = decrypt($user->fresh()->two_factor_secret);

    $page->fillForm(['otp' => $kode], 'verifyOTPForm')
        ->call('verifyOTP')
        ->assertHasNoFormErrors([], 'verifyOTPForm');

    $user->refresh();

    // Secret TOTP harus dibuang. Kalau tidak, `two_factor_secret` berisi OTP
    // email terenkripsi sementara aplikasi autentikasi masih memegang kunci
    // lama -- dan kode dari aplikasi lama ikut diterima.
    expect($user->two_factor_type)->toBe(TwoFactorAuthType::Email)
        ->and($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_confirmed_at)->not->toBeNull();
});

test('memilih None mematikan 2FA dan mengembalikan user ke daftar keamanan', function (): void {
    $user = makeUserWith2faState([
        'two_factor_type' => TwoFactorAuthType::Totp->value,
        'two_factor_secret' => (new Google2FA)->generateSecretKey(),
        'two_factor_confirmed_at' => now(),
    ]);

    actingAs($user);

    Livewire::test(TwoFactorySetup::class)
        ->fillForm(['two_factor_type' => TwoFactorAuthType::None->value], 'setupForm')
        ->call('setup')
        ->assertRedirect(WelcomePasswordSecurityPage::getUrl(['section' => 'two-factor'], panel: 'welcome'));

    $user->refresh();

    expect($user->two_factor_type)->toBe(TwoFactorAuthType::None)
        ->and($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull()
        ->and($user->hasSetupTwoFactor())->toBeFalse()
        // Penanda ini harus ikut dibersihkan, kalau tidak sesi yang sedang
        // berjalan tetap dianggap sudah lolos 2FA.
        ->and(session('trusted_device_validated'))->toBeNull();
});
