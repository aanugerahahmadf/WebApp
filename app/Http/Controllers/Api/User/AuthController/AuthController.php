<?php

namespace App\Http\Controllers\Api\User\AuthController;

use App\Http\Controllers\Controller;
use App\Mail\OtpMail\OtpMail;
use App\Models\User\User;
use App\Models\WhatsappOtp\WhatsappOtp;
use App\Services\StorageService\StorageService;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
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
            'password' => 'required|string|min:12|confirmed',
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
        $request->validate([
            'login' => 'required',
            'password' => 'required',
        ]);

        $login = $request->login;
        $fieldType = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $user = User::where($fieldType, $login)->orWhere('ktp_number', $login)->orWhere('passport_number', $login)->orWhere('sim_number', $login)->orWhere('npwp_number', $login)->first();

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
            'otp_expires_at' => now()->addMinutes(5),
            'otp_purpose' => 'forgot_password',
        ]);

        Mail::to($request->email)->send(new OtpMail($otp, $user->name ?? 'Pengguna'));

        return response()->json([
            'status' => 'success',
            'message' => 'Instruksi reset password akan dikirim ke email Anda.',
            'email' => $user->email,
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
            'password' => 'required|confirmed|min:12',
        ]);

        $user = User::where('email', $request->email)
            ->where('otp_code', $request->otp)
            ->where('otp_purpose', 'forgot_password')
            ->where('otp_expires_at', '>', now())
            ->first();

        if (! $user) {
            return response()->json(['status' => 'error', 'message' => __('Kode OTP tidak valid atau sudah kedaluwarsa')], 422);
        }

        $user->update([
            'password' => $request->password,
            'otp_code' => null,
            'otp_expires_at' => null,
            'otp_purpose' => null,
        ]);

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
            'otp_expires_at' => now()->addMinutes(5),
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
