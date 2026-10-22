<?php

namespace App\Support\DeviceTrust;

use App\Filament\User\Pages\DeviceTrustPrompt\DeviceTrustPrompt;
use App\Filament\Welcome\Pages\SettingsPage\PasswordSecurityPage\PasswordSecurityPage;
use App\Models\TrustedDevice\TrustedDevice;
use App\Models\User\User;
use App\Services\GeoLocationService\GeoLocationService;
use App\Services\PlatformNotificationService\PlatformNotificationService;
use Illuminate\Support\Facades\DB;
use Jenssegers\Agent\Agent;

/*
 * Pengenal perangkat + notifikasi keamanan ala Google/Instagram.
 *
 * Setiap kali akun berhasil sign in, perangkatnya diwakili fingerprint =
 * hash User-Agent. Ini STABIL per perangkat -- beda dari signature di tabel
 * trust_devices milik paket multi-2fa yang memakai random salt dan hanya
 * ada bagi pengguna 2FA.
 *
 * Aturannya:
 *
 *   - fingerprint BELUM pernah dipakai user itu
 *       -> baris trusted_devices baru dibuat (trusted_at NULL), lalu user
 *          ditanya "Perangkat Baru -- Apakah Ini Anda?" lewat satu tombol
 *          "Tinjau Perangkat" yang membuka halaman DeviceTrustPrompt
 *          (di sana pilihannya "Ya, Ini Saya" / "Bukan Saya").
 *
 *   - fingerprint dikenal + trusted_at terisi
 *       -> diam. Seperti Google: perangkat yang sudah dipercaya tidak lagi
 *          dimintai konfirmasi tiap login. (last_seen_at tetap diperbarui.)
 *
 *   - fingerprint dikenal + belum trusted
 *       -> informasikan "Login Terdeteksi" dengan informasi perangkat --
 *          perilaku lama yang dipertahankan.
 *
 * Semua jalur sign in yang memicu Illuminate\Auth\Events\Login tercangkup
 * otomatis lewat listener AppServiceProvider; jalur API mobile yang tidak
 * memicu event itu (AuthController::login) memanggil recognize() langsung.
 *
 * Semua metode aman-dipanggil-dari-manapun: jawaban user tidak pernah
 * menggagalkan login, dan semua akses model dipagari miliknya sendiri.
 */
class DeviceTrustService
{
    /** Baris trusted_devices yang paling baru dibuat/diperbarui + statusnya. */
    public function __construct(
        public TrustedDevice $device,
        public bool $isNew,
    ) {}

    /**
     * Catat kunjungan perangkat, lalu kirim notifikasi sesuai statusnya.
     * Dipanggil SETELAH autentikasi berhasil.
     */
    public static function recognize(User $user): self
    {
        $geo = static::geolocation();

        $known = static::findDevice($user);

        if ($known) {
            $known->forceFill([
                'last_seen_at' => now(),
                'last_ip' => $geo['ip'],
                'last_city' => $geo['city'],
                'last_region' => $geo['region'],
                'last_country' => $geo['country'],
            ])->save();

            static::notify($user, $known, isNew: false);

            return new self($known, false);
        }

        $device = TrustedDevice::create([
            'user_id' => $user->getKey(),
            'device_fingerprint' => static::fingerprint(),
            'device_name' => static::describe(),
            'platform' => static::platformName(),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'last_ip' => $geo['ip'],
            'last_city' => $geo['city'],
            'last_region' => $geo['region'],
            'last_country' => $geo['country'],
            'trusted_at' => null,
        ]);

        static::notify($user, $device, isNew: true);

        return new self($device, true);
    }

