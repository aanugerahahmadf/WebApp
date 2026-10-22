<?php

/**
 * DeviceTrustService + halaman DeviceTrustPrompt.
 *
 * Semua perilaku yang TIDAK terlihat dari membaca kode:
 *
 *   1. Perangkat BARU (User-Agent belum pernah dipakai akun itu) dikirim
 *      notifikasi "Perangkat Baru -- Apakah Ini Anda?" dengan tombol yang
 *      membukanya ke halaman Tinjau -- ala Google/Instagram.
 *   2. Sign in ulang dari User-Agent yang SAMA tidak menimbulkan baris
 *      kedua.
 *   3. Perangkat yang sudah dijawab "Ya, Ini Saya" tidak dikirim prompt
 *      lagi walau barisnya masih ada.
 *   4. "Bukan saya" (deny): token HP (Sanctum) + baris trust_devices milik
 *      paket 2FA ikut habis; baris trusted_devices ditandai "denied".
 *   5. Perangkat milik user A tidak bisa ditinjau user B.
 */

use App\Filament\User\Pages\DeviceTrustPrompt\DeviceTrustPrompt;
use App\Models\TrustedDevice\TrustedDevice;
use App\Models\User\User;
use App\Support\DeviceTrust\DeviceTrustService;
use Filament\Facades\Filament;
use Illuminate\Notifications\DatabaseNotification;
use MixCode\FilamentMulti2fa\Enums\TwoFactorAuthType;
use MixCode\FilamentMulti2fa\Models\TrustDevice;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('user'));
});

/**
 * Set User-Agent request aktif supaya fingerprint perangkat di dalam
 * DeviceTrustService konsisten dengan simulasi perangkat itu.
 */
function aktifkanPerangkat(string $userAgent): void
{
    request()->headers->set('User-Agent', $userAgent);
}

const UA_CHROME_WINDOWS = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

const UA_ANDROID_CHROME = 'Mozilla/5.0 (Linux; Android 13; SM-S901B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Mobile Safari/537.36';

/**
 * Geser last_notified_at ke belakang supaya guard "notif baru dikirim
 * beberapa detik lalu" tidak menghalangi pemanggilan berikutnya.
 */
function tandaiNotifikasiPurba(TrustedDevice $device): void
{
    $device->forceFill(['last_notified_at' => now()->subMinute()])->save();
}

/*
 * ------------------------------------------------------------------
 * Jalur API mobile (AuthController + SecurityController)
 * ------------------------------------------------------------------
 */

it('login API dari HP baru mencatat perangkat dan mengirim prompt', function (): void {
    $user = User::factory()->create(['password' => bcrypt('@Superadmin123')]);

    // Jalur API tidak memicu Login event -- hook DeviceTrustService ada di
    // AuthController::login(), jadi dites lewat HTTP nyata.
    $this->postJson('/api/login', [
        'login' => $user->email,
        'password' => '@Superadmin123',
    ], ['User-Agent' => UA_ANDROID_CHROME])
        ->assertSuccessful();

    $device = TrustedDevice::where('user_id', $user->id)->first();

    expect($device)->not->toBeNull()
        ->and($device->trusted_at)->toBeNull();

    // Prompt perangkat baru sampai ke inbox notifikasinya.
    $promptTitle = \App\Services\PlatformNotificationService\PlatformNotificationService::withRecipientLocale(
        $user,
        fn () => __('Perangkat Baru -- Apakah Ini Anda?'),
    );

    expect(DatabaseNotification::query()
        ->where('notifiable_id', $user->id)
        ->where('data->title', $promptTitle)
        ->count())->toBe(1);
});

it('verify dari HP menandai perangkat dipercaya', function (): void {
    $user = User::factory()->create();

    aktifkanPerangkat(UA_ANDROID_CHROME);
    $device = DeviceTrustService::recognize($user)->device;

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/security/trusted-devices/{$device->id}/verify")
        ->assertSuccessful()
        ->assertJson(['status' => 'success']);

    expect($device->fresh()->trusted_at)->not->toBeNull()
        ->and($device->fresh()->denied_at)->toBeNull();
});

