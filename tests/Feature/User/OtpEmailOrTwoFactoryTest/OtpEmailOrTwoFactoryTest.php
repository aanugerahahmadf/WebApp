<?php

/**
 * Halaman OtpEmailOrTwoFactory -- satu halaman untuk tiga cara verifikasi
 * setelah Sign In.
 *
 * Yang diuji di sini, semuanya perilaku yang tidak bisa dilihat dari
 * membaca kode:
 *
 *   1. CheckTrustedDevice milik paket mengarahkan ke route dengan NAMA yang
 *      sama seperti OTPVerify. Kalau nama itu berubah, middleware masuk
 *      redirect loop -- jadi URL dan route name diuji eksplisit.
 *
 *   2. TOTP asli dicek dengan Google2FA. Kode 6 digit sembarang harus
 *      ditolak; kode asli harus diterima.
 *
 *   3. Kode pemulihan SEKALI PAKAI. Ini yang membedakan kode pemulihan dari
 *      kode biasa -- kalau bisa dipakai berulang, 2FA jadi tidakional.
 *
 *   4. Tidak ada lagi "Ingatkan Perangkat Ini". Before-nya default-nya
 *      menyala, sehingga Sign In berikutnya tidak pernah Ask 2FA.
 */

use App\Filament\User\Auth\OtpEmailOrTwoFactory\OtpEmailOrTwoFactory;
use App\Filament\User\Pages\Home\Home;
use App\Models\BackupCode\BackupCode;
use App\Models\User\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use MixCode\FilamentMulti2fa\Enums\TwoFactorAuthType;
use MixCode\FilamentMulti2fa\Middleware\CheckTrustedDevice;
use MixCode\FilamentMulti2fa\Models\TrustDevice;
use MixCode\FilamentMulti2fa\Pages\OTPVerify;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('user'));
});

/**
 * User dengan 2FA TOTP aktif dan secret asli.
 */
function userTotp(array $extra = []): User
{
    $user = User::factory()->create(array_merge([
        'two_factor_type' => TwoFactorAuthType::Totp,
        'two_factor_confirmed_at' => now(),
    ], $extra));

    $user->generateTwoFactorAuthenticatorAppOTPCode();
    $user->two_factor_type = TwoFactorAuthType::Totp;
    $user->two_factor_confirmed_at = now();
    $user->save();

    return $user->fresh();
}

function totpSekarang(User $user): string
{
    return (new PragmaRX\Google2FA\Google2FA)->getCurrentOtp($user->two_factor_secret);
}

/*
 * -----------------------------------------------------------------------
 * Route & middleware
 * -----------------------------------------------------------------------
 */

it('memakai route name yang sama dengan OTPVerify supaya middleware tidak loop', function (): void {
    expect(OtpEmailOrTwoFactory::getRouteName())->toBe(OTPVerify::getRouteName())
        ->and(OtpEmailOrTwoFactory::getUrl())->toBe(OTPVerify::getUrl());
});

it('CheckTrustedDevice masih mengarahkan ke halaman ini', function (): void {
    $user = userTotp();

    $r = $this->actingAs($user)->get('/user/home');

    $r->assertRedirect(OtpEmailOrTwoFactory::getUrl());
});

it('halaman ini Sendiri TIDAK di-redirect middleware (cegah loop)', function (): void {
    $user = userTotp();

    // Kalau slug/route name beda, middleware akan redirect ke sini
    // selamanya dan test ini jadi 302.
    $r = $this->actingAs($user)->get(OtpEmailOrTwoFactory::getUrl());

    $r->assertOk();
});

it('tetap mendaftarkan middleware yang sama', function (): void {
    expect(Filament::getPanel('user')->getAuthMiddleware())
        ->toContain(CheckTrustedDevice::class);
});

/*
 * -----------------------------------------------------------------------
 * Tampilan
 * -----------------------------------------------------------------------
 */