    /**
     * Notifikasi sesuai status perangkat. Perangkat yang sama tidak dipenuhi
     * lagi kalau notifikasinya baru lewat beberapa detik -- refresh dan
     * login ulang tidak perlu menimbulkan notifikasi beruntun.
     */
    protected static function notify(User $user, TrustedDevice $device, bool $isNew): void
    {
        if (! $isNew && $device->last_notified_at?->gt(now()->subSeconds(30))) {
            return;
        }

        $device->forceFill(['last_notified_at' => now()])->save();

        try {
            PlatformNotificationService::withRecipientLocale($user, function () use ($user, $device, $isNew): void {
                $location = $device->locationSentence();
                $time = now()->format('d M Y H:i:s');

                if ($isNew) {
                    PlatformNotificationService::send(
                        $user,
                        __('Perangkat Baru -- Apakah Ini Anda?'),
                        __('Sign in dari perangkat yang belum pernah dipakai oleh akun Anda: :device, dari :ip (:location) pada :time. Kalau ini bukan Anda, keluarkan semua sesi sekarang.', [
                            'device' => $device->displayLabel(),
                            'ip' => $device->last_ip ?: '-',
                            'location' => $location,
                            'time' => $time,
                        ]),
                        DeviceTrustPrompt::getUrl(['id' => $device->id], panel: 'welcome'),
                        __('Tinjau Perangkat'),
                        // Data untuk aplikasi HP: type 'login' masuk kanal
                        // keamanan (security_notifications) + gate
                        // NotificationPrefs, route membawa tap langsung ke
                        // halaman Perangkat Tepercaya di HP.
                        ['type' => 'login', 'route' => '/trusted-devices', 'id' => (string) $device->id],
                    );
                } elseif ($device->trusted_at) {
                    // Perangkat sudah dipercaya: tidak perlu notifikasi lagi.
                } else {
                    // Perilaku lama "Login Terdeteksi", diperkaya penyebut
                    // nama perangkat supaya user tidak perlu menebak.
                    // Tombolnya tetap menunjuk halaman "Tempat Anda Sign In"
                    // -- kontrak yang dipegang PasswordSecurityPagesTest.
                    PlatformNotificationService::send(
                        $user,
                        __('Login Terdeteksi'),
                        __('Akun Anda telah digunakan dari :device (:ip, :location) pada :time.', [
                            'device' => $device->displayLabel(),
                            'ip' => $device->last_ip ?: '-',
                            'location' => $location,
                            'time' => $time,
                        ]),
                        PasswordSecurityPage::getUrl(['section' => 'sign-in-activity'], panel: 'welcome'),
                        __('Lihat aktivitas Sign In'),
                        ['type' => 'login', 'route' => '/login-activity'],
                    );
                }
            });
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Jawaban "Ya, Ini Saya" -- perangkat itu dianggap milik user.
     */
    public static function trust(TrustedDevice $device, User $user): void
    {
        static::ensureOwned($device, $user);

        $device->forceFill([
            'trusted_at' => now(),
            'denied_at' => null,
        ])->save();
    }

    /**
     * Jawaban "Bukan saya" -- keluarkan semua sesi web lain + token HP,
     * bersihkan perangkat milik paket multi-2fa, tandai perangkat ini.
     */
    public static function deny(TrustedDevice $device, User $user): void
    {
        static::ensureOwned($device, $user);

        $device->forceFill(['denied_at' => now()])->save();

        // Sesi web (tabel sessions, driver database). Sesi browser yang SEDANG
        // meninjau dipertahankan supaya respons Livewire tidak mati di tengah
        // jalan -- perilaku Instagram: yang meninjau tetap sesi, sisanya
        // keluar semua.
        //
        // hasSession(): rute API (Sanctum) tidak punya session store --
        // request()->session() di sana melempar RuntimeException, jadi
        // peninjau berupa HP tidak punya "sesi saat ini" untuk dikecualikan.
        if (config('session.driver') === 'database') {
            $query = DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $user->getAuthIdentifier());

            if (request()->hasSession()) {
                $query->where('id', '!=', request()->session()->getId());
            }

            $query->delete();
        }

        // Token aplikasi HP (Sanctum): aplikasi HP langsung kehilangan akses.
        $user->tokens()->delete();

        // Tabel "trust_devices" milik paket multi-2fa -- barisnya dibersih-
        // kan supaya sesi yang menumpang cookie `trusted_device` ikut lepas.
        $trustDeviceModel = config('filament-multi-2fa.trust_device_model');

        if (is_string($trustDeviceModel) && class_exists($trustDeviceModel)) {
            $trustDeviceModel::where('user_id', $user->getKey())->delete();
        }

        PlatformNotificationService::sendToWebOnly(
            $user,
            __('Bukan Anda?'),
            __('Semua sesi web lain dan token HP pada akun ini sudah dikeluarkan. Ubah kata sandi Anda di menu Sandi & Keamanan.'),
        );
    }

    // ----------------------------------------------------------------------
    // Identifikasi perangkat
    // ----------------------------------------------------------------------

    /**
     * Baris perangkat user yang sedang sign in, dicari lewat pasangan
     * (user_id, device_fingerprint) supaya query cukup ikut indeks. Baris
     * legacy yang kolom device_fingerprint-nya NULL tidak akan pernah
     * cocok dengan fingerprint pengguna, jadi tidak perlu dibersihkan.
     */
    protected static function findDevice(User $user): ?TrustedDevice
    {
        return TrustedDevice::query()
            ->where('user_id', $user->getKey())
            ->where('device_fingerprint', static::fingerprint())
            ->first();
    }

    /**
     * Pengenal perangkat: hash User-Agent saja -- TANPA IP, karena IP LAN
     * ganti-ganti dan tabel TrustDevice milik paket 2FA justru memakai
     * random salt untuk itu; di sini identitas perangkat harus stabil.
     */
    public static function fingerprint(): string
    {
        return hash('sha256', (string) request()->userAgent());
    }

    /**
     * Nama perangkat yang manusiawi: "Chrome di Windows". Pakai parser
     * Jenssegers\Agent yang juga dipakai BrowserSessionsComponent agar
     * labelnya senada dengan daftar sesi di halaman Sandi & Keamanan.
     */
    public static function describe(): string
    {
        $userAgent = (string) request()->userAgent();

        $agent = new Agent;
        $agent->setUserAgent($userAgent);

        $browser = $agent->browser();

        if (! filled($browser)) {
            // UA tidak terbaca (CLI, curl tanpa header) -- pakai apa adanya.
            return mb_substr($userAgent, 0, 80) ?: __('Perangkat tidak dikenal');
        }

        $where = $agent->isDesktop() ? $agent->platform() : $agent->device();

        if ((is_string($where) === false) || ! filled($where) || $where === 'WebKit') {
            $where = null;
        }

        return filled($where) ? "{$browser} di {$where}" : $browser;
    }

    /**
     * Kategori platform untuk kolom `platform`: sama dengan kata sandi UI
     * "Perangkat Tepercaya" -- ponsel / tablet / komputer.
     */
    protected static function platformName(): string
    {
        $agent = new Agent;
        $agent->setUserAgent((string) request()->userAgent());

        return match (true) {
            $agent->isTablet() => __('tablet'),
            $agent->isMobile() => __('ponsel'),
            default => __('komputer'),
        };
    }

    /**
     * IP + lokasi, pakai GeoLocationService yang sama dengan listener lama.
     *
     * @return array{ip: ?string, city: ?string, region: ?string, country: ?string}
     */
    protected static function geolocation(): array
    {
        try {
            /** @var GeoLocationService $geo */
            $geo = app(GeoLocationService::class);
            $location = $geo->lookup(request()->ip() ?? '');
        } catch (\Throwable) {
            $location = [];
        }

        return [
            'ip' => request()->ip(),
            'city' => $location['city'] ?? null,
            'region' => $location['region'] ?? null,
            'country' => $location['country'] ?? null,
        ];
    }

    /**
     * Semua tahap jawaban milik user sendiri -- perangkat milik orang lain
     * tidak bisa dipercaya/ditolak dari halaman akun user lain.
     */
    protected static function ensureOwned(TrustedDevice $device, User $user): void
    {
        if ($device->user_id !== $user->getKey()) {
            abort(403);
        }
    }
}
