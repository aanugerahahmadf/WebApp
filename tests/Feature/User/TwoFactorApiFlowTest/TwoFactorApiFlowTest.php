<?php

namespace Tests\Feature\User\TwoFactorApiFlowTest;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Alur 2FA lewat API, dari setup sampai terbitnya token aplikasi.
 *
 * Alur ini sebelumnya belum pernah dijalankan sama sekali -- test yang ada
 * hanya menyentuh halaman Livewire atau statis. Yang diuji di sini adalah
 * rangkaian yang benar-benar memanggil endpoint:
 *
 *   setup -> verify setup -> login (dapat tantangan) -> verify tantangan -> token
 *
 * Termasuk hal-hal yang paling rawan rusak tapi paling jarang disentuh:
 *
 *  - apakah TOTP dibaca sebagai Base32 polos, bukan teks terenkripsi
 *  - apakah token aplikasi benar-benar TIDAK terbit sebelum 2FA lolos
 *  - apakah kode pemulihan bisa dipakai dua kali (harus tidak)
 *  - apakah challenge token bisa diputar ulang (harus tidak)
 */
class TwoFactorApiFlowTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = '@Superadmin123';

    /** Kode verifikasi 2FA saat sign in. Sama dengan yang dipakai panel web. */
    private const LOGIN_EMAIL_CACHE_KEY = '2fa_email_otp_';

    /** Kode OTP selama setup metode email. Terpisah dari yang di atas. */
    private const SETUP_EMAIL_CACHE_KEY = '2fa_setup_otp_';

    private function user(): User
    {
        return User::factory()->create([
            'email' => 'twofa@example.com',
            'username' => 'twofatest',
            'password' => bcrypt(self::PASSWORD),
        ]);
    }

    private function totpAt(string $secret): string
    {
        return (new Google2FA)->getCurrentOtp($secret);
    }

    /**
     * Jalankan setup + verify TOTP sampai tuntas, lalu kembalikan secret.
     */
    private function enableTotp(User $user): string
    {
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/security/two-factor/setup', ['type' => 'totp'])
            ->assertOk();

        $secret = $this->freshSecret($user);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/security/two-factor/setup/verify', [
                'type' => 'totp',
                'code' => $this->totpAt($secret),
            ])
            ->assertOk();

        return $secret;
    }

    private function freshSecret(User $user): string
    {
        return (string) $user->fresh()->two_factor_secret;
    }

    /**
     * Jalankan setup + verify email sampai tuntas, sampai two_factor_type
     * terisi 'email'.
     */
    private function enableEmail(User $user): void
    {
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/security/two-factor/setup', ['type' => 'email'])
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/security/two-factor/setup/verify', [
                'type' => 'email',
                'code' => (string) Cache::get(self::SETUP_EMAIL_CACHE_KEY.$user->id),
            ])
            ->assertOk();
    }

    private function login(): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/login', [
            'login' => 'twofa@example.com',
            'password' => self::PASSWORD,
        ]);
    }

    /* ================================================================
     | SETUP: BENTUK SECRET
     | ================================================================ */

    /**
     * Setup TOTP harus menyimpan Base32 polos, bukan Base32 terenkripsi.
     *
     * Kolom two_factor_secret dipakai paket multi-2fa untuk dua hal berbeda:
     * Base32 polos untuk TOTP (UsingTwoFA.php:49) dan encrypt(kode) untuk OTP
     * email (UsingTwoFA.php:35). Kalau API ikut memakai encrypt() di sini,
     * verifyKey() akan selalu gagal karena isinya bukan Base32.
     */
    public function test_setup_totp_stores_plain_base32_secret(): void
    {
        $user = $this->user();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/security/two-factor/setup', ['type' => 'totp'])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $secret = $this->freshSecret($user);

        $this->assertNotEmpty($secret, 'Secret TOTP harus tersimpan di two_factor_secret.');

        // Base32 hanya berisi A-Z dan 2-7. Kalau terenkripsi, akan muncul
        // karakter base64 (/, +, =) yang tidak ada di Base32.
        $this->assertMatchesRegularExpression(
            '/^[A-Z2-7]+=*$/',
            $secret,
            'Secret TOTP harus Base32 polos, bukan hasil encrypt().'
        );

        $this->assertTrue(
            (new Google2FA)->verifyKey($secret, $this->totpAt($secret)),
            'Secret yang disimpan harus memverifikasi kodenya sendiri.'
        );
    }

    public function test_setup_totp_does_not_confirm_the_account_yet(): void
    {
        $user = $this->user();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/security/two-factor/setup', ['type' => 'totp'])
            ->assertOk();

        $user->refresh();

        // Secret sudah ada, tapi 2FA belum aktif: belum ada yang membuktikan
        //engguna benar-benar punya aplikasi itu.
        $this->assertNotNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);
        $this->assertFalse((bool) $user->two_factor_enabled);
    }

    public function test_setup_rejects_unknown_type(): void
    {
        $this->actingAs($this->user(), 'sanctum')
            ->postJson('/api/security/two-factor/setup', ['type' => 'sms'])
            ->assertStatus(422);
    }

    public function test_setup_requires_authentication(): void
    {
        $this->postJson('/api/security/two-factor/setup', ['type' => 'totp'])
            ->assertStatus(401);
    }

    /**
     * Metode yang SUDAH pernah diverifikasi tidak boleh dipilih ulang.
     *
     * Sama seperti TwoFactorySetup::setup() (baris 317): guard-nya
     * `$state === $this->user->two_factor_type?->value`, jadi yang dicegah
     * hanya metode yang sudah terverifikasi -- persis rule `verified_before`
     * di sana.
     */
    public function test_cannot_setup_a_method_that_is_already_verified(): void
    {
        $user = $this->user();
        $this->enableTotp($user);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/security/two-factor/setup', ['type' => 'totp'])
            ->assertStatus(422);
    }

    /**
     * Tapi setup ulang SEBELUM verifikasi itu diizinkan, dan hasilnya
     * secret baru.
     *
     * Ini bukan bug: TwoFactorySetup juga mengizinkan pemilihan ulang selama
     * metode itu belum confirmed, karena yang di-disable di form web hanya
     * opsi yang nilainya sama dengan two_factor_type yang sudah terisi.
     *
     * Efeknya perlu dikunci eksplisit: membuka halaman setup TOTP dua kali
     * menghasilkan QR yang berbeda, sehingga entri di aplikasi autentikator
     * milik pengguna menjadi tidak berlaku. Kalau suatu saat ini diubah jadi
     * menolak, test ini yang akan menangkap perubahannya.
     */
    public function test_repeated_unverified_setup_rotates_the_secret(): void
    {
        $user = $this->user();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/security/two-factor/setup', ['type' => 'totp'])
            ->assertOk();

        $first = $this->freshSecret($user);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/security/two-factor/setup', ['type' => 'totp'])
            ->assertOk();

        $second = $this->freshSecret($user);

        $this->assertNotSame($first, $second, 'Setup ulang harus mengeluarkan secret baru.');
        $this->assertNull(
            $user->fresh()->two_factor_confirmed_at,
            'Selalu belum terverifikasi, jadi 2FA belum aktif.'
        );
    }

    /* ================================================================
     | VERIFY SETUP
     | ================================================================ */

    public function test_verify_setup_totp_enables_two_factor_and_returns_backup_codes(): void
    {
        $user = $this->user();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/security/two-factor/setup', ['type' => 'totp'])
            ->assertOk();

        $secret = $this->freshSecret($user);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/security/two-factor/setup/verify', [
                'type' => 'totp',
                'code' => $this->totpAt($secret),
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.two_factor_type', 'totp')
            ->assertJsonCount(10, 'data.backup_codes');

        $user->refresh();

        $this->assertTrue($user->two_factor_enabled);
        $this->assertNotNull($user->two_factor_confirmed_at);
        $this->assertSame(
            $secret,
            $this->freshSecret($user),
            'Secret tidak boleh berubah saat verifikasi.'
        );
        $this->assertDatabaseCount('backup_codes', 10);
    }

    public function test_verify_setup_rejects_wrong_totp(): void
    {
        $user = $this->user();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/security/two-factor/setup', ['type' => 'totp']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/security/two-factor/setup/verify', [
                'type' => 'totp',
                'code' => '000000',
            ])
            ->assertStatus(422);

        $user->refresh();

        $this->assertNull($user->two_factor_confirmed_at);
        $this->assertFalse((bool) $user->two_factor_enabled);
    }

    /* ================================================================
     | TABRAKAN KODE EMAIL: SETUP vs SIGN IN
     | ================================================================ */

    /**
     * Kode setup email dan kode 2FA saat sign in harus TIDAK saling menimpa.
     *
     * Verify 2FA di aplikasi dan di panel web SENGAJA berbagi slot:
     *
     *   AuthController::TWO_FACTOR_EMAIL_CACHE_KEY  = '2fa_email_otp_'
     *   OtpEmailOrTwoFactory::EMAIL_CACHE_KEY       = '2fa_email_otp_'
     *
     * satu kode yang dikirim ke email harus berlaku di kedua sisi, jadi satu
     * slot memang perlu dipakai bersama.
     *
     * SETUP tidak boleh ikut berbagi slot itu:
     *
     *   SecurityController::SETUP_EMAIL_CACHE_KEY   = '2fa_setup_otp_'
     *
     * Skenario yang diuji:
     *   1. akun sudah 2FA lewat email, lalu Sign In di perangkat A
     *      -> tantangan 2FA meng-cache kode A
     *   2. pengguna membuka halaman Two Factor Setup di perangkat B dan
     *      menekan "Kirim Ulang OTP" (resendTwoFactorSetupOtp)
     *   3. kalau slot-nya sama, kode A tertimpa kode baru dari langkah 2
     *   4. pengguna di perangkat A sudah menerima email untuk kode A, tapi
     *      kodenya sudah tidak berlaku lagi
     *
     * Kalau test ini gagal, mengetik ulang kode di perangkat A tidak akan
     * pernah berhasil, dan tidak ada pesan apa pun yang menjelaskan kenapa.
     */
    public function test_setup_resend_does_not_invalidate_a_pending_sign_in_code(): void
    {
        $user = $this->user();
        $this->enableEmail($user);

        // Langkah 1: tantangan sign in meng-cache kode.
        $challenge = $this->login()
            ->assertOk()
            ->assertJsonPath('status', 'two_factor_required')
            ->json('data.challenge_token');

        $loginCode = Cache::get(self::LOGIN_EMAIL_CACHE_KEY.$user->id);
        $this->assertNotNull($loginCode);

        // Langkah 2-3: kirim ulang dari halaman setup mengambil alih slotnya.
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/security/two-factor/setup/resend')
            ->assertOk();

        $this->assertSame(
            (string) $loginCode,
            (string) Cache::get(self::LOGIN_EMAIL_CACHE_KEY.$user->id),
            'Kirim ulang di halaman setup tidak boleh membatalkan kode Sign In yang masih menunggu.'
        );

        // Langkah 4: dan kode itu sendiri harus tetap diterima.
        $this->postJson('/api/auth/two-factor/verify', [
            'challenge_token' => $challenge,
            'method' => 'email',
            'code' => (string) $loginCode,
        ])->assertOk();
    }

    /**
     * Kode email harus hidup di cache, bukan menimpa two_factor_secret.
     *
     * Regression yang paling merusak kalau salah: paket multi-2fa menulis
     * encrypt(kode) ke two_factor_secret saat mengirim OTP email
     * (UsingTwoFA.php:35). Kalau API melakukan hal itu pada akun yang 2FA-nya
     * sudah aktif lewat TOTP, secret Base32-nya hilang permanen --
     * aplikasi autentikator milik pengguna mati, dan satu-satunya jalan keluar
     * tinggal kode cadangan.
     */
    public function test_email_otp_does_not_clobber_the_totp_secret(): void
    {
        $user = $this->user();
        $secret = $this->enableTotp($user);

        $this->login()->assertOk()->assertJsonPath('status', 'two_factor_required');

        $this->assertSame(
            $secret,
            $this->freshSecret($user),
            'two_factor_secret tidak boleh berubah selama alur 2FA TOTP.'
        );

        // Metode TOTP tidak mengirim email sama sekali, jadi kedua slot
        // kode harus kosong.
        $this->assertNull(
            Cache::get(self::LOGIN_EMAIL_CACHE_KEY.$user->id),
            'Tantangan TOTP tidak boleh meng-cache kode email.'
        );
        $this->assertNull(
            Cache::get(self::SETUP_EMAIL_CACHE_KEY.$user->id),
            'Metode TOTP tidak mengirim email, jadi tidak boleh ada kode setup di cache.'
        );
    }

    public function test_verify_setup_email_enables_and_clears_the_secret(): void
    {
        $user = $this->user();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/security/two-factor/setup', ['type' => 'email'])
            ->assertOk();

        $code = Cache::get(self::SETUP_EMAIL_CACHE_KEY.$user->id);
        $this->assertNotNull($code, 'Kode setup email harus tersimpan di cache.');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/security/two-factor/setup/verify', [
                'type' => 'email',
                'code' => (string) $code,
            ])
            ->assertOk()
            ->assertJsonPath('data.two_factor_type', 'email');

        $user->refresh();

        $this->assertTrue($user->two_factor_enabled);
        $this->assertNotNull($user->two_factor_confirmed_at);
        $this->assertNull(
            $user->two_factor_secret,
            'Metode email tidak memakai secret, jadi kolomnya harus dikosongkan.'
        );
        $this->assertNull(
            Cache::get(self::SETUP_EMAIL_CACHE_KEY.$user->id),
            'Kode setup yang sudah dipakai harus dihapus dari cache.'
        );
    }

    public function test_verify_setup_email_rejects_wrong_code(): void
    {
        $user = $this->user();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/security/two-factor/setup', ['type' => 'email']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/security/two-factor/setup/verify', [
                'type' => 'email',
                'code' => '000000',
            ])
            ->assertStatus(422);

        $this->assertFalse((bool) $user->fresh()->two_factor_enabled);
    }

    public function test_resend_setup_email_replaces_the_cached_code(): void
    {
        $user = $this->user();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/security/two-factor/setup', ['type' => 'email']);

        $first = (string) Cache::get(self::SETUP_EMAIL_CACHE_KEY.$user->id);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/security/two-factor/setup/resend')
            ->assertOk();

        $second = (string) Cache::get(self::SETUP_EMAIL_CACHE_KEY.$user->id);

        $this->assertNotSame(
            $first,
            $second,
            'Kirim ulang harus mengeluarkan kode baru, bukan memakai kode lama.'
        );
    }

    /* ================================================================
     | TANTANGAN SAAT SIGN IN
     | ================================================================ */

    /**
     * Ini regresi keamanan yang paling penting.
     *
     * Sebelum ada tantangan 2FA, login() langsung menerbitkan token begitu
     * kata sandi cocok -- jadi 2FA yang aktif tidak pernah diminta buktinya.
     * Test ini mengunci bahwa token TIDAK boleh terbit pada respons login
     * ketika 2FA aktif.
     */
    public function test_login_does_not_issue_a_token_when_two_factor_is_active(): void
    {
        $user = $this->user();
        $this->enableTotp($user);

        $response = $this->login();

        $response->assertOk()
            ->assertJsonPath('status', 'two_factor_required')
            ->assertJsonPath('data.method', 'authenticator')
            ->assertJsonStructure([
                'data' => ['challenge_token', 'method', 'two_factor_type', 'email', 'expires_in'],
            ]);

        $body = $response->json();

        $this->assertArrayNotHasKey('token', $body, 'Token tidak boleh terbit sebelum 2FA lolos.');
        $this->assertArrayNotHasKey('token', $body['data'] ?? []);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_without_two_factor_still_returns_a_token(): void
    {
        $this->user();

        $this->login()
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_wrong_password_never_starts_a_two_factor_challenge(): void
    {
        $user = $this->user();
        $this->enableTotp($user);

        $this->postJson('/api/login', [
            'login' => 'twofa@example.com',
            'password' => 'password123456',
        ])->assertStatus(401);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_full_totp_flow_from_setup_to_token(): void
    {
        $user = $this->user();
        $secret = $this->enableTotp($user);

        $challenge = $this->login()->assertOk()->json('data.challenge_token');

        $this->postJson('/api/auth/two-factor/verify', [
            'challenge_token' => $challenge,
            'method' => 'authenticator',
            'code' => $this->totpAt($secret),
        ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['data' => ['token', 'user']]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_challenge_token_is_single_use(): void
    {
        $user = $this->user();
        $secret = $this->enableTotp($user);

        $challenge = $this->login()->json('data.challenge_token');

        $this->postJson('/api/auth/two-factor/verify', [
            'challenge_token' => $challenge,
            'method' => 'authenticator',
            'code' => $this->totpAt($secret),
        ])->assertOk();

        // Kalau challenge tidak dihapus setelah dipakai, ia bisa diputar ulang
        // tanpa kata sandi.
        $this->postJson('/api/auth/two-factor/verify', [
            'challenge_token' => $challenge,
            'method' => 'authenticator',
            'code' => $this->totpAt($secret),
        ])->assertStatus(401);
    }

    public function test_unknown_challenge_token_is_rejected(): void
    {
        $this->enableTotp($this->user());

        $this->postJson('/api/auth/two-factor/verify', [
            'challenge_token' => 'token-palsu',
            'method' => 'authenticator',
            'code' => '123456',
        ])->assertStatus(401);
    }

    public function test_verify_two_factor_rejects_a_wrong_code_without_issuing_a_token(): void
    {
        $user = $this->user();
        $this->enableTotp($user);

        $challenge = $this->login()->json('data.challenge_token');

        $this->postJson('/api/auth/two-factor/verify', [
            'challenge_token' => $challenge,
            'method' => 'authenticator',
            'code' => '000000',
        ])->assertStatus(422);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_verify_two_factor_requires_a_challenge_token(): void
    {
        $this->postJson('/api/auth/two-factor/verify', ['code' => '123456'])
            ->assertStatus(422);
    }

    /* ================================================================
     | EMAIL & KODE PEMULIHAN
     | ================================================================ */

    public function test_full_email_flow_from_setup_to_token(): void
    {
        $user = $this->user();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/security/two-factor/setup', ['type' => 'email'])
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/security/two-factor/setup/verify', [
                'type' => 'email',
                'code' => (string) Cache::get(self::SETUP_EMAIL_CACHE_KEY.$user->id),
            ])
            ->assertOk();

        $challenge = $this->login()
            ->assertOk()
            ->assertJsonPath('data.method', 'email')
            ->json('data.challenge_token');

        $code = Cache::get(self::LOGIN_EMAIL_CACHE_KEY.$user->id);
        $this->assertNotNull($code, 'Login harus meng-cache kode email untuk verifikasi 2FA.');

        $this->postJson('/api/auth/two-factor/verify', [
            'challenge_token' => $challenge,
            'method' => 'email',
            'code' => (string) $code,
        ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_email_challenge_code_is_single_use(): void
    {
        $user = $this->user();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/security/two-factor/setup', ['type' => 'email']);
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/security/two-factor/setup/verify', [
                'type' => 'email',
                'code' => (string) Cache::get(self::SETUP_EMAIL_CACHE_KEY.$user->id),
            ])
            ->assertOk();

        $challenge = $this->login()->json('data.challenge_token');
        $code = (string) Cache::get(self::LOGIN_EMAIL_CACHE_KEY.$user->id);

        $this->postJson('/api/auth/two-factor/verify', [
            'challenge_token' => $challenge,
            'method' => 'email',
            'code' => $code,
        ])->assertOk();

        $this->postJson('/api/auth/two-factor/verify', [
            'challenge_token' => $challenge,
            'method' => 'email',
            'code' => $code,
        ])->assertStatus(401);
    }

    public function test_recovery_code_grants_access(): void
    {
        $user = $this->user();
        $this->enableTotp($user);

        $codes = $this->backupCodes($user);
        $this->assertCount(10, $codes);

        $challenge = $this->login()->json('data.challenge_token');

        $this->postJson('/api/auth/two-factor/verify', [
            'challenge_token' => $challenge,
            'method' => 'recovery',
            'code' => $codes[0],
        ])->assertOk()->assertJsonPath('status', 'success');
    }

    /* ================================================================
     | KONTRAK TIPE UNTUK KLIEN
     | ================================================================ */

    /**
     * two_factor_enabled harus keluar sebagai JSON boolean sejati.
     *
     * Kolomnya pernah tanpa cast, jadi nilainya balik dari database sebagai
     * int 0/1 lalu diteruskan apa adanya oleh twoFactorStatus(). Di sisi Dart
     * field-nya `bool _twoFactorEnabled`, dan dynamic->bool yang tidak cocok
     * adalah RUNTIME TypeError, bukan konversi diam-diam -- halaman Two
     * Factor Settings gagal saat dimuat untuk setiap akun dengan 2FA aktif.
     *
     * Toggle-nya tidak terpengaruh karena twoFactorToggle() mengembalikan
     * bool PHP asli, jadi gejalanya hanya muncul di pemuatan halaman. Itu
     * sebabnya test ini perlu ada: tanpa memuat halaman, bugnya tak terlihat.
     *
     * assertJsonPath dengan nilai literal bool adalah yang mengunci ini --
     * 1 != true di perbandingan ketat PHPUnit.
     */
    public function test_status_returns_two_factor_enabled_as_a_json_boolean(): void
    {
        $user = $this->user();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/security/two-factor/status')
            ->assertOk()
            ->assertJsonPath('data.two_factor_enabled', false);

        $this->enableTotp($user);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/security/two-factor/status')
            ->assertOk()
            ->assertJsonPath('data.two_factor_enabled', true);

        // Guard tambahan: pastikan yang benar-benar bool, bukan 1 yang
        // kebetulan lolos di JsonPath.
        $this->assertIsBool($response->json('data.two_factor_enabled'));

        $raw = $response->getContent();
        $this->assertStringContainsString(
            '"two_factor_enabled":true',
            $raw,
            'JSON harus berisi true, bukan 1.'
        );
    }

    /**
     * Kode pemulihan harus SEKALI PAKAI.
     *
     * Kalau tidak, satu kode yang bocor dari catatan atau foto layar memberi
     * akses berulang tanpa batas.
     */
    public function test_recovery_code_cannot_be_reused(): void
    {
        $user = $this->user();
        $this->enableTotp($user);

        $codes = $this->backupCodes($user);

        $first = $this->login()->json('data.challenge_token');

        $this->postJson('/api/auth/two-factor/verify', [
            'challenge_token' => $first,
            'method' => 'recovery',
            'code' => $codes[0],
        ])->assertOk();

        $second = $this->login()->json('data.challenge_token');

        $this->postJson('/api/auth/two-factor/verify', [
            'challenge_token' => $second,
            'method' => 'recovery',
            'code' => $codes[0],
        ])->assertStatus(422);
    }

    public function test_recovery_code_is_case_insensitive(): void
    {
        $user = $this->user();
        $this->enableTotp($user);

        $codes = $this->backupCodes($user);
        $challenge = $this->login()->json('data.challenge_token');

        $this->postJson('/api/auth/two-factor/verify', [
            'challenge_token' => $challenge,
            'method' => 'recovery',
            'code' => strtolower($codes[0]),
        ])->assertOk();
    }

    public function test_resend_two_factor_code_requires_a_valid_challenge(): void
    {
        $this->enableTotp($this->user());

        $this->postJson('/api/auth/two-factor/resend', ['challenge_token' => 'palsu'])
            ->assertStatus(401);

        $this->postJson('/api/auth/two-factor/resend', [])
            ->assertStatus(422);
    }

    /* ================================================================
     | MATIKAN 2FA
     | ================================================================ */

    public function test_setup_none_disables_two_factor_and_deletes_backup_codes(): void
    {
        $user = $this->user();
        $this->enableTotp($user);

        $this->assertDatabaseCount('backup_codes', 10);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/security/two-factor/setup', ['type' => 'none'])
            ->assertOk()
            ->assertJsonPath('data.type', 'none');

        $user->refresh();

        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);
        $this->assertFalse((bool) $user->two_factor_enabled);
        $this->assertDatabaseCount('backup_codes', 0);
    }

    public function test_login_returns_a_token_again_after_two_factor_is_disabled(): void
    {
        $user = $this->user();
        $this->enableTotp($user);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/security/two-factor/setup', ['type' => 'none'])
            ->assertOk();

        $this->login()
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_status_reports_two_factor_type(): void
    {
        $user = $this->user();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/security/two-factor/status')
            ->assertOk()
            ->assertJsonPath('data.two_factor_type', 'none');

        $this->enableTotp($user);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/security/two-factor/status')
            ->assertOk()
            ->assertJsonPath('data.two_factor_type', 'totp');
    }

    /** Kode cadangan aktif milik user, dalam bentuk yang bisa dikirim ke API. */
    private function backupCodes(User $user): array
    {
        return \App\Models\BackupCode\BackupCode::where('user_id', $user->id)
            ->where('used', false)
            ->pluck('code')
            ->all();
    }
}