it('reject dari HP mencabut token pemanggil', function (): void {
    $user = User::factory()->create();
    $user->createToken('auth_token');

    aktifkanPerangkat(UA_ANDROID_CHROME);
    $device = DeviceTrustService::recognize($user)->device;

    $paketDevice = TrustDevice::create([
        'user_id' => $user->id,
        'device_name' => UA_ANDROID_CHROME,
        'device_signature' => 'sig-hp',
        'expires_at' => now()->addDay(),
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/security/trusted-devices/{$device->id}/reject")
        ->assertSuccessful()
        ->assertJson(['status' => 'success', 'logged_out' => true]);

    expect($device->fresh()->denied_at)->not->toBeNull()
        ->and($user->tokens()->count())->toBe(0)
        ->and(TrustDevice::find($paketDevice->id))->toBeNull();
});

it('reject perangkat milik orang lain tidak mengubah apa pun', function (): void {
    $pemilik = User::factory()->create();
    $penyerang = User::factory()->create();

    aktifkanPerangkat(UA_CHROME_WINDOWS);
    $device = DeviceTrustService::recognize($pemilik)->device;

    $this->actingAs($penyerang, 'sanctum')
        // 404, bukan 403: controller menyaring per user_id dulu, jadi
        // keberadaan perangkat milik orang lain tidak diketahui penyerang.
        ->postJson("/api/security/trusted-devices/{$device->id}/reject")
        ->assertNotFound();

    expect($device->fresh()->denied_at)->toBeNull();
});

/*
 * ------------------------------------------------------------------
 * Pengenalan perangkat + notifikasi
 * ------------------------------------------------------------------
 */

it('perangkat baru dikirim notifikasi Apakah Ini Anda dan mencatat barisnya', function (): void {
    $user = User::factory()->create();

    aktifkanPerangkat(UA_CHROME_WINDOWS);
    $result = DeviceTrustService::recognize($user);

    expect($result->isNew)->toBeTrue()
        ->and(TrustedDevice::where('user_id', $user->id)->count())->toBe(1);

    $device = TrustedDevice::where('user_id', $user->id)->first();
    expect($device->device_fingerprint)->toBe(DeviceTrustService::fingerprint())
        ->and($device->trusted_at)->toBeNull()
        // 127.0.0.1 lewat GeoLocationService tanpa menyentuh jaringan.
        ->and($device->last_ip)->toBe('127.0.0.1');

    // Notifikasinya satu, judulnya persis pertanyaan perangkat baru, dan
    // tombolnya mengarah ke halaman tinjau.
    //
    // Judul dibandingkan lewat PENERIMA (withRecipientLocale pakai
    // user->lang), bukan locale test saat itu -- kalau tidak, judul yang
    // tersimpan (en) pasti tidak cocok dengan __('...')
    // (id) dari pengetestan ini, dan query data->title gagal.
    $promptTitle = \App\Services\PlatformNotificationService\PlatformNotificationService::withRecipientLocale(
        $user,
        fn () => __('Perangkat Baru -- Apakah Ini Anda?'),
    );

    /** @var DatabaseNotification $notification */
    $notification = DatabaseNotification::query()
        ->where('notifiable_id', $user->id)
        ->where('data->title', $promptTitle)
        ->sole();

    $promptUrl = DeviceTrustPrompt::getUrl(['id' => $device->id], panel: 'user');

    expect(collect($notification->data['actions'] ?? []))
        ->pluck('url')
        ->filter()
        ->values()
        ->toContain((string) $promptUrl);
});

it('sign in ulang dari User-Agent yang sama tidak menggandakan baris', function (): void {
    $user = User::factory()->create();

    aktifkanPerangkat(UA_CHROME_WINDOWS);
    DeviceTrustService::recognize($user);

    aktifkanPerangkat(UA_CHROME_WINDOWS);
    $second = DeviceTrustService::recognize($user);

    expect($second->isNew)->toBeFalse()
        ->and(TrustedDevice::where('user_id', $user->id)->count())->toBe(1);
});

it('perangkat yang sudah dipercaya tidak dikirim prompt lagi', function (): void {
    $user = User::factory()->create();

    aktifkanPerangkat(UA_CHROME_WINDOWS);
    DeviceTrustService::recognize($user);

    $device = TrustedDevice::where('user_id', $user->id)->first();
    DeviceTrustService::trust($device, $user);

    tandaiNotifikasiPurba($device);

    DeviceTrustService::recognize($user);

    // Notifikasi "Apakah Ini Anda?" hanya satu -- diterbitkan saat
    // perangkatnya masih menunggu jawaban.
    expect(DatabaseNotification::count())->toBe(1)
        ->and($device->fresh()->trusted_at)->not->toBeNull();
});

/*
 * ------------------------------------------------------------------
 * Jawaban "Bukan saya"
 * ------------------------------------------------------------------
 */

it('deny menghabiskan token HP dan perangkat tepercaya milik paket 2fa', function (): void {
    $user = User::factory()->create();
    $user->createToken('auth_token');
    $user->update(['two_factor_type' => TwoFactorAuthType::Totp, 'two_factor_confirmed_at' => now()]);
    $user->generateTwoFactorAuthenticatorAppOTPCode();

    $paketDevice = TrustDevice::create([
        'user_id' => $user->id,
        'device_name' => UA_ANDROID_CHROME,
        'device_signature' => 'sig-123',
        'expires_at' => now()->addDay(),
    ]);

    aktifkanPerangkat(UA_ANDROID_CHROME);
    $result = DeviceTrustService::recognize($user);

    DeviceTrustService::deny($result->device, $user);

    expect($result->device->fresh()->denied_at)->not->toBeNull()
        ->and($user->tokens()->count())->toBe(0)
        ->and(TrustDevice::find($paketDevice->id))->toBeNull();

    // Baris trusted_devices TIDAK dihapus -- ia jadi catatan historis
    // "dipatok bukan Anda" lewat kolom denied_at.
    expect(TrustedDevice::find($result->device->id))->not->toBeNull();
});

it('deny perangkat milik orang lain ditolak', function (): void {
    $pemilik = User::factory()->create();
    $penyerang = User::factory()->create();

    aktifkanPerangkat(UA_CHROME_WINDOWS);
    $pemilikDevice = DeviceTrustService::recognize($pemilik)->device;

    DeviceTrustService::deny($pemilikDevice, $penyerang);
})->throws(HttpException::class);

/*
 * ------------------------------------------------------------------
 * Halaman DeviceTrustPrompt
 * ------------------------------------------------------------------
 */

it('halaman tinjau perangkat 404 untuk user lain', function (): void {
    $pemilik = User::factory()->verified()->create();
    $orangLain = User::factory()->verified()->create();

    aktifkanPerangkat(UA_CHROME_WINDOWS);
    $device = DeviceTrustService::recognize($pemilik)->device;

    $url = DeviceTrustPrompt::getUrl(['id' => $device->id], panel: 'user');

    $this->actingAs($orangLain)->get($url)->assertNotFound();
});

it('halaman tinjau perangkat terbuka untuk pemiliknya', function (): void {
    $pemilik = User::factory()->verified()->create();

    aktifkanPerangkat(UA_CHROME_WINDOWS);
    $device = DeviceTrustService::recognize($pemilik)->device;

    $url = DeviceTrustPrompt::getUrl(['id' => $device->id], panel: 'user');

    // GET kedua-actingAs dalam satu test instance tidak reliable
    // (AuthenticationException di session array), jadi pemiliknya dites
    // sendiri dalam test terpisah.
    $this->actingAs($pemilik)->get($url)->assertOk();
});