it('menampilkan alternatif di Opsi Lainnya, bukan metode yang sedang dipakai', function (): void {
    $user = userTotp();

    $r = $this->actingAs($user)->get(OtpEmailOrTwoFactory::getUrl());
    $html = $r->getContent() ?: '';

    $r->assertOk();

    // Default-nya Aplikasi Autentikasi, jadi daftar alternatifnya adalah dua
    // yang lain. Cara ini yang dipakai GitHub: yang sedang aktif disembunyikan
    // supaya tidak mungkin dipilih dua kali.
    //
    // Dibandingkan lewat __() supaya benar di locale apa pun.
    expect($html)->toContain(__('Kode Pemulihan 2FA'))
        ->toContain(__('Kirim Kode ke Email'))
        ->toContain(__('Opsi Lainnya'))
        ->toContain(__('Verifikasi'))
        ->not->toContain(__('Aplikasi Autentikasi'));

    // Checkbox "Ingatkan Perangkat Ini" harus hilang -- itu yang membuat
    // Sign In berikutnya tidak pernah Ask 2FA.
    expect($html)->not->toContain('Ingatkan Perangkat Ini');
});

it('saat mode recovery, alternatifnya berganti ke aplikasi autentikasi', function (): void {
    $user = userTotp();

    $html = Livewire::actingAs($user)
        ->test(OtpEmailOrTwoFactory::class)
        ->call('useMethod', OtpEmailOrTwoFactory::METHOD_RECOVERY)
        ->html();

    expect($html)->toContain(__('Aplikasi Autentikasi'))
        ->toContain(__('Kirim Kode ke Email'))
        ->not->toContain(__('Kode Pemulihan 2FA'));
});

it('menampilkan judul Pemulihan Dua Faktor saat mode recovery', function (): void {
    $user = userTotp();

    Livewire::actingAs($user)
        ->test(OtpEmailOrTwoFactory::class)
        ->call('useMethod', OtpEmailOrTwoFactory::METHOD_RECOVERY)
        ->assertSet('method', OtpEmailOrTwoFactory::METHOD_RECOVERY)
        ->assertSee(__('Pemulihan Dua Faktor'));
});

/*
 * -----------------------------------------------------------------------
 * Verifikasi TOTP
 * -----------------------------------------------------------------------
 */

it('menerima kode TOTP asli lalu ke Home', function (): void {
    $user = userTotp();

    Livewire::actingAs($user)
        ->test(OtpEmailOrTwoFactory::class)
        ->set('data.otp', totpSekarang($user))
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(Home::getUrl());

    expect(session('2fa_passed'))->toBeTrue();
});

it('menolak kode 6 digit sembarang', function (): void {
    $user = userTotp();

    Livewire::actingAs($user)
        ->test(OtpEmailOrTwoFactory::class)
        ->set('data.otp', '000000')
        ->call('save')
        ->assertNotified();

    expect(session('2fa_passed'))->not->toBeTrue();
});

it('menolak kode TOTP dari time-step yang sudah lewat', function (): void {
    // Catatan: TOTP berbeda dari kode pemulihan. Kodenya berlaku selama satu
    // time-step (30 detik), jadi kode yang sama pada detik yang sama memang
    // sah itu wajar dan bukan hole. Yang diuji di sini kebalikannya -- kode
    // dari 10 menit lalu harus ditolak, sehingga tidak ada kode lama yang
    // bisa dipakai untuk masuk.
    $user = userTotp();

    $g = new PragmaRX\Google2FA\Google2FA;

    $kodeLalu = $g->oathTotp(
        $user->two_factor_secret,
        $g->getTimestamp() - 600
    );

    Livewire::actingAs($user)
        ->test(OtpEmailOrTwoFactory::class)
        ->set('data.otp', $kodeLalu)
        ->call('save')
        ->assertNotified();

    expect(session('2fa_passed'))->not->toBeTrue();
});

/*
 * -----------------------------------------------------------------------
 * Kode pemulihan
 * -----------------------------------------------------------------------
 */

it('menerima kode pemulihan yang benar dan menandainya terpakai', function (): void {
    $user = userTotp();

    $backup = BackupCode::create([
        'user_id' => $user->id,
        'code' => 'ABCD-1234',
    ]);

    Livewire::actingAs($user)
        ->test(OtpEmailOrTwoFactory::class)
        ->call('useMethod', OtpEmailOrTwoFactory::METHOD_RECOVERY)
        ->set('data.otp', 'ABCD-1234')
        ->call('save')
        ->assertRedirect(Home::getUrl());

    expect($backup->fresh()->used)->toBeTrue();
});

