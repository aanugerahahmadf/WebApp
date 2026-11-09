<?php

namespace App\Http\Controllers\Api\User\AuthController;

use App\Http\Controllers\Controller;
use App\Mail\OtpMail\OtpMail;
use App\Models\BackupCode\BackupCode;
use App\Models\User\User;
use App\Models\WhatsappOtp\WhatsappOtp;
use App\Services\StorageService\StorageService;
use App\Support\DeviceTrust\DeviceTrustService;
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

        // Jalur 2FA juga tanpa Login event -- perangkatnya dicatat di sini.
        DeviceTrustService::recognize($user);

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
            if (! $user->social_id || ! in_array($user->social_type, ['google', 'firebase_google', 'firebase'])) {
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
            'firebase_id_token' => 'nullable|string',
            'google_id_token' => 'nullable|string',
            'id_token' => 'nullable|string',
        ]);

        $firebaseIdToken = $request->input('firebase_id_token') ?? $request->input('id_token');
        $googleIdToken = $request->input('google_id_token');

        if (! $firebaseIdToken && ! $googleIdToken) {
            return response()->json([
                'status' => 'error',
                'message' => __('Token Firebase atau Google diperlukan'),
            ], 422);
        }

        $email = null;
        $googleId = null;
        $name = null;
        $avatarUrl = null;

        // 1. Verifikasi token Firebase ID
        if ($firebaseIdToken) {
            // A. Coba Kreait Firebase Admin SDK jika file credentials ada
            $credentialsPath = config('firebase.credentials');
            if ($credentialsPath && file_exists($credentialsPath)) {
                try {
                    $firebaseAuth = app(\Kreait\Firebase\Contract\Auth::class);
                    $verifiedToken = $firebaseAuth->verifyIdToken($firebaseIdToken);
                    $googleId = (string) $verifiedToken->claims()->get('sub');
                    $firebaseUser = $firebaseAuth->getUser($googleId);
                    $email = $firebaseUser->email;
                    $name = $firebaseUser->displayName ?: explode('@', $email)[0];
                    $avatarUrl = $firebaseUser->photoUrl;
                } catch (\Throwable $e) {
                    Log::warning('[GoogleLogin] Kreait Firebase verification failed, falling back to Identity Toolkit REST', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // B. Verifikasi via Google Identity Toolkit REST API (resilient & tanpa file service account)
            if (! $email) {
                $apiKey = config('firebase.api_key') ?: env('FIREBASE_API_KEY');
                if ($apiKey) {
                    try {
                        $response = Http::timeout(15)->post(
                            'https://identitytoolkit.googleapis.com/v1/accounts:lookup?key='.$apiKey,
                            ['idToken' => $firebaseIdToken]
                        );

                        if ($response->successful()) {
                            $users = $response->json('users');
                            if (! empty($users) && isset($users[0])) {
                                $profile = $users[0];
                                $googleId = $profile['localId'] ?? null;
                                $email = $profile['email'] ?? null;
                                $name = $profile['displayName'] ?? ($email ? explode('@', $email)[0] : 'User');
                                $avatarUrl = $profile['photoUrl'] ?? null;
                            }
                        } else {
                            Log::warning('[GoogleLogin] Identity Toolkit lookup response not successful', [
                                'status' => $response->status(),
                                'body' => $response->json(),
                            ]);
                        }
                    } catch (\Throwable $e) {
                        Log::error('[GoogleLogin] Identity Toolkit network exception', ['error' => $e->getMessage()]);
                    }
                }
            }
        }

        // 2. Fallback verifikasi via Google OAuth2 tokeninfo jika token Google langsung
        if (! $email && ($googleIdToken || $firebaseIdToken)) {
            $tokenToTest = $googleIdToken ?: $firebaseIdToken;
            try {
                $response = Http::timeout(15)->get('https://oauth2.googleapis.com/tokeninfo', [
                    'id_token' => $tokenToTest,
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    if (! empty($data['email'])) {
                        $email = $data['email'];
                        $googleId = $data['sub'] ?? ($data['user_id'] ?? null);
                        $name = $data['name'] ?? explode('@', $email)[0];
                        $avatarUrl = $data['picture'] ?? null;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[GoogleLogin] Google OAuth tokeninfo fallback failed', ['error' => $e->getMessage()]);
            }
        }

        if (! $email || ! $googleId) {
            Log::warning('Firebase/Google token verification completely failed');

            return response()->json([
                'status' => 'error',
                'message' => __('Token Firebase tidak valid atau sudah kedaluwarsa'),
            ], 401);
        }

        $user = User::where('social_id', $googleId)
            ->orWhere('email', $email)
            ->first();

        if ($user) {
            if (! $user->social_id) {
                $user->update([
                    'social_id' => $googleId,
                    'social_type' => 'firebase_google',
                    'avatar_url' => $avatarUrl ?: $user->avatar_url,
                ]);
            }

            if ($user->active_status === false) {
                $user->update(['active_status' => true]);
            }

            // 2FA aktif -> jangan terbitkan token dulu, minta challenge.
            // Sama seperti di login(): requiresTwoFactor() memeriksa apakah
            // two_factor_type selain "none". Tanpa pengecekan ini, akun Google
            // yang 2FA-nya aktif akan langsung mendapat token dan melewati
            // OtpEmailOrTwoFactory -- persis seperti bug yang dilaporkan.
            if ($this->requiresTwoFactor($user)) {
                return $this->twoFactorChallenge($user);
            }

            $token = $user->createToken('google-auth')->plainTextToken;

            // Sign in Google akun LAMA via API -- tidak memicu Login event.
            DeviceTrustService::recognize($user);

            $needsOtp = is_null($user->email_verified_at);
            $needsCompletion = method_exists($user, 'shouldCompleteProfile') && $user->shouldCompleteProfile();

            return response()->json([
                'status' => 'success',
                'message' => __('Login berhasil'),
                'data' => [
                    'token' => $token,
                    'user' => $user,
                    'needs_otp' => $needsOtp,
                    'needs_completion' => $needsCompletion,
                ],
            ]);
        }

        // Buat username unik yang aman
        $username = explode('@', $email)[0];
        $base = $username;
        $i = 1;
        while (User::query()->where('username', $username)->exists()) {
            $username = $base.$i++;
        }

        // User doesn't exist — register with Google data
        try {
            $user = User::create([
                'social_id' => $googleId,
                'social_type' => 'firebase_google',
                'username' => $username,
                'full_name' => $name,
                'first_name' => explode(' ', $name)[0],
                'last_name' => Str::after($name, ' ') ?: '',
                'email' => $email,
                'email_verified_at' => null,
                'avatar_url' => $avatarUrl,
                'active_status' => true,
                // 2FA TIDAK PERNAH aktif untuk Google OAuth users.
                'two_factor_type' => \MixCode\FilamentMulti2fa\Enums\TwoFactorAuthType::None->value,
                'two_factor_enabled' => false,
            ]);
        } catch (QueryException $e) {
            // MySQL: 1062 = Duplicate entry; PostgreSQL: 23505 = unique_violation
            $sqlState = $e->errorInfo[0] ?? '';
            $mysqlCode = $e->errorInfo[1] ?? 0;
            if ($mysqlCode == 1062 || $sqlState === '23505') {
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

            // 2FA aktif -> jangan terbitkan token dulu, minta challenge.
            if ($this->requiresTwoFactor($user)) {
                return $this->twoFactorChallenge($user);
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
            // MySQL: 1062 = Duplicate entry; PostgreSQL: 23505 = unique_violation
            $sqlState = $e->errorInfo[0] ?? '';
            $mysqlCode = $e->errorInfo[1] ?? 0;
            if ($mysqlCode == 1062 || $sqlState === '23505') {
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

            // 2FA aktif -> jangan terbitkan token dulu, minta challenge.
            if ($this->requiresTwoFactor($user)) {
                return $this->twoFactorChallenge($user);
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

        // Sign in sosial via API (facebook/apple) -- tidak memicu Login
        // event, jadi pengenalan perangkatnya dipicu di sini.
        DeviceTrustService::recognize($user);

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
