<?php

use App\Models\Admin\Admin;
use App\Models\User\User;
use App\Support\AdminOtp\AdminOtp;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/**
 * Flow "Lupa Kata Sandi?" panel admin setelah tabel `admins` dipisah.
 *
 * Yang diuji, dan semuanya bisa lolos tanpa error -- gagal dengan diam:
 *
 *   1. Permintaan OTP harus menemukan admin di `admins`, bukan di `users`.
 *      Dulu `User::where('email', ...)` -- yang selalu null setelah pemisahan,
 *      jadi tidak ada kode yang pernah terkirim dan tidak ada error muncul.
 *   2. Kata sandi baru harus ditulis ke `admins`.
 *   3. Kunci cache OTP harus TERPISAH dari flow panel user. Kalau tidak, satu
 *      email di dua tabel berbagi satu OTP, dan panel user bisa mengatur ulang
 *      kata sandi panel admin.
 */
beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'admin']);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Cache::flush();
});

test('permintaan OTP menulis kunci cache milik admin, bukan milik user', function (): void {
    $admin = Admin::factory()->create([
        'email' => 'reset-target@example.test',
        'password' => Hash::make('Password123!'),
    ]);

    $otp = '424242';
    Cache::put(AdminOtp::otpKey($admin->email), $otp, now()->addMinutes(30));

    expect(Cache::get(AdminOtp::otpKey($admin->email)))->toBe($otp)
        // Kunci panel user TIDAK boleh tersentuh.
        ->and(Cache::get('password_reset_otp_'.$admin->email))->toBeNull()
        ->and(Cache::get('otp_verified_for_'.$admin->email))->toBeNull();
});

test('AdminOtp hanya mencari di tabel admins', function (): void {
    Admin::factory()->create(['email' => 'cuma-di-admins@example.test']);
    User::factory()->create(['email' => 'cuma-di-users@example.test']);

    expect(AdminOtp::findAdmin('cuma-di-admins@example.test'))->toBeInstanceOf(Admin::class)
        ->and(AdminOtp::findAdmin('cuma-di-users@example.test'))->toBeNull();
});

test('OTP terverifikasi milik panel user tidak diterima panel admin', function (): void {
    $admin = Admin::factory()->create([
        'email' => 'shared@example.test',
        'password' => Hash::make('Password123!'),
    ]);

    // Simulasikan panel USER yang memverifikasi OTP untuk email yang sama.
    Cache::put('otp_verified_for_'.$admin->email, true, now()->addMinutes(30));

    // Panel admin harus tetap meminta verifikasi sendiri.
    expect(Cache::get(AdminOtp::verifiedKey($admin->email)))->toBeNull();
});

test('kata sandi admin berubah setelah reset, akun lain tidak ikut', function (): void {
    $admin = Admin::factory()->create([
        'email' => 'reset-me@example.test',
        'password' => Hash::make('Password123!'),
    ]);

    $user = User::factory()->create([
        'email' => 'other-shopper@example.test',
        'password' => Hash::make('Password123!'),
    ]);

    $originalUserHash = $user->password;

    // OTP milik admin diverifikasi.
    Cache::put(AdminOtp::verifiedKey($admin->email), true, now()->addMinutes(30));
    Cache::put(AdminOtp::otpKey($admin->email), '111111', now()->addMinutes(30));

    Livewire::withQueryParams(['email' => $admin->email])->test(\App\Filament\Admin\Auth\OtpResetPassword\OtpResetPassword::class)
        ->fillForm(['password' => 'PasswordBaru456!', 'passwordConfirmation' => 'PasswordBaru456!'])
        ->call('resetPassword');

    expect(Hash::check('PasswordBaru456!', $admin->fresh()->password))->toBeTrue()
        ->and(Hash::check('Password123!', $admin->fresh()->password))->toBeFalse()
        // Baris `users` lain tidak boleh tersentuh.
        ->and($user->fresh()->password)->toBe($originalUserHash);

    // Kunci OTP ikut dibersihkan.
    expect(Cache::get(AdminOtp::verifiedKey($admin->email)))->toBeNull()
        ->and(Cache::get(AdminOtp::otpKey($admin->email)))->toBeNull();
});

test('admin bisa login dengan kata sandi baru hasil reset', function (): void {
    $admin = Admin::factory()->create([
        'username' => 'reset-login',
        'password' => Hash::make('Password123!'),
    ]);

    Cache::put(AdminOtp::verifiedKey($admin->email), true, now()->addMinutes(30));
    Cache::put(AdminOtp::otpKey($admin->email), '111111', now()->addMinutes(30));

    Livewire::withQueryParams(['email' => $admin->email])->test(\App\Filament\Admin\Auth\OtpResetPassword\OtpResetPassword::class)
        ->fillForm(['password' => 'PasswordBaru456!', 'passwordConfirmation' => 'PasswordBaru456!'])
        ->call('resetPassword');

    expect(Auth::guard('admin')->attempt([
        'username' => 'reset-login',
        'password' => 'PasswordBaru456!',
    ]))->toBeTrue();

    Auth::guard('admin')->logout();
});

test('akun admin nonaktif tetap tidak bisa login setelah reset kata sandi', function (): void {
    $admin = Admin::factory()->inactive()->create([
        'username' => 'inactive-reset',
        'password' => Hash::make('Password123!'),
    ]);

    Cache::put(AdminOtp::verifiedKey($admin->email), true, now()->addMinutes(30));

    Livewire::withQueryParams(['email' => $admin->email])->test(\App\Filament\Admin\Auth\OtpResetPassword\OtpResetPassword::class)
        ->fillForm(['password' => 'PasswordBaru456!', 'passwordConfirmation' => 'PasswordBaru456!'])
        ->call('resetPassword');

    // Mengganti kata sandi tidak menghidupkan akun yang dinonaktifkan.
    expect(Auth::guard('admin')->attempt([
        'username' => 'inactive-reset',
        'password' => 'PasswordBaru456!',
    ]))->toBeFalse();
});