it('kode pemulihan hanya bisa dipakai SATU KALI', function (): void {
    $user = userTotp();

    $backup = BackupCode::create([
        'user_id' => $user->id,
        'code' => 'ABCD-1234',
    ]);

    Livewire::actingAs($user)
        ->test(OtpEmailOrTwoFactory::class)
        ->call('useMethod', OtpEmailOrTwoFactory::METHOD_RECOVERY)
        ->set('data.otp', 'ABCD-1234')
        ->call('save')
        ->assertRedirect(Home::getUrl());

    session()->forget('2fa_passed');

    Livewire::actingAs($user)
        ->test(OtpEmailOrTwoFactory::class)
        ->call('useMethod', OtpEmailOrTwoFactory::METHOD_RECOVERY)
        ->set('data.otp', 'ABCD-1234')
        ->call('save')
        ->assertNotified();

    expect(session('2fa_passed'))->not->toBeTrue();
});

it('kode pemulihan lowercase tetap diterima (user bisa ketik huruf kecil)', function (): void {
    $user = userTotp();

    BackupCode::create([
        'user_id' => $user->id,
        'code' => 'ABCD-1234',
    ]);

    Livewire::actingAs($user)
        ->test(OtpEmailOrTwoFactory::class)
        ->call('useMethod', OtpEmailOrTwoFactory::METHOD_RECOVERY)
        ->set('data.otp', 'abcd-1234')
        ->call('save')
        ->assertRedirect(Home::getUrl());
});

it('menolak kode pemulihan milik user lain', function (): void {
    $user = userTotp();
    $orangLain = User::factory()->create();

    BackupCode::create([
        'user_id' => $orangLain->id,
        'code' => 'WXYZ-9999',
    ]);

    Livewire::actingAs($user)
        ->test(OtpEmailOrTwoFactory::class)
        ->call('useMethod', OtpEmailOrTwoFactory::METHOD_RECOVERY)
        ->set('data.otp', 'WXYZ-9999')
        ->call('save')
        ->assertNotified();
});

/*
 * -----------------------------------------------------------------------
 * OTP email
 * -----------------------------------------------------------------------
 */

it('menerima kode email yang dikirim ke cache', function (): void {
    $user = userTotp();

    Cache::put('2fa_email_otp_' . $user->id, '123456', now()->addMinutes(10));

    Livewire::actingAs($user)
        ->test(OtpEmailOrTwoFactory::class)
        ->call('useMethod', OtpEmailOrTwoFactory::METHOD_EMAIL)
        ->set('data.otp', '123456')
        ->call('save')
        ->assertRedirect(Home::getUrl());

    // Kode email harus dihapus setelah dipakai.
    expect(Cache::get('2fa_email_otp_' . $user->id))->toBeNull();
});

it('menolak kode email yang salah', function (): void {
    $user = userTotp();

    Cache::put('2fa_email_otp_' . $user->id, '123456', now()->addMinutes(10));

    Livewire::actingAs($user)
        ->test(OtpEmailOrTwoFactory::class)
        ->call('useMethod', OtpEmailOrTwoFactory::METHOD_EMAIL)
        ->set('data.otp', '999999')
        ->call('save')
        ->assertNotified();

    expect(session('2fa_passed'))->not->toBeTrue();
});

/*
 * -----------------------------------------------------------------------
 * Naluri
 * -----------------------------------------------------------------------
 */

it('langsung ke Home kalau 2FA belum aktif', function (): void {
    $user = User::factory()->create([
        'two_factor_type' => TwoFactorAuthType::None,
    ]);

    Livewire::actingAs($user)
        ->test(OtpEmailOrTwoFactory::class)
        ->assertRedirect(Home::getUrl());
});

it('TIDAK menambah perangkat tepercaya setelah verifikasi (tetap 2FA di Sign In berikutnya)', function (): void {
    $user = userTotp();

    Livewire::actingAs($user)
        ->test(OtpEmailOrTwoFactory::class)
        ->set('data.otp', totpSekarang($user))
        ->call('save');

    // Ini inti dari keluhan "Sign In langsung ke Home": kolom ini yang membuat
    // middleware melewati 2FA.
    expect(TrustDevice::where('user_id', $user->id)->count())->toBe(0);
});

afterEach(function (): void {
    Filament::setCurrentPanel(null);
});