<?php

namespace App\Http\Controllers\Api\User\AuthController;

use App\Http\Controllers\Controller;
use App\Mail\OtpMail\OtpMail;
use App\Models\BackupCode\BackupCode;
use App\Models\User\User;
use App\Models\WhatsappOtp\WhatsappOtp;
use App\Services\StorageService\StorageService;
use App\Support\PasswordPolicy\PasswordPolicy;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use MixCode\FilamentMulti2fa\Enums\TwoFactorAuthType;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    /**
     * Masa berlaku OTP lupa kata sandi (menit).
     *
     * 30, mengikuti OtpRequestPasswordReset::request() dan masa berlaku
     * penanda di VerifyOtp::verify() di panel Filament. API ini pernah
     * memakai 5 menit -- 6 kali lebih pendek dari web -- sehingga pengguna
     * yang membaca email-nya dengan telat gagal despite masih di bawah
     * tenggat yang sama di web.
     *
     * Tidak boleh diubah tanpa mengubah ketiga tempat sekaligus:
     * forgotPassword() (menerbitkan), verifyOtp() (memverifikasi), dan
     * resetPassword() (kivingga penanda 30 menit). Kalau hanya satu yang
     * berubah, ada jeda di mana kode dianggap belum sah padahal baru
     * saja dikirim.
     */
    public const OTP_FORGOT_PASSWORD_TTL_MINUTES = 30;

    /** Masa berlaku OTP verifikasi email / Google (menit). */
    public const OTP_VERIFY_TTL_MINUTES = 5;

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'mid_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'username' => 'required|string|max:255|unique:users|alpha_dash',
            'email' => 'required|string|email|max:255|unique:users',
            'whatsapp' => 'nullable|string|max:255',
            'ktp_number' => 'nullable|string|max:20|unique:users,ktp_number',
            'passport_number' => 'nullable|string|max:20|unique:users,passport_number',
            'sim_number' => 'nullable|string|max:20|unique:users,sim_number',
            'npwp_number' => 'nullable|string|max:20|unique:users,npwp_number',
            'identity_type' => 'nullable|string|in:ktp,passport,sim,npwp|max:20',
            'birth_place' => 'nullable|string|max:255',
            'birth_date' => 'nullable|date',
            'ktp_photo' => 'nullable|image|max:2048',
            'selfie_photo' => 'nullable|image|max:5120',
            'face_scan_photo' => 'nullable|image|max:5120',
            'liveness_completed' => 'nullable|boolean',
            'country' => 'nullable|string|max:255',
            'province_id' => 'nullable|exists:indonesia_provinces,id',
            'city_id' => 'nullable|exists:indonesia_cities,id',
            'district_id' => 'nullable|exists:indonesia_districts,id',
            'village_id' => 'nullable|exists:indonesia_villages,id',
            'province_name' => 'nullable|string|max:255',
            'city_name' => 'nullable|string|max:255',
            'district_name' => 'nullable|string|max:255',
            'village_name' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:10',
            'gender' => 'nullable|string|max:255',
            'religion' => 'nullable|string|max:50',
            'marital_status' => 'nullable|string|max:50',
            'mother_name' => 'nullable|string|max:255',
            'occupation' => 'nullable|string|max:100',
            'income_range' => 'nullable|string|max:50',
            'source_of_funds' => 'nullable|string|max:100',
            'address' => 'nullable|string',
            'password' => PasswordPolicy::confirmedRules(),
            'profile_photo' => 'nullable|image|max:10240',
            'avatar_url' => 'nullable|string|max:500',
        ], [
            'ktp_number.unique' => 'Nomor KTP sudah terdaftar oleh pengguna lain.',
            'passport_number.unique' => 'Nomor Passport sudah terdaftar oleh pengguna lain.',
            'sim_number.unique' => 'Nomor SIM sudah terdaftar oleh pengguna lain.',
            'npwp_number.unique' => 'Nomor NPWP sudah terdaftar oleh pengguna lain.',
        ]);

        $validator->sometimes('ktp_number', 'required|size:16|unique:users,ktp_number', function ($input) {
            return ($input->identity_type ?? '') === 'ktp';
        });

        $validator->sometimes('passport_number', 'required|min:6|unique:users,passport_number', function ($input) {
            return ($input->identity_type ?? '') === 'passport';
        });

        $validator->sometimes('sim_number', 'required|min:6|max:20|unique:users,sim_number', function ($input) {
            return ($input->identity_type ?? '') === 'sim';
        });

        $validator->sometimes('npwp_number', 'required|min:15|max:20|unique:users,npwp_number', function ($input) {
            return ($input->identity_type ?? '') === 'npwp';
        });

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal validasi'),
                'errors' => $validator->errors(),
            ], 422);
        }

        if (filled($request->whatsapp) && ! $this->isWhatsappVerified($request->whatsapp)) {
            return response()->json([
                'status' => 'error',
                'message' => __('Nomor WhatsApp belum diverifikasi. Silakan verifikasi kode OTP terlebih dahulu.'),
            ], 422);
        }

        $userData = [
            'full_name' => $request->full_name,
            'first_name' => $request->first_name,
            'mid_name' => $request->mid_name,
            'last_name' => $request->last_name,
            'username' => $request->username,
            'email' => $request->email,
            'whatsapp' => $request->whatsapp,
            'whatsapp_verified_at' => filled($request->whatsapp) ? now() : null,
            'ktp_number' => $request->ktp_number,
            'passport_number' => $request->passport_number,
            'sim_number' => $request->sim_number,
            'npwp_number' => $request->npwp_number,
            'identity_type' => $request->identity_type,
            'birth_place' => $request->birth_place,
            'birth_date' => $request->birth_date,
            'country' => $request->country,
            'province_id' => $request->province_id,
            'city_id' => $request->city_id,
            'district_id' => $request->district_id,
            'village_id' => $request->village_id,
            'province_name' => $request->province_name,
            'city_name' => $request->city_name,
            'district_name' => $request->district_name,
            'village_name' => $request->village_name,
            'postal_code' => $request->postal_code,
            'gender' => $request->gender,
            'religion' => $request->religion,
            'marital_status' => $request->marital_status,
            'mother_name' => $request->mother_name,
            'occupation' => $request->occupation,
            'income_range' => $request->income_range,
            'source_of_funds' => $request->source_of_funds,
            'address' => $request->address,
            'password' => $request->password,
            'ip_address' => $request->ip(),
        ];

        if ($request->hasFile('ktp_photo')) {
            $userData['ktp_photo'] = StorageService::upload($request->file('ktp_photo'), 'ktp-photos');
        }

        if ($request->hasFile('selfie_photo')) {
            $userData['selfie_photo'] = StorageService::upload($request->file('selfie_photo'), 'selfies');
        }

        if ($request->hasFile('face_scan_photo')) {
            $userData['face_scan_photo'] = StorageService::upload($request->file('face_scan_photo'), 'face-scans');
        }

        $userData['liveness_completed'] = $request->boolean('liveness_completed');

        if ($request->hasFile('profile_photo')) {
            $path = StorageService::upload($request->file('profile_photo'), 'avatars');
            $userData['avatar_url'] = $path;
        } elseif ($request->filled('avatar_url')) {
            $userData['avatar_url'] = $request->avatar_url;
        }

        // Remove non-fillable sensitive fields; set them via forceFill after create
        $sensitiveFields = array_intersect_key($userData, array_flip([
            'liveness_completed',
        ]));
        $userData = array_diff_key($userData, $sensitiveFields);

        $user = User::create($userData);
        if ($sensitiveFields) {
            $user->forceFill($sensitiveFields)->save();
        }

        $user->assignRole('user');

        /** @var User $user */
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'token' => $token,
            'user' => $user,
            'data' => [
                'token' => $token,
                'user' => $user,
            ],
        ], 201);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login' => 'required',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => __('Validasi gagal'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $login = $request->login;

        // Sign in hanya menerima Email atau Username. Nomor KTP/Passport/SIM/
        // NPWP tidak lagi jadi alias login, jadi query cukup satu kolom
        // ter-index -- konsisten dengan SignIn.php di panel Filament.
        $fieldType = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $user = User::where($fieldType, $login)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => __('Data login tidak valid'),
            ], 401);
        }

        if ($user->active_status === false) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akun Anda telah dinonaktifkan oleh admin.',
            ], 403);
        }

        if (! $user->active_status) {
            $user->update(['active_status' => true]);
        }

        // 2FA aktif -> jangan terbitkan token dulu.
        //
        // Padanan middleware CheckTrustedDevice milik paket multi-2fa, yang
        // di panel Filament mengarahkan ke OtpEmailOrTwoFactory sebelum
        // halaman mana pun boleh dibuka. Mobile sebelumnya melompatinya
        // seluruhnya: token langsung terbit begitu kata sandi cocok, jadi 2FA
        // yang sudah aktif tidak pernah ditebak -- siapa pun yang punya kata
        // sandi bisa masuk.
        //
        // Token baru terbit di verifyTwoFactor(), yaitu hanya setelah kode
        // benar.
        if ($this->requiresTwoFactor($user)) {
            return $this->twoFactorChallenge($user);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'token' => $token,
            'user' => $user,
            'data' => [
                'token' => $token,
                'user' => $user,
            ],
        ]);
    }

    /* ---------------------------------------------------------------------
     | Autentikasi dua faktor (padanan OtpEmailOrTwoFactory)
     | ------------------------------------------------------------------ */

    /** Cara verifikasi: kode dari aplikasi autentikasi (TOTP). */
    public const TWO_FACTOR_METHOD_AUTHENTICATOR = 'authenticator';

    /** Cara verifikasi: kode pemulihan XXXX-XXXX. */
    public const TWO_FACTOR_METHOD_RECOVERY = 'recovery';

    /** Cara verifikasi: kode 6 digit yang dikirim ke email. */
    public const TWO_FACTOR_METHOD_EMAIL = 'email';

    /** Berapa lama challenge 2FA berlaku (menit). */
    protected const TWO_FACTOR_CHALLENGE_TTL_MINUTES = 10;

    /** Berapa lama kode email 2FA berlaku (menit). */
    protected const TWO_FACTOR_EMAIL_TTL_MINUTES = 10;

    /** Key cache kode email 2FA. Sama dengan milik OtpEmailOrTwoFactory. */
    protected const TWO_FACTOR_EMAIL_CACHE_KEY = '2fa_email_otp_';

    /**
     * Apakah akun ini wajib membuktikan identitas dua langkah?
     *
     * Syaratnya sama persis dengan CheckTrustedDevice milik paket:
     * `two_factor_type` selain "none".
     *
     * `two_factor_enabled` sengaja TIDAK dipakai sebagai penentu. Keduanya
     * bisa tidak sinkron -- `SecurityController::twoFactorToggle()` mengubah
     * `two_factor_enabled` lewat toggle WhatsApp, sementara setup lewat
     * TwoFactorySetup hanya mengisi `two_factor_type`. memakai yang kedua
     * membuat kedua jalur itu sama-sama dihormati.
     */
    protected function requiresTwoFactor(User $user): bool
    {
        return $user->two_factor_type !== null
            && $user->two_factor_type->value !== TwoFactorAuthType::None->value;
    }

    /**
     * Mulai challenge 2FA: buat token sementara, kirim kode email bila perlu,
     * dan JANGAN terbitkan token aplikasi.
     *
     * Token challenge hanya mengikat "pengguna mana yang sedang memegang
     * challenge ini", bukan hak akses. Ia disimpan di cache bersama id user,
     * jadi tidak bisa dipakai menebak-nebak akun lain.
     */
    protected function twoFactorChallenge(User $user): JsonResponse
    {
        $challengeToken = Str::random(64);

        // Metode awal mengikuti tipe yang tersimpan: Email -> kode email,
        // selain itu -> aplikasi autentikasi.
        $method = $user->two_factor_type->value === TwoFactorAuthType::Email->value
            ? self::TWO_FACTOR_METHOD_EMAIL
            : self::TWO_FACTOR_METHOD_AUTHENTICATOR;

        Cache::put(
            self::twoFactorChallengeCacheKey($challengeToken),
            ['user_id' => $user->id],
            now()->addMinutes(self::TWO_FACTOR_CHALLENGE_TTL_MINUTES),
        );

        if ($method === self::TWO_FACTOR_METHOD_EMAIL) {
            $this->sendTwoFactorEmailCode($user);
        }

        return response()->json([
            'status' => 'two_factor_required',
            'message' => __('Verifikasi dua faktor diperlukan.'),
            'data' => [
                'challenge_token' => $challengeToken,
                'method' => $method,
                'two_factor_type' => $user->two_factor_type->value,
                'email' => $user->email,
                'expires_in' => self::TWO_FACTOR_CHALLENGE_TTL_MINUTES * 60,
            ],
        ]);
    }

    /**
     * Tahap kedua: verifikasi kode 2FA, baru terbitkan token.
     *
     * Tiga cara, dipilih lewat `method`, persis seperti OtpEmailOrTwoFactory:
     *
     *   authenticator -- TOTP 6 digit dari Google Authenticator / Duo
     *   recovery     -- kode pemulihan XXXX-XXXX, SEKALI PAKAI
     *   email        -- kode 6 digit yang dikirim ke email
     *
     * Paket resend: kirim ulang kode email.
     */
    public function resendTwoFactorCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'challenge_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => __('Validasi gagal'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $this->twoFactorChallengeUser($request->challenge_token);

        if (! $user) {
            return $this->twoFactorChallengeExpiredResponse();
        }

        $this->sendTwoFactorEmailCode($user, force: true);

        return response()->json([
            'status' => 'success',
            'message' => __('Kode baru telah dikirim ke email Anda.'),
        ]);
    }

    public function verifyTwoFactor(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'challenge_token' => 'required|string',
            'code' => 'required|string|max:12',
            'method' => 'nullable|string|in:'.self::TWO_FACTOR_METHOD_AUTHENTICATOR.','.self::TWO_FACTOR_METHOD_RECOVERY.','.self::TWO_FACTOR_METHOD_EMAIL,
        ]);

        // Sama seperti login() dan register(): validasi gagal dibalas 422
        // dengan `errors` per-field, bukan exception ValidationException yang
        // jadi 500.
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => __('Validasi gagal'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $challengeToken = $request->challenge_token;
        $user = $this->twoFactorChallengeUser($challengeToken);

        if (! $user) {
            return $this->twoFactorChallengeExpiredResponse();
        }

        $method = $request->method ?: ($user->two_factor_type->value === TwoFactorAuthType::Email->value
            ? self::TWO_FACTOR_METHOD_EMAIL
            : self::TWO_FACTOR_METHOD_AUTHENTICATOR);

        $code = (string) $request->code;

        $lolos = match ($method) {
            self::TWO_FACTOR_METHOD_AUTHENTICATOR => $this->checkTwoFactorTotp($user, $code),
            self::TWO_FACTOR_METHOD_RECOVERY => $this->checkTwoFactorRecovery($user, $code),
            self::TWO_FACTOR_METHOD_EMAIL => $this->checkTwoFactorEmail($user, $code),
            default => false,
        };

        if (! $lolos) {
            return response()->json([
                'status' => 'error',
                'message' => __('Kode salah'),
                'error' => __('Kode yang Anda masukkan tidak cocok atau sudah kadaluarsa.'),
            ], 422);
        }

        // Kode email sudah tidak berlaku -- jangan biarkan bisa dipakai ulang.
        Cache::forget(self::TWO_FACTOR_EMAIL_CACHE_KEY.$user->id);
        Cache::forget(self::twoFactorChallengeCacheKey($challengeToken));

        if ($method === self::TWO_FACTOR_METHOD_EMAIL && ! $user->two_factor_confirmed_at) {
            $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => __('Verifikasi Berhasil'),
            'token' => $token,
            'user' => $user,
            'data' => [
                'token' => $token,
                'user' => $user,
            ],
        ]);
    }

    protected static function twoFactorChallengeCacheKey(string $challengeToken): string
    {
        return '2fa_challenge_'.$challengeToken;
    }

    /**
     * User di balik challenge, atau null kalau challenge sudah kadaluarsa /
     * dipakai ulang.
     */
    protected function twoFactorChallengeUser(string $challengeToken): ?User
    {
        $cached = Cache::get(self::twoFactorChallengeCacheKey($challengeToken));

        if (! is_array($cached) || ! isset($cached['user_id'])) {
            return null;
        }

        $user = User::find($cached['user_id']);

        // Akun yang sudah dihapus, atau 2FA-nya dimatikan di tengah
        // challenge: challenge ini tidak lagi berguna.
        if (! $user || ! $this->requiresTwoFactor($user)) {
            return null;
        }

        return $user;
    }

    protected function twoFactorChallengeExpiredResponse(): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => __('Sesi verifikasi habis. Silakan Sign In kembali.'),
        ], 401);
    }

    /** Kode 6 digit dari aplikasi autentikasi, dicek dengan Google2FA sungguhan. */
    protected function checkTwoFactorTotp(User $user, string $code): bool
    {
        $secret = $user->two_factor_secret;

        if (! $secret || $code === '') {
            return false;
        }

        return (new Google2FA)->verifyKey((string) $secret, $code);
    }

    /**
     * Kode pemulihan harus SEKALI PAKAI.
     *
     * Dicari dengan lockForUpdate supaya dua request bersamaan tidak memakai
     * kode yang sama dua kali. Pencocokan dibuat case-insensitive karena
     * pengguna bisa saja mengetik huruf kecil.
     */
    protected function checkTwoFactorRecovery(User $user, string $code): bool
    {
        $code = strtoupper(trim($code));

        if ($code === '') {
            return false;
        }

        $backup = BackupCode::query()
            ->where('user_id', $user->id)
            ->whereRaw('UPPER(code) = ?', [$code])
            ->where('used', false)
            ->lockForUpdate()
            ->first();

        if (! $backup) {
            return false;
        }

        $backup->update(['used' => true, 'used_at' => now()]);

        return true;
    }

    protected function checkTwoFactorEmail(User $user, string $code): bool
    {
        $disimpan = Cache::get(self::TWO_FACTOR_EMAIL_CACHE_KEY.$user->id);

        return $disimpan !== null && hash_equals((string) $disimpan, trim($code));
    }

    /**
     * Kirim kode 2FA ke email.
     *
     * Disimpan di cache dengan key terpisah, BUKAN lewat
     * generateTwoFactorOTPCode() milik paket: method itu menulis
     * `two_factor_secret = encrypt(kode acak)`, dan menimpanya berarti 2FA
     * yang sedang aktif ikut hancur -- terutama kalau pengguna memakai
     * aplikasi autentikasi, karena secret itu berisi TOTP-nya.
     */
    protected function sendTwoFactorEmailCode(User $user, bool $force = false): void
    {
        $key = self::TWO_FACTOR_EMAIL_CACHE_KEY.$user->id;

        if (! $force && Cache::has($key)) {
            return;
        }

        $kode = (string) random_int(100000, 999999);
        Cache::put($key, $kode, now()->addMinutes(self::TWO_FACTOR_EMAIL_TTL_MINUTES));

        try {
            Mail::to($user->email)->send(new OtpMail($kode, $user->name ?? 'Pengguna'));
        } catch (\Throwable $e) {
            // Mail gagal = kode tidak akan pernah sampai. Hapus dari cache
            // supaya tombol "Kirim Ulang" tetap bisa dipakai, jangan
            // menampilkan form yang tidak bisa diisi.
            Cache::forget($key);

            Log::error('Gagal kirim OTP 2FA ke '.$user->email.': '.$e->getMessage());
        }
    }

    /**
     * Buang semua perangkat yang sedang masuk KECUALI yang sedang dipakai
     * request ini.
     *
     * Delegasi ke User::revokeOtherSessions() -- logikanya hidup di model
     * karena bukan milik satu controller saja: Ubah Kata Sandi di panel
     * Filament dan di API sama-sama membutuhkannya.
     */
    protected function revokeOtherSessions(User $user): int
    {
        return $user->revokeOtherSessions();
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => __('Berhasil keluar'),
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first(['*']);

        if (! $user) {
            return response()->json(['status' => 'success', 'message' => 'Jika email terdaftar, OTP telah dikirim.'], 200);
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        User::where('email', $request->email)->update([
            'otp_code' => $otp,
            // 30 menit, bukan 5.
            //
            // OtpRequestPasswordReset::request() di panel Filament menyimpan
            // OTP lupa kata sandi 30 menit (sama dengan VerifyOtp yang
            // menandainya sah 30 menit). API ini memakai 5 menit, jadi
            // pengguna yang membuka email-nya lewat -- hal yang paling
            // sering terjadi, apalagi dengan email perusahaan yang
            // Sometimes道理的 ditunda -- akan melihat OTP sudah kedaluwarsa
            // padahal masih di bawah tenggat yang sama di web.
            //
            // VerifyOtp juga memanggil verifyOtp() dengan purpose
            // forgot_password, jadi masa berlaku WAJIB sama untuk keduanya;
            // kalau tidak, ada jeda di mana sheet reset akan menolak OTP
            // yang baru saja dianggap sah.
            'otp_expires_at' => now()->addMinutes(self::OTP_FORGOT_PASSWORD_TTL_MINUTES),
            'otp_purpose' => 'forgot_password',
        ]);

        try {
            Mail::to($request->email)->send(new OtpMail($otp, $user->name ?? 'Pengguna'));
        } catch (\Throwable $e) {
            // Mail gagal = OTP tidak akan pernah sampai. Hapus dari DB
            // supaya VerifyOtp bisa-rules تحمل kebetulan tidak mungkin.
            Log::error('Gagal kirim OTP lupa kata sandi ke '.$request->email.': '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => __('Kode OTP gagal dikirim. Periksa konfigurasi email lalu coba lagi.'),
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Instruksi reset password akan dikirim ke email Anda.',
            'email' => $user->email,
        ]);
    }

    /**
     * Atur ulang kata sandi setelah OTP terverifikasi.
     *
     * Dua tahap, sama seperti VerifyOtp + OtpResetPassword di panel Filament:
     * verifyOtp() menandai OTP forgot_password sudah sah di cache, lalu
     * endpoint inilah yang menerimanya. both pihak menulis ke key cache yang
     * sama (`otp_verified_for_<email>`), jadi kedua antarmuka tidak mungkin
     * saling menagih OTP-nya dua kali.
     *
     * Field `email` dan `otp` tetap diterima dan keduanya WAJIB, karena
     * authenticate-nya berarti "pengguna ini sudah membuktikan miliknya email
     * dengan kode itu". Mobil mengirim keduanya; kode yang lolos adalah yang
     * sudah diverifikasi, atau -- untuk klien yang lebih lama -- yang masih
     * hidup di tabel.
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
            'password' => PasswordPolicy::confirmedRules(),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => __('Validasi gagal'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = $request->email;
        $otp = (string) $request->otp;

        // Sudah diverifikasi di verifyOtp()? (jalur normal)
        $sudahDiverifikasi = Cache::get('otp_verified_for_'.$email);

        $user = $sudahDiverifikasi
            ? User::where('email', $email)->first(['*'])
            : // Fallback: OTP forgot_password yang masih hidup di tabel.
                User::where('email', $email)
                ->where('otp_code', $otp)
                ->where('otp_purpose', 'forgot_password')
                ->where('otp_expires_at', '>', now())
                ->first();

        if (! $user) {
            return response()->json(['status' => 'error', 'message' => __('Kode OTP tidak valid atau sudah kedaluwarsa')], 422);
        }

        // Token lama ikut dibuang: kata sandi berubah, jadi sesi yang dibuat
        // dengan kata sandi lama tidak lagi mewakili akun ini.
        $user->tokens()->delete();

        $user->update([
            'password' => $request->password,
            'otp_code' => null,
            'otp_expires_at' => null,
            'otp_purpose' => null,
        ]);

        Cache::forget('otp_verified_for_'.$email);

        return response()->json([
            'status' => 'success',
            'message' => __('Reset password berhasil'),
        ]);
    }

    public function clerkSync(Request $request)
    {
        $secret = config('services.clerk_sync_secret', env('CLERK_SYNC_SECRET', ''));
        if ($secret === '' || $request->header('X-CLERK-SYNC-SECRET') !== $secret) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'clerk_id' => 'required|string',
            'email' => 'required|email',
            'name' => 'nullable|string|max:255',
            'avatar_url' => 'nullable|string|max:500',
            'username' => 'nullable|string|max:255|alpha_dash',
        ]);

        $clerkId = $request->clerk_id;
        $email = $request->email;

        $user = User::where('clerk_id', $clerkId)->orWhere('email', $email)->first();

        if ($user) {
            if (! $user->clerk_id) {
                $user->update(['clerk_id' => $clerkId]);
            }
            if ($request->name && ! $user->full_name) {
                $user->update(['full_name' => $request->name]);
            }
            if ($request->avatar_url && ! $user->avatar_url) {
                $user->update(['avatar_url' => $request->avatar_url]);
            }
        } else {
            $username = $request->username ?? 'user_'.Str::random(8);
            while (User::where('username', $username)->exists()) {
                $username = 'user_'.Str::random(8);
            }

            $user = User::create([
                'clerk_id' => $clerkId,
                'full_name' => $request->name ?? $email,
                'username' => $username,
                'email' => $email,
                'avatar_url' => $request->avatar_url,
                'active_status' => true,
            ]);

            $userRole = Role::where('name', 'user')->first();
            if ($userRole) {
                $user->assignRole($userRole);
            }
        }

        if (! $user->active_status) {
            $user->update(['active_status' => true]);
        }

        $token = $user->createToken('clerk-sync')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'data' => [
                'token' => $token,
                'user' => $user,
            ],
        ]);
    }

    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required_without:whatsapp|email',
            'whatsapp' => 'required_without:email|string',
            'purpose' => 'required|string|in:google_register,forgot_password,verify_email,reset_app_lock,verify_whatsapp',
        ]);

        $target = $request->whatsapp ?? $request->email ?? '';
        $rateKey = 'otp_'.preg_replace('/\D/', '', $target);
        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            $seconds = RateLimiter::availableIn($rateKey);

            return response()->json([
                'status' => 'error',
                'message' => "Terlalu banyak percobaan. Silakan coba lagi dalam {$seconds} detik.",
            ], 429);
        }
        RateLimiter::hit($rateKey, 300);

        // WhatsApp OTP (verify_whatsapp)
        if ($request->filled('whatsapp')) {
            if ($request->purpose !== 'verify_whatsapp') {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Tujuan OTP tidak valid'),
                ], 422);
            }

            $phone = $this->normalizeWhatsapp($request->whatsapp);
            if (strlen($phone) < 10) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Format nomor WhatsApp tidak valid'),
                ], 422);
            }

            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            WhatsappOtp::updateOrCreate(
                ['whatsapp' => $phone],
                [
                    'otp_code' => $otp,
                    'expires_at' => now()->addMinutes(5),
                    'verified_at' => null,
                ]
            );

            $this->sendWhatsappOtp($phone, $otp);

            return response()->json([
                'status' => 'success',
                'message' => __('Kode OTP berhasil dikirim ke WhatsApp'),
            ]);
        }

        $user = User::where('email', $request->email)->first();

        if ($request->purpose === 'google_register') {
            if (! $user) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('User tidak ditemukan'),
                ], 404);
            }
            if (! $user->social_id || $user->social_type !== 'google') {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Email tidak terdaftar via Google'),
                ], 422);
            }
        } elseif (! in_array($request->purpose, ['forgot_password', 'verify_email', 'reset_app_lock'])) {
            return response()->json([
                'status' => 'error',
                'message' => __('Tujuan OTP tidak valid'),
            ], 422);
        }

        if ($request->purpose === 'reset_app_lock' && ! $user) {
            return response()->json([
                'status' => 'error',
                'message' => __('User tidak ditemukan'),
            ], 404);
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        User::where('email', $request->email)->update([
            'otp_code' => $otp,
            'otp_expires_at' => now()->addMinutes(self::OTP_VERIFY_TTL_MINUTES),
            'otp_purpose' => $request->purpose,
        ]);

        Mail::to($request->email)->send(new OtpMail($otp, $user?->name ?? 'Pengguna'));

        return response()->json([
            'status' => 'success',
            'message' => __('Kode OTP berhasil dikirim'),
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required_without:whatsapp|email',
            'whatsapp' => 'required_without:email|string',
            'otp' => 'required|string|size:6',
            'purpose' => 'required|string|in:google_register,forgot_password,verify_email,reset_app_lock,verify_whatsapp',
        ]);

        // WhatsApp OTP (verify_whatsapp)
        if ($request->filled('whatsapp')) {
            if ($request->purpose !== 'verify_whatsapp') {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Tujuan OTP tidak valid'),
                ], 422);
            }

            $phone = $this->normalizeWhatsapp($request->whatsapp);

            $record = WhatsappOtp::where('whatsapp', $phone)
                ->where('otp_code', $request->otp)
                ->where('expires_at', '>', now())
                ->whereNull('verified_at')
                ->first();

            if (! $record) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Kode OTP tidak valid atau sudah kedaluwarsa'),
                ], 422);
            }

            $record->update(['verified_at' => now()]);

            $user = User::where('whatsapp', $phone)
                ->orWhere('whatsapp', $request->whatsapp)
                ->first();
            if ($user) {
                $user->update(['whatsapp_verified_at' => now()]);
            }

            return response()->json([
                'status' => 'success',
                'message' => __('OTP berhasil diverifikasi'),
                'data' => [
                    'verified' => true,
                ],
            ]);
        }

        $user = User::where('email', $request->email)
            ->where('otp_code', $request->otp)
            ->where('otp_purpose', $request->purpose)
            ->where('otp_expires_at', '>', now())
            ->first();

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => __('Kode OTP tidak valid atau sudah kedaluwarsa'),
            ], 422);
        }

        $user->update([
            'otp_code' => null,
            'otp_expires_at' => null,
            'otp_purpose' => null,
        ]);

        if ($request->purpose === 'forgot_password' || $request->purpose === 'verify_email' || $request->purpose === 'google_register') {
            $user->update(['email_verified_at' => now()]);
        }

        // forgot_password: OTP ini masih dibutuhkan satu langkah lagi, yaitu
        // resetPassword(). Tabel di atas sudah mengosongkan otp_code, jadi
        // tanpa penanda di cache, resetPassword() tidak akan punya apa-apa
        // untuk dicocokkan -- persis yang membuat alur lupa kata sandi selalu
        // gagal di mobile.
        //
        // TTL-nya ikut OTP_FORGOT_PASSWORD_TTL_MINUTES, sama seperti
        // VerifyOtp::verify() di panel Filament yang menandainya sah 30
        // menit. Nilai yang disimpan adalah kodenya, bukan `true`, supaya
        // resetPassword() bisa mencocokkan ulang kode yang diketik
        // pengguna.
        if ($request->purpose === 'forgot_password') {
            Cache::put(
                'otp_verified_for_'.$request->email,
                (string) $request->otp,
                now()->addMinutes(self::OTP_FORGOT_PASSWORD_TTL_MINUTES),
            );
        }

        return response()->json([
            'status' => 'success',
            'message' => __('OTP berhasil diverifikasi'),
            'data' => [
                'verified' => true,
            ],
        ]);
    }

    public function googleLogin(Request $request)
    {
        $request->validate([
            'id_token' => 'required|string',
        ]);

        // Verify Google ID token via Google's token info endpoint
        try {
            $response = Http::timeout(10)->withoutVerifying()->get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $request->id_token,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal memverifikasi token Google'),
            ], 500);
        }

        if (! $response->successful()) {
            return response()->json([
                'status' => 'error',
                'message' => __('Token Google tidak valid'),
            ], 401);
        }

        $payload = $response->json();
        if (! isset($payload['email']) || ! isset($payload['sub'])) {
            return response()->json([
                'status' => 'error',
                'message' => __('Token Google tidak valid'),
            ], 401);
        }

        $googleId = $payload['sub'];
        $email = $payload['email'];
        $name = $payload['name'] ?? explode('@', $email)[0];
        $avatarUrl = $payload['picture'] ?? null;

        $user = User::where('social_id', $googleId)
            ->orWhere('email', $email)
            ->first();

        if ($user) {
            if (! $user->social_id) {
                $user->update([
                    'social_id' => $googleId,
                    'social_type' => 'google',
                    'avatar_url' => $avatarUrl ?: $user->avatar_url,
                ]);
            }

            if ($user->active_status === false) {
                $user->update(['active_status' => true]);
            }

            $token = $user->createToken('google-auth')->plainTextToken;

            return response()->json([
                'status' => 'success',
                'message' => __('Login berhasil'),
                'data' => [
                    'token' => $token,
                    'user' => $user,
                    'needs_otp' => false,
                    'needs_completion' => false,
                ],
            ]);
        }

        // User doesn't exist — register with Google data
        try {
            $user = User::create([
                'social_id' => $googleId,
                'social_type' => 'google',
                'full_name' => $name,
                'first_name' => explode(' ', $name)[0],
                'last_name' => Str::after($name, ' ') ?: '',
                'email' => $email,
                'avatar_url' => $avatarUrl,
                'active_status' => true,
            ]);
        } catch (QueryException $e) {
            if ($e->errorInfo[1] == 1062) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Akun Google kamu sudah terdaftar. Silakan masuk dengan email dan password.'),
                    'error_code' => 'google_account_already_registered',
                ], 422);
            }
            throw $e;
        }

        $userRole = Role::where('name', 'user')->first();
        if ($userRole) {
            $user->assignRole($userRole);
        }

        $token = $user->createToken('google-auth')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => __('Registrasi berhasil'),
            'data' => [
                'token' => $token,
                'user' => $user,
                'needs_otp' => true,
                'needs_completion' => true,
            ],
        ]);
    }

    public function facebookLogin(Request $request)
    {
        $request->validate([
            'access_token' => 'required|string',
        ]);

        // Verify Facebook access token via Graph API
        try {
            $response = Http::timeout(10)->get('https://graph.facebook.com/me', [
                'access_token' => $request->access_token,
                'fields' => 'id,name,email,picture',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal memverifikasi token Facebook'),
            ], 500);
        }

        if (! $response->successful() || ! isset($response['id'])) {
            return response()->json([
                'status' => 'error',
                'message' => __('Token Facebook tidak valid'),
            ], 401);
        }

        $payload = $response->json();
        $facebookId = $payload['id'];
        $email = $payload['email'] ?? 'fb_'.$facebookId.'@facebook.com';
        $name = $payload['name'] ?? explode('@', $email)[0];
        $avatarUrl = $payload['picture']['data']['url'] ?? null;

        $user = User::where('social_id', $facebookId)
            ->orWhere('email', $email)
            ->first();

        if ($user) {
            if (! $user->social_id) {
                $user->update([
                    'social_id' => $facebookId,
                    'social_type' => 'facebook',
                    'avatar_url' => $avatarUrl ?: $user->avatar_url,
                ]);
            }

            if ($user->active_status === false) {
                $user->update(['active_status' => true]);
            }

            return $this->issueToken($user, 'facebook-auth');
        }

        // Register with Facebook data
        try {
            $user = User::create([
                'social_id' => $facebookId,
                'social_type' => 'facebook',
                'full_name' => $name,
                'first_name' => explode(' ', $name)[0],
                'last_name' => Str::after($name, ' ') ?: '',
                'email' => $email,
                'avatar_url' => $avatarUrl,
                'active_status' => true,
            ]);
        } catch (QueryException $e) {
            if ($e->errorInfo[1] == 1062) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Akun Facebook kamu sudah terdaftar. Silakan masuk dengan email dan password.'),
                    'error_code' => 'facebook_account_already_registered',
                ], 422);
            }
            throw $e;
        }

        $userRole = Role::where('name', 'user')->first();
        if ($userRole) {
            $user->assignRole($userRole);
        }

        return $this->issueToken($user, 'facebook-auth');
    }

    public function appleLogin(Request $request)
    {
        $request->validate([
            'identity_token' => 'required|string',
        ]);

        $token = $request->input('identity_token');

        try {
            $appleKeys = Http::timeout(10)->get('https://appleid.apple.com/auth/keys')->json();
            $publicKey = JWK::parseKeySet($appleKeys);
            $decoded = JWT::decode($token, $publicKey, ['RS256']);
            $appleId = $decoded->sub;
            $email = $decoded->email ?? null;
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Token Apple tidak valid'),
            ], 401);
        }

        if (! $appleId) {
            return response()->json([
                'status' => 'error',
                'message' => __('Token Apple tidak valid'),
            ], 401);
        }

        $email = $email ?? 'apple_'.$appleId.'@apple.com';

        $user = User::where('social_id', $appleId)
            ->orWhere('email', $email)
            ->first();

        if ($user) {
            if (! $user->social_id) {
                $user->update([
                    'social_id' => $appleId,
                    'social_type' => 'apple',
                ]);
            }

            if ($user->active_status === false) {
                $user->update(['active_status' => true]);
            }

            return $this->issueToken($user, 'apple-auth');
        }

        // Register with Apple data
        $user = User::create([
            'social_id' => $appleId,
            'social_type' => 'apple',
            'full_name' => $email,
            'email' => $email,
            'active_status' => true,
        ]);

        $userRole = Role::where('name', 'user')->first();
        if ($userRole) {
            $user->assignRole($userRole);
        }

        return $this->issueToken($user, 'apple-auth');
    }

    public function updateProfile(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'full_name' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'mid_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'username' => 'nullable|string|max:255|unique:users,username,'.$user->id,
            'email' => 'nullable|email|max:255|unique:users,email,'.$user->id,
            'whatsapp' => 'nullable|string|max:20',
            'identity_type' => 'nullable|string|in:ktp,passport,sim,npwp|max:20',
            'ktp_number' => 'nullable|string|max:20|unique:users,ktp_number,'.$user->id,
            'passport_number' => 'nullable|string|max:20|unique:users,passport_number,'.$user->id,
            'sim_number' => 'nullable|string|max:20|unique:users,sim_number,'.$user->id,
            'npwp_number' => 'nullable|string|max:20|unique:users,npwp_number,'.$user->id,
            'gender' => 'nullable|string|max:20',
            'religion' => 'nullable|string|max:50',
            'marital_status' => 'nullable|string|max:50',
            'mother_name' => 'nullable|string|max:255',
            'occupation' => 'nullable|string|max:100',
            'income_range' => 'nullable|string|max:50',
            'source_of_funds' => 'nullable|string|max:100',
            'address' => 'nullable|string',
            'budget' => 'nullable|numeric',
            'wedding_date' => 'nullable|date',
            'theme_preference' => 'nullable|string',
            'color_preference' => 'nullable|string',
            'event_concept' => 'nullable|string',
            'dream_venue' => 'nullable|string',
        ], [
            'ktp_number.unique' => 'Nomor KTP sudah terdaftar oleh pengguna lain.',
            'passport_number.unique' => 'Nomor Passport sudah terdaftar oleh pengguna lain.',
            'sim_number.unique' => 'Nomor SIM sudah terdaftar oleh pengguna lain.',
            'npwp_number.unique' => 'Nomor NPWP sudah terdaftar oleh pengguna lain.',
        ]);

        $validator->sometimes('ktp_number', 'required|size:16|unique:users,ktp_number,'.$user->id, function ($input) {
            return ($input->identity_type ?? '') === 'ktp';
        });

        $validator->sometimes('passport_number', 'required|min:6|max:20|unique:users,passport_number,'.$user->id, function ($input) {
            return ($input->identity_type ?? '') === 'passport';
        });

        $validator->sometimes('sim_number', 'required|min:6|max:20|unique:users,sim_number,'.$user->id, function ($input) {
            return ($input->identity_type ?? '') === 'sim';
        });

        $validator->sometimes('npwp_number', 'required|min:15|max:20|unique:users,npwp_number,'.$user->id, function ($input) {
            return ($input->identity_type ?? '') === 'npwp';
        });

        $data = $validator->validated();

        if ($request->hasFile('profile_photo')) {
            $path = StorageService::upload($request->file('profile_photo'), 'profile-photos');
            $data['avatar_url'] = $path;
        }

        $user->update($data);

        return response()->json([
            'status' => 'success',
            'data' => array_merge($user->toArray(), [
                'needs_completion' => ! $user->identity_type || ! $user->whatsapp || ! $user->birth_date,
            ]),
        ]);
    }

    public function deleteAccount(Request $request)
    {
        $request->validate([
            'password' => 'required',
        ]);

        /** @var User $user */
        $user = Auth::user();

        if (! Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => __('Password tidak valid'),
            ], 422);
        }

        // Revoke all tokens
        $user->tokens()->delete();

        // Delete user
        $user->delete();

        return response()->json([
            'status' => 'success',
            'message' => __('Akun berhasil dihapus'),
        ]);
    }

    public function sendVerificationEmail(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->email_verified_at) {
            return response()->json(['status' => 'success', 'message' => 'Email sudah diverifikasi']);
        }

        $token = Str::random(64);
        $user->update(['email_verification_token' => $token]);

        Mail::raw(
            'Verifikasi email kamu: '.config('app.url')."/api/verify-email?token={$token}&email={$user->email}",
            function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Verifikasi Email - Wedding Flower Decorations');
            }
        );

        return response()->json(['status' => 'success', 'message' => 'Email verifikasi terkirim']);
    }

    public function verifyEmail(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)
            ->where('email_verification_token', $request->token)
            ->first();

        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Token tidak valid'], 422);
        }

        $user->update([
            'email_verified_at' => now(),
            'email_verification_token' => null,
        ]);

        return response()->json(['status' => 'success', 'message' => 'Email berhasil diverifikasi']);
    }

    private function issueToken(User $user, string $guardName): JsonResponse
    {
        $token = $user->createToken($guardName)->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => __('Login berhasil'),
            'data' => [
                'token' => $token,
                'user' => $user,
                'needs_completion' => ! $user->identity_type || ! $user->whatsapp || ! $user->birth_date,
            ],
        ]);
    }

    /**
     * Normalize phone to international format (628xxx).
     */
    private function normalizeWhatsapp(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);
        if (empty($phone)) {
            return '';
        }
        if (str_starts_with($phone, '0')) {
            $phone = '62'.substr($phone, 1);
        }

        return $phone;
    }

    /**
     * Check whether the given whatsapp number has been OTP-verified recently.
     */
    private function isWhatsappVerified(string $whatsapp): bool
    {
        $phone = $this->normalizeWhatsapp($whatsapp);
        if (empty($phone)) {
            return false;
        }

        return WhatsappOtp::where('whatsapp', $phone)
            ->whereNotNull('verified_at')
            ->where('verified_at', '>', now()->subMinutes(10))
            ->exists();
    }

    /**
     * Send OTP via WhatsApp (Fonnte).
     */
    private function sendWhatsappOtp(string $phone, string $otp): void
    {
        try {
            $token = config('services.fonnte_token', env('FONNTE_TOKEN', ''));
            if (empty($token)) {
                Log::warning('[Auth] WhatsApp OTP skipped — FONNTE_TOKEN not set');

                return;
            }

            $message = __('whatsapp.otp_message', ['otp' => $otp]);

            Http::withHeaders(['Authorization' => $token])
                ->timeout(10)
                ->post('https://api.fonnte.com/send', [
                    'target' => $phone,
                    'message' => $message,
                ]);
        } catch (\Throwable $e) {
            Log::warning('[Auth] WhatsApp OTP exception: '.$e->getMessage());
        }
    }
}
