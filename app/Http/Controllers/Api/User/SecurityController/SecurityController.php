<?php

namespace App\Http\Controllers\Api\User\SecurityController;

use App\Http\Controllers\Controller;
use App\Mail\OtpMail\OtpMail;
use App\Models\BackupCode\BackupCode;
use App\Models\SecurityEmail\SecurityEmail;
use App\Models\TrustedDevice\TrustedDevice;
use App\Models\User\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use MixCode\FilamentMulti2fa\Enums\TwoFactorAuthType;
use PragmaRX\Google2FA\Google2FA;

class SecurityController extends Controller
{
    public function checkup(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        $items = [
            [
                'key' => 'password',
                'label_key' => 'checkupPassword',
                'description_key' => 'checkupPasswordDesc',
                'secured' => true,
                'detail_verified_key' => null,
                'detail_unverified_key' => null,
                'action_route' => '/change-password',
            ],
            [
                'key' => 'email',
                'label_key' => 'checkupEmail',
                'description' => $user->email,
                'secured' => ! empty($user->email_verified_at),
                'detail_verified_at' => $user->email_verified_at?->toISOString(),
                'detail_verified_key' => 'checkupEmailVerified',
                'detail_unverified_key' => 'checkupEmailNotVerified',
                'action_route' => null,
            ],
            [
                'key' => 'whatsapp',
                'label_key' => 'checkupWhatsapp',
                'description' => $user->whatsapp ?: null,
                'secured' => ! empty($user->whatsapp_verified_at),
                'detail_verified_at' => $user->whatsapp_verified_at?->toISOString(),
                'detail_verified_key' => 'checkupWhatsappVerified',
                'detail_unverified_key' => 'checkupWhatsappNotVerified',
                'action_route' => null,
            ],
            [
                'key' => 'two_factor',
                'label_key' => 'checkupTwoFactor',
                'description_key' => 'checkupTwoFactorDesc',
                'secured' => $user->two_factor_enabled ?? false,
                'detail_verified_key' => null,
                'detail_unverified_key' => 'checkupTwoFactorNotEnabled',
                'action_route' => '/two-factor-settings',
            ],
            [
                'key' => 'identity',
                'label_key' => 'checkupIdentity',
                'description_key' => 'checkupIdentityDesc',
                'secured' => ! empty($user->identity_verified_at),
                'detail_verified_at' => $user->identity_verified_at?->toISOString(),
                'detail_verified_key' => 'checkupIdentityVerified',
                'detail_unverified_key' => 'checkupIdentityNotVerified',
                'action_route' => null,
            ],
        ];

        $securedCount = collect($items)->where('secured', true)->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'items' => $items,
                'secured_count' => $securedCount,
                'total_items' => count($items),
            ],
        ]);
    }

    public function recentEmails(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        $emails = SecurityEmail::where('user_id', $user->id)
            ->orderByDesc('sent_at')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'status' => 'success',
            'data' => $emails->items(),
            'pagination' => [
                'current_page' => $emails->currentPage(),
                'last_page' => $emails->lastPage(),
                'per_page' => $emails->perPage(),
                'total' => $emails->total(),
            ],
        ]);
    }

    public static function recordSecurityEmail(int $userId, string $type, string $subjectKey, ?string $body = null): void
    {
        SecurityEmail::create([
            'user_id' => $userId,
            'type' => $type,
            'subject' => $subjectKey,
            'body' => $body,
            'sent_at' => now(),
        ]);
    }

    // ════════════════════════════════════════════════════════════════
    //  2FA
    // ════════════════════════════════════════════════════════════════

    public function twoFactorStatus(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'two_factor_enabled' => $user->two_factor_enabled ?? false,
                // Metode yang benar-benar aktif, bukan cuma toggle-nya.
                // Dipakai klien untuk memutuskan label tombol setup 2FA
                // ("Tambahkan" vs "Kelola"), sama seperti
                // `@php $isSetUp = $user?->two_factor_type?->value !== 'none';`
                // di two-factor.blade.php. Tanpa field ini, aplikasi tidak
                // bisa membedakan akun yang belum pernah setup 2FA dari akun
                // yang metodenya sudah email/totp.
                'two_factor_type' => $user->two_factor_type?->value ?? 'none',
                'whatsapp_number' => $user->whatsapp,
                'whatsapp_verified' => ! empty($user->whatsapp_verified_at),
                'backup_codes_remaining' => BackupCode::where('user_id', $user->id)->where('used', false)->count(),
                'trusted_devices_count' => TrustedDevice::where('user_id', $user->id)->count(),
            ],
        ]);
    }

    public function twoFactorToggle(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $enabled = $request->boolean('enabled');

        if ($enabled && empty($user->whatsapp)) {
            return response()->json([
                'status' => 'error',
                'message' => 'WhatsApp number is required to enable 2FA',
            ], 422);
        }

        $user->update(['two_factor_enabled' => $enabled]);

        if (! $enabled) {
            BackupCode::where('user_id', $user->id)->delete();
        }

        return response()->json([
            'status' => 'success',
            'message' => $enabled ? '2FA enabled' : '2FA disabled',
            'data' => ['two_factor_enabled' => $enabled],
        ]);
    }

    public function generateBackupCodes(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        BackupCode::where('user_id', $user->id)->delete();

        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $code = strtoupper(Str::random(4).'-'.Str::random(4));
            BackupCode::create([
                'user_id' => $user->id,
                'code' => $code,
            ]);
            $codes[] = $code;
        }

        return response()->json([
            'status' => 'success',
            'data' => ['backup_codes' => $codes],
        ]);
    }

    // ════════════════════════════════════════════════════════════════
    //  SETUP 2FA (padanan TwoFactorySetup / Auth\TwoFactorAuth)
    // ════════════════════════════════════════════════════════════════

    /**
     * Key cache kode OTP selama SETUP metode email.
     *
     * Sengaja BERBEDA dari '2fa_email_otp_' yang dipakai
     * AuthController::TWO_FACTOR_EMAIL_CACHE_KEY dan
     * OtpEmailOrTwoFactory (web).
     *
     * Key yang sama itu harus dipakai bersama oleh verifikasi 2FA di aplikasi
     * dan di panel web -- satu kode yang dikirim ke email berlaku di keduanya,
     * jadi kedua sisi harus melihat slot yang sama.
     *
     * Tapi SETUP tidak boleh ikut berbagi slot itu. Kalau iya, pressing
     * "Kirim Ulang" di halaman Two Factor Setup menimpa kode yang sedang
     * menunggu di tab login lain: pengguna di sana sudah menerima emailnya,
     * tapi kodenya mati dan tidak ada pesan yang menjelaskan kenapa.
     *
     * Kode setup memang tidak perlu cocok dengan web -- TwoFactorySetup milik
     * paket menyimpan kodenya di two_factor_secret (itulah yang membuatnya
     * menimpa TOTP), sedangkan alur ini menyimpannya di cache justru supaya
     * secret TOTP yang aktif tidak ikut hilang.
     */
    protected const SETUP_EMAIL_CACHE_KEY = '2fa_setup_otp_';

    /**
     * Tahap 1 setup: pilih metode, lalu dapat instruksi verifikasinya.
     *
     * Padanan TwoFactorySetup::setup() dari paket multi-2fa. Tiga metode,
     * masing-masing berakhir di layar verifikasi yang berbeda:
     *
     *   email -> kode 6 digit dikirim ke email, lalu verifikasi OTP
     *   totp  -> secret Base32 dikembalikan (plus URI otpauth:// untuk QR),
     *            lalu verifikasi TOTP
     *   none  -> langsung selesai, 2FA dimatikan
     *
     * Metode yang sudah aktif DITOLAK, sama seperti `disableOptionWhen()`
     * plus rule `verified_before` di Filament: metode yang sudah pernah
     * dipakai tidak boleh dipilih ulang sebagai setup baru.
     *
     * Soal `two_factor_secret`: kolom ini punya dua isi berbeda depending
     * pada metodenya -- Base32 polos untuk TOTP, dan `encrypt(kode)` untuk
     * OTP email (lihat UsingTwoFA::generateTwoFactorOTPCode()). Karena itu
     * kode email selalu disimpan di cache dengan key terpisah, dan TOTP
     * selalu dibaca sebagai Base32 polos. Kalau tidak, verifikasi email
     * akan menimpa TOTP yang sedang aktif.
     */
    public function setupTwoFactor(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        $type = (string) $request->input('type');

        if (! in_array($type, ['email', 'totp', 'none'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => __('Metode verifikasi tidak valid.'),
            ], 422);
        }

        $current = $user->two_factor_type?->value ?? TwoFactorAuthType::None->value;

        if ($type !== 'none' && $type === $current) {
            return response()->json([
                'status' => 'error',
                'message' => __('Anda sudah pernah memverifikasi akun Anda dengan metode ini sebelumnya.'),
            ], 422);
        }

        if ($type === 'none') {
            $user->forceFill([
                'two_factor_type' => TwoFactorAuthType::None->value,
                'two_factor_secret' => null,
                'two_factor_confirmed_at' => null,
                'two_factor_expires_at' => null,
                'two_factor_sent_at' => null,
                'two_factor_enabled' => false,
            ])->save();

            BackupCode::where('user_id', $user->id)->delete();
            Cache::forget(self::SETUP_EMAIL_CACHE_KEY.$user->id);

            return response()->json([
                'status' => 'success',
                'message' => __('Autentikasi dua faktor dinonaktifkan.'),
                'data' => ['type' => 'none'],
            ]);
        }

        if ($type === 'email') {
            try {
                $this->sendSetupEmailCode($user);
            } catch (\Throwable) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Kode OTP gagal dikirim. Periksa konfigurasi email lalu coba lagi.'),
                ], 500);
            }

            return response()->json([
                'status' => 'success',
                'message' => __('Kode OTP telah dikirim ke email Anda.'),
                'data' => [
                    'type' => 'email',
                    'method' => 'email',
                    'email' => $user->email,
                ],
            ]);
        }

        // totp
        $secret = (new Google2FA)->generateSecretKey();
        $issuer = (string) config('app.name');

        $user->forceFill(['two_factor_secret' => $secret])->save();

        return response()->json([
            'status' => 'success',
            'message' => __('Pindai kode QR dengan aplikasi autentikasi, lalu masukkan kode 6 digit untuk memastikan semuanya benar.'),
            'data' => [
                'type' => 'totp',
                'method' => 'authenticator',
                'secret' => $secret,
                // URI yang persis sama dengan isi QR code di panel Filament,
                // jadi satu secret berlaku di kedua antarmuka.
                'otpauth_uri' => sprintf(
                    'otpauth://totp/%s:%s?secret=%s&issuer=%s',
                    rawurlencode($issuer),
                    rawurlencode((string) $user->email),
                    $secret,
                    rawurlencode($issuer),
                ),
            ],
        ]);
    }

    /** Tahap 2: verifikasi kode, baru simpan 2FA sebagai aktif. */
    public function verifyTwoFactorSetup(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        $type = (string) $request->input('type');
        $code = trim((string) $request->input('code'));

        if (! in_array($type, ['email', 'totp'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => __('Metode verifikasi tidak valid.'),
            ], 422);
        }

        if ($code === '') {
            return response()->json([
                'status' => 'error',
                'message' => __('Kode verifikasi wajib diisi.'),
            ], 422);
        }

        $secret = $user->two_factor_secret;

        if ($type === 'totp') {
            $lolos = $secret && (new Google2FA)->verifyKey((string) $secret, $code);
        } else {
            $tersimpan = Cache::get(self::SETUP_EMAIL_CACHE_KEY.$user->id);
            $lolos = $tersimpan !== null && hash_equals((string) $tersimpan, $code);
        }

        if (! $lolos) {
            return response()->json([
                'status' => 'error',
                'message' => __('Kode OTP tidak valid atau telah kadaluarsa.'),
            ], 422);
        }

        $attributes = [
            'two_factor_type' => $type === 'email'
                ? TwoFactorAuthType::Email->value
                : TwoFactorAuthType::Totp->value,
            'two_factor_confirmed_at' => now(),
            'two_factor_expires_at' => null,
            'two_factor_sent_at' => null,
            'two_factor_enabled' => true,
        ];

        // TOTP: secret HARUS dipertahankan -- di situlah TOTP-nya disimpan,
        // dan menghapusnya membuat 2FA aktif tapi tidak bisa diverifikasi.
        // Email: secret justru dibuang, karena untuk metode ini nilainya
        // tidak pernah dipakai.
        if ($type === 'totp') {
            $attributes['two_factor_secret'] = $secret;
        } else {
            $attributes['two_factor_secret'] = null;
        }

        $user->forceFill($attributes)->save();
        Cache::forget(self::SETUP_EMAIL_CACHE_KEY.$user->id);

        // Kode cadangan dibuat di sini, persis seperti TwoFactorySetup --
        // tanpa itu akun aktif tanpa pintu pemulihan sama sekali.
        BackupCode::where('user_id', $user->id)->delete();
        $backupCodes = [];
        for ($i = 0; $i < 10; $i++) {
            $backup = strtoupper(Str::random(4).'-'.Str::random(4));
            BackupCode::create(['user_id' => $user->id, 'code' => $backup]);
            $backupCodes[] = $backup;
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Autentikasi dua faktor berhasil diaktifkan.'),
            'data' => [
                'type' => $type,
                'two_factor_type' => $user->two_factor_type->value,
                'backup_codes' => $backupCodes,
            ],
        ]);
    }

    /** Kirim ulang kode OTP selama setup metode email. */
    public function resendTwoFactorSetupOtp(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        try {
            $this->sendSetupEmailCode($user);
        } catch (\Throwable) {
            return response()->json([
                'status' => 'error',
                'message' => __('Kode OTP gagal dikirim. Periksa konfigurasi email lalu coba lagi.'),
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Kode OTP baru telah dikirim.'),
        ]);
    }

    /**
     * Kirim kode setup ke email.
     *
     * Cache dibersihkan kalau pengiriman gagal, supaya tombol "Kirim Ulang"
     * tetap bisa dipakai -- kalau tidak, OTP tersimpan di cache tapi tidak
     * pernah sampai ke pengguna, dan formnya tidak bisa diisi.
     */
    protected function sendSetupEmailCode(User $user): void
    {
        $key = self::SETUP_EMAIL_CACHE_KEY.$user->id;
        $code = (string) random_int(100000, 999999);

        Cache::put($key, $code, now()->addMinutes(10));

        try {
            Mail::to($user->email)->send(new OtpMail($code, $user->name ?? 'Pengguna'));
        } catch (\Throwable $e) {
            Cache::forget($key);

            throw $e;
        }
    }

    // ════════════════════════════════════════════════════════════════
    //  TRUSTED DEVICES
    // ════════════════════════════════════════════════════════════════

    public function trustedDevices(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        $devices = TrustedDevice::where('user_id', $user->id)
            ->orderByDesc('trusted_at')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $devices,
        ]);
    }

    public function removeTrustedDevice(int $deviceId): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        TrustedDevice::where('user_id', $user->id)->where('id', $deviceId)->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Device removed',
        ]);
    }

    /**
     * Hapus semua perangkat terpercaya sekaligus.
     *
     * Padanan removeAllTrustedDevices() di panel Filament. Endpoint ini ada
     * karena aplikasi mobile tidak punya jalan lain: ia tidak bisa menjalankan
     * query langsung, jadi tanpa endpoint ini "Hapus Semua Perangkat" harus
     * dijawab dengan N request hapus-satu-pemu-satuan dari klien.
     */
    public function removeAllTrustedDevices(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        TrustedDevice::where('user_id', $user->id)->delete();

        return response()->json([
            'status' => 'success',
            'message' => __('Semua perangkat tepercaya dihapus.'),
        ]);
    }

    // ════════════════════════════════════════════════════════════════
    //  SESI PERANGKAT
    // ════════════════════════════════════════════════════════════════

    /**
     * Keluarkan semua sesi perangkat lain milik user yang sedang masuk.
     *
     * Padanan logoutOtherBrowserSessions() di panel Filament.
     *
     * PASSWORD WAJIB, sama seperti di sana.
     *
     * BrowserSessionsComponent::logoutOtherBrowserSessions() meminta
     * password di dialog konfirmasi lalu membandingkannya dengan
     * Hash::check() sebelum menghapus sesi apa pun. Endpoint ini
     * sebelumnya menerima permintaan tanpa password sama sekali, jadi
     * pemeriksaan itu bisa dilewati seluruhnya dari aplikasi: siapa pun
     * yang memegang token bisa memutus akses orang lain dari semua
     * perangkat tanpa membuktikan bahwa dia pemilik akun.
     *
     * Password tidak pernah dicatat di log atau dikembalikan dalam respons.
     *
     * Penghapusan baris session hanya berlaku kalau driver session memakai
     * database. Untuk driver lain tidak ada tabel yang bisa dihapus, dan
     * mengarang-"menghapus" dengan mengosongkan tabel yang tidak dipakai akan
     * merusak data driver itu -- jadi lebih baik melaporkan tidak ada yang
     * dihapus.
     */
    public function removeAllOtherSessions(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        if (! $request->filled('password')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Kata sandi wajib diisi.'),
                'errors' => ['password' => [__('Kata sandi wajib diisi.')]],
            ], 422);
        }

        if (! Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => __('Kata sandi yang diberikan tidak cocok dengan catatan kami.'),
                'errors' => ['password' => [__('Kata sandi yang diberikan tidak cocok dengan catatan kami.')]],
            ], 422);
        }

        if (config('session.driver') !== 'database') {
            return response()->json([
                'status' => 'success',
                'message' => __('Semua sesi perangkat lain telah dikeluarkan.'),
                'data' => ['removed' => 0, 'supported' => false],
            ]);
        }

        $removed = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->where('id', '!=', request()->session()->getId())
            ->delete();

        return response()->json([
            'status' => 'success',
            'message' => __('Semua sesi perangkat lain telah dikeluarkan.'),
            'data' => ['removed' => $removed, 'supported' => true],
        ]);
    }

    // ════════════════════════════════════════════════════════════════
    //  UBAH EMAIL (OTP)
    // ════════════════════════════════════════════════════════════════

    /**
     * Tahap 1: kirim OTP ke email baru.
     *
     * Email BELUM diubah di sini. Email baru disimpan di cache dan OTP-nya
     * disimpan terpisah, persis seperti changeEmailForm() di panel Filament.
     *
     * Kenapa tidak langsung mengubah email: kalau email diganti dulu lalu OTP
     * gagal dikirim, akun sekarang punya email baru yang tidak milik siapa
     * pun -- dan OTP-nya tidak pernah sampai. Satu-satunya jalan keluar
     * menjadi "Lupa Kata Sandi" ke email yang sudah tidak lagi dikuasai.
     */
    public function sendEmailChangeOtp(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'email_confirmation' => ['required', 'same:email'],
        ]);

        $otp = (string) random_int(100000, 999999);

        Cache::put('change_email_otp_'.$user->id, $otp, now()->addMinutes(10));
        Cache::put('change_email_pending_'.$user->id, $request->email, now()->addMinutes(10));

        try {
            Mail::to($request->email)->send(new OtpMail($otp, $user->name ?? 'Pengguna'));
        } catch (\Throwable) {
            // Cache dibersihkan supaya tombol "Kirim Ulang" tetap bisa dipakai.
            // Kalau tidak, OTP tersimpan tapi tidak pernah sampai ke pengguna.
            // Email lama juga dibiarkan seperti semula: OTP gagal terkirim
            // bukan berarti email barunya salah.
            Cache::forget('change_email_otp_'.$user->id);
            Cache::forget('change_email_pending_'.$user->id);

            return response()->json([
                'status' => 'error',
                'message' => __('Kode OTP gagal dikirim. Periksa konfigurasi email lalu coba lagi.'),
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Kode OTP telah dikirim ke email baru Anda.'),
        ]);
    }

    /**
     * Tahap 2: verifikasi OTP, baru email diganti.
     */
    public function verifyEmailChangeOtp(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $otp = Cache::get('change_email_otp_'.$user->id);
        $pendingEmail = Cache::get('change_email_pending_'.$user->id);

        if (! $otp || ! $pendingEmail || ! hash_equals((string) $otp, trim((string) $request->otp))) {
            return response()->json([
                'status' => 'error',
                'message' => __('Kode OTP tidak valid atau telah kadaluarsa.'),
            ], 422);
        }

        $user->update(['email' => $pendingEmail, 'email_verified_at' => now()]);

        SecurityEmail::create([
            'user_id' => $user->id,
            'type' => 'email_changed',
            'subject' => __('Email akun berhasil diubah'),
            'body' => 'email_changed_at:'.now()->toDateTimeString(),
            'sent_at' => now(),
        ]);

        Cache::forget('change_email_otp_'.$user->id);
        Cache::forget('change_email_pending_'.$user->id);

        // Token lama ikut dibuang: email adalah identitas, dan sesi yang
        // dibuat dengan email lama tidak lagi mewakili akun ini.
        $user->tokens()->where('id', '!=', $user->currentAccessToken()?->id)->delete();

        return response()->json([
            'status' => 'success',
            'message' => __('Email berhasil diubah dan diverifikasi.'),
            'data' => ['email' => $user->email],
        ]);
    }

    // ════════════════════════════════════════════════════════════════
    //  SAVED LOGIN
    // ════════════════════════════════════════════════════════════════

    public function savedLoginStatus(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'saved_login_enabled' => $user->saved_login_enabled ?? true,
            ],
        ]);
    }

    public function savedLoginToggle(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $user->update(['saved_login_enabled' => $request->boolean('enabled')]);

        return response()->json([
            'status' => 'success',
            'data' => ['saved_login_enabled' => $user->saved_login_enabled],
        ]);
    }
}
