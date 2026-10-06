<?php

namespace App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\Concerns\HandlesPasswordSecurity;

use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\PasswordSecurityPage;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SecurityCheck\Checkup\Checkup;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SecurityCheck\RecentEmails\RecentEmails;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SecurityCheck\SignInActivity\SignInActivity;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SignInAndRecovery\ChangePassword\ChangePassword;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SignInAndRecovery\SavedLogin\SavedLogin;
use App\Support\PasswordPolicy\PasswordPolicy;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SignInAndRecovery\TwoFactor\TwoFactor;
use App\Filament\User\Pages\SettingsPage\SettingsPage;
use App\Models\BackupCode\BackupCode;
use App\Models\SecurityEmail\SecurityEmail;
use App\Models\TrustedDevice\TrustedDevice;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;

/**
 * Logika bersama untuk semua halaman "Kata Sandi dan Keamanan".
 *
 * Dipisah dari kelasnya sendiri karena ketujSlashnya halaman dulu memakai
 * satu kelas giant dengan query ?section=, dan semua method-nya(numlogic
 * two-factor, backup code, ganti email, session) jadi berada di satu tempat
 * yang tidak jelas kepunyaan halaman mana.
 *
 * Sekarang tiap bagian punya halaman sendiri; yang tersisa di sini hanya
 * benar-benar dipakai lebih dari satu, atau dipakai satu tapi tidak layak
 * jadi kelas tersendiri.
 *
 * Halaman yang memakai:
 *   PasswordSecurity    -> PasswordSecurity    (daftar / index)
 *   ChangePassword      -> ChangePassword
 *   TwoFactor           -> TwoFactor
 *   TwoFactorySetup     -> TwoFactorySetup
 *   SavedLogin          -> SavedLogin
 *   SignInActivity      -> SignInActivity
 *   RecentEmails        -> RecentEmails
 *   Checkup             -> Checkup
 *
 * Yang TIDAK ada di sini dan sengaja per halaman: mount(), getSlug(),
 * getHeading(), getSubheading(), getHeaderActions(), getBreadcrumbs(),
 * getForms() -- semuanya ikut kelas,halaman masing-masing supaya tidak
 * perlu ditimpa yang tidak relevan.
 */
trait HandlesPasswordSecurity
{
    public array $passwordData = ['logout_others' => false];

    public array $emailData = [];

    public bool $emailOtpSent = false;

    public bool $requestSignInEnabled = false;

    public bool $checkupCompleted = false;

    #[Url(as: 'returnTo')]
    public ?string $returnTo = null;

    /**
     * Kalau halaman ini dibuka dari detail notifikasi, rantai crumb harus
     * naik ke notifikasi dulu -- bukan langsung ke daftar Keamanan.
     *
     * Dulu ini jadi tombol "Kembali ke Detail Notifikasi" di header.
     *
     * Hanya URL notifikasi yang diterima: returnTo datang dari query string,
     * jadi tanpa pengejelan ini ia bisa mengarahkan crumb ke situs luar.
     */
    protected function notificationDetailReturnUrl(): ?string
    {
        if (blank($this->returnTo)) {
            return null;
        }

        $notificationUrlPrefix = url('/user/notifications/');

        return str_starts_with($this->returnTo, $notificationUrlPrefix)
            ? $this->returnTo
            : null;
    }

    /**
     * Dua kelompok halaman, dengan label yang dipakai SEKALI di dua tempat:
     * sebagai heading di daftar, dan sebagai crumb di halaman detail.
     *
     * Dipisah dari view karena label crumb dan label heading harus sama;
     * kalau masing-masing ditulis sendiri, keduanya akan mulai berbeda.
     *
     * @return array<int, array{label: string, items: array<int, array{0: string, 1: string, 2: string, 3: string}>}>
     */
    public static function securityGroups(): array
    {
        return [
            [
                'label' => __('Sign In dan Pemulihan'),
                'items' => [
                    [ChangePassword::class, 'heroicon-o-lock-closed', __('Ubah Kata Sandi'), __('Perbarui kata sandi dan lindungi akun Anda.')],
                    [TwoFactor::class, 'heroicon-o-shield-check', __('Autentikasi Dua Faktor'), __('Atur WhatsApp, kode cadangan, dan perangkat tepercaya.')],
                    [SavedLogin::class, 'heroicon-o-bookmark', __('Sign In Tersimpan'), __('Kelola info Sign In yang tersimpan di perangkat.')],
                ],
            ],
            [
                'label' => __('Pemeriksaan Keamanan'),
                'items' => [
                    [SignInActivity::class, 'heroicon-o-map-pin', __('Tempat Anda Sign In'), __('Lihat dan keluarkan sesi perangkat yang aktif.')],
                    [RecentEmails::class, 'heroicon-o-envelope', __('Ubah Email'), __('Ganti email akun dengan verifikasi kode OTP.')],
                    [Checkup::class, 'heroicon-o-shield-check', __('Pemeriksaan Keamanan'), __('Tinjau perlindungan penting untuk akun Anda.')],
                ],
            ],
        ];
    }

    /**
     * Label kelompok halaman ini, atau null kalau halaman ini bukan salah
     * satu butir daftar (mis. index-nya sendiri).
     *
     * Diturunkan dari securityGroups() daripada ditulis per halaman, supaya
     * halaman yang baru tidak bisa lupa mengisi kelompoknya -- crumbnya akan
     * kembali ke daftar tanpa menampilkan kelompok, persis gejala yang
     * sebelumnya ada.
     */
    protected function securityGroupLabel(): ?string
    {
        foreach (self::securityGroups() as $group) {
            foreach ($group['items'] as [$page]) {
                if ($page === static::class) {
                    return $group['label'];
                }
            }
        }

        return null;
    }

    /**
     * Rantai crumb lengkap:
     *
     *   Pengaturan / Kata Sandi dan Keamanan / [Kelompok] / Judul
     *
     * Tidak memakai breadcrumbParentCrumb() dari HasDynamicBreadcrumbs:
     * parent di sini bukan "halaman asal klik" tapi struktur tetap. Kalau
     * ikut dinamis, crumb akan berubah-ubah tergantung mana yang diklik
     * sebelumnya -- dan bisa hilang total saat halaman dibuka langsung dari
     * bookmark, sehingga tidak ada cara naik sama sekali.
     *
     * Kelompok sengaja crumb tanpa URL: dia bukan halaman, cuma pengelompok,
     * jadi tidak boleh diklik.
     *
     * @return array<string|\Illuminate\Support\HtmlString, string|\Illuminate\Support\HtmlString>
     */
    protected function securityBreadcrumbs(): array
    {
        $crumbs = [
            SettingsPage::getUrl(panel: 'user') => __('Pengaturan'),
            PasswordSecurityPage::getUrl(panel: 'user') => __('Kata Sandi dan Keamanan'),
        ];

        // Dikirim dari detail notifikasi: naik ke notifikasi dulu, lalu ke
        // daftar Keamanan. Sebelumnya jadi tombol; sekarang jadi crumb.
        if ($notificationDetailUrl = $this->notificationDetailReturnUrl()) {
            $crumbs[$notificationDetailUrl] = __('Detail Notifikasi');
            $crumbs[PasswordSecurityPage::getUrl(panel: 'user')] = __('Kata Sandi dan Keamanan');
        }

        if ($group = $this->securityGroupLabel()) {
            // Kalau judul halaman sama dengan label kelompok -- terjadi di
            // Checkup, yang bernama "Pemeriksaan Keamanan" dan bergrup pada
            // "Pemeriksaan Keamanan" -- crumb kelompok disembunyikan saja.
            // Menampilkan keduanya berarti dua crumb identik berturut-turut,
            // yang terbaca sebagai bug, bukan sebagai informasi.
            if ($group !== $this->getTitle()) {
                // Kunci integer -> Filament merender <span>, bukan <a>.
                $crumbs[] = $group;
            }
        }

        // Judul halaman sebagai crumb terakhir.
        //
        // Dikecualikan kalau rantainya sudah memuat halaman ini sendiri --
        // itu hanya terjadi di index, karena crumb induknya persis halaman
        // ini. Tanpa pengecualian, index menampilkan "Kata Sandi dan
        // Keamanan" dua kali berturut-turut.
        //
        // Pengecualiannya TIDAK boleh membandingkan judul dengan crumb
        // terakhir: Checkup punya judul "Pemeriksaan Keamanan" yang sama
        // dengan label kelompoknya, dan cara itu membuat judulnya hilang --
        // crumbsnya lalu berhenti di kelompok tanpa nama halaman.
        $urls = array_keys(array_filter($crumbs, 'is_string', ARRAY_FILTER_USE_KEY));

        if (! in_array($this->getUrl(), $urls, true)) {
            $crumbs[] = $this->getTitle();
        }

        return $crumbs;
    }

    /**
     * Tidak ada tombol kembali.
     *
     * Akses naik ditangani breadcrumb (securityBreadcrumbs()). Tombol
     * sebelumnya selalu mengarah ke daftar Keamanan, sehingga dari situ
     * pengguna harus memilih kelompok lagi -- dan tidak ada cara langsung
     * kembali ke Pengaturan.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
    /* ---------------------------------------------------------------------
     | Ubah kata sandi
     | ------------------------------------------------------------------ */

    /** Native Filament form: mewarisi seluruh style input, label, validasi,
     *  dark mode, dan spacing responsif dari panel, bukan HTML custom. */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('Ubah Kata Sandi'))
                    ->description(__('Gunakan kata sandi yang kuat untuk menjaga keamanan akun Anda.'))
                    ->schema([
                        TextInput::make('current_password')
                            ->label(__('Kata Sandi Saat Ini'))
                            ->password()
                            ->revealable()
                            ->required()
                            ->helperText(fn (): string => __('Diperbarui :date', ['date' => Auth::user()?->updated_at?->translatedFormat('d F Y') ?? '-'])),
                        TextInput::make('password')
                            ->label(__('Kata Sandi Baru'))
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(8)
                            ->regex('/[A-Z]/')
                            ->regex('/[a-z]/')
                            ->regex('/[0-9]/')
                            ->regex('/[^A-Za-z0-9]/')
                            ->helperText(__('Minimal 8 karakter, huruf besar, huruf kecil, angka, dan simbol.'))
                            ->live(),
                        TextInput::make('password_confirmation')
                            ->label(__('Konfirmasi Kata Sandi Baru'))
                            ->password()
                            ->revealable()
                            ->required()
                            ->same('password')
                            ->live(),
                        Checkbox::make('logout_others')
                            ->label(__('Logout dari perangkat lain'))
                            ->helperText(__('Pilih ini jika orang lain menggunakan akun Anda.')),
                    ]),
            ])
            ->statePath('passwordData');
    }

    public function updatePassword(): void
    {
        $this->passwordData = $this->form->getState();
        $this->validate([
            'passwordData.current_password' => ['required', 'string'],
            'passwordData.password' => PasswordPolicy::rules(),
            'passwordData.password_confirmation' => ['required', 'same:passwordData.password'],
        ], [
            'passwordData.password.regex' => __('Kata sandi harus mengandung huruf besar, huruf kecil, angka, dan simbol.'),
            'passwordData.password_confirmation.same' => __('Konfirmasi kata sandi tidak cocok.'),
        ]);

        $user = Auth::user();
        if (! $user || ! Hash::check($this->passwordData['current_password'], $user->password)) {
            $this->addError('passwordData.current_password', __('Kata sandi saat ini tidak sesuai.'));

            return;
        }

        $user->password = Hash::make($this->passwordData['password']);
        $user->save();

        if (! empty($this->passwordData['logout_others']) && config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->where('id', '!=', request()->session()->getId())
                ->delete();
        }

        $this->passwordData = ['logout_others' => false];
        Notification::make()->title(__('Kata sandi berhasil diubah.'))->success()->send();
    }

    /* ---------------------------------------------------------------------
     | Ganti email (OTP)
     | ------------------------------------------------------------------ */

    public function changeEmailForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('Ubah Email'))
                    ->description(__('Masukkan alamat email baru. Kami akan mengirim kode OTP untuk memastikan email tersebut milik Anda.'))
                    ->schema([
                        TextInput::make('current_email')
                            ->label(__('Email Saat Ini'))
                            ->email()
                            ->readOnly()
                            ->dehydrated(false)
                            ->helperText(__('Email yang saat ini terhubung ke akun Anda.')),
                        TextInput::make('email')
                            ->label(__('Email Baru'))
                            ->email()
                            ->required()
                            ->autocomplete('email')
                            ->disabled(fn (): bool => $this->emailOtpSent),
                        TextInput::make('email_confirmation')
                            ->label(__('Konfirmasi Email Baru'))
                            ->email()
                            ->required()
                            ->autocomplete('email')
                            ->same('email')
                            ->disabled(fn (): bool => $this->emailOtpSent)
                            ->helperText(__('Masukkan kembali email baru Anda untuk memastikan tidak ada salah ketik.')),
                        TextInput::make('otp')
                            ->label(__('Kode OTP'))
                            ->numeric()
                            ->length(6)
                            ->required()
                            ->visible(fn (): bool => $this->emailOtpSent)
                            ->helperText(__('Masukkan 6 digit kode yang dikirim ke email baru Anda.')),
                    ]),
            ])
            ->statePath('emailData');
    }

    public function sendEmailChangeOtp(): void
    {
        $this->emailData = $this->changeEmailForm->getState();
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $this->validate([
            'emailData.email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'emailData.email_confirmation' => ['required', 'same:emailData.email'],
        ]);

        $otp = (string) random_int(100000, 999999);
        Cache::put('change_email_otp_'.$user->id, $otp, now()->addMinutes(10));
        Cache::put('change_email_pending_'.$user->id, $this->emailData['email'], now()->addMinutes(10));

        try {
            Mail::send('User.emails.otp.otp', [
                'title' => __('Verifikasi Email Baru'),
                'description' => __('Gunakan kode berikut untuk mengonfirmasi perubahan alamat email. Kode berlaku selama 10 menit.'),
                'otp' => $otp,
            ], function ($message): void {
                $message->to($this->emailData['email'])->subject(__('Kode OTP Perubahan Email'));
            });
        } catch (\Throwable $exception) {
            Cache::forget('change_email_otp_'.$user->id);
            Cache::forget('change_email_pending_'.$user->id);
            Notification::make()->title(__('Kode OTP gagal dikirim.'))->body(__('Periksa konfigurasi email lalu coba lagi.'))->danger()->send();

            return;
        }

        $this->emailOtpSent = true;
        Notification::make()->title(__('Kode OTP telah dikirim ke email baru.'))->success()->send();
    }

    public function verifyEmailChangeOtp(): void
    {
        $this->emailData = $this->changeEmailForm->getState();
        $this->validate(['emailData.otp' => ['required', 'digits:6']]);
        $user = Auth::user();
        $otp = Cache::get('change_email_otp_'.$user?->id);
        $pendingEmail = Cache::get('change_email_pending_'.$user?->id);

        if (! $user || ! $otp || ! $pendingEmail || ! hash_equals((string) $otp, (string) $this->emailData['otp'])) {
            $this->addError('emailData.otp', __('Kode OTP tidak valid atau telah kadaluarsa.'));

            return;
        }

        $user->update(['email' => $pendingEmail, 'email_verified_at' => now()]);
        SecurityEmail::create([
            'user_id' => $user->id,
            'type' => 'email_changed',
            'subject' => __('Email akun berhasil diubah'),
            'body' => __('Alamat email akun telah diperbarui dan diverifikasi.'),
            'sent_at' => now(),
        ]);
        Cache::forget('change_email_otp_'.$user->id);
        Cache::forget('change_email_pending_'.$user->id);
        $this->emailOtpSent = false;
        $this->emailData = [];
        $this->changeEmailForm->fill([
            'current_email' => $user->email,
        ]);
        Notification::make()->title(__('Email berhasil diubah dan diverifikasi.'))->success()->send();
    }

    /* ---------------------------------------------------------------------
     | 2FA
     | ------------------------------------------------------------------ */

    public function toggleTwoFactor(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        if (! $user->two_factor_enabled && blank($user->whatsapp)) {
            Notification::make()->title(__('Nomor WhatsApp diperlukan'))->body(__('Tambahkan nomor WhatsApp yang terverifikasi pada profil terlebih dahulu.'))->warning()->send();

            return;
        }

        $user->two_factor_enabled = ! (bool) $user->two_factor_enabled;
        $user->save();
        if (! $user->two_factor_enabled) {
            BackupCode::query()->where('user_id', $user->id)->delete();
        }
        Notification::make()->title($user->two_factor_enabled ? __('Autentikasi dua faktor diaktifkan.') : __('Autentikasi dua faktor dinonaktifkan.'))->success()->send();
    }

    public function toggleRequestSignIn(): void
    {
        $this->requestSignInEnabled = ! $this->requestSignInEnabled;
        session(['security_request_sign_in_'.Auth::id() => $this->requestSignInEnabled]);
    }

    public function generateBackupCodes(): void
    {
        $user = Auth::user();
        if (! $user || ! $user->two_factor_enabled) {
            return;
        }
        BackupCode::query()->where('user_id', $user->id)->delete();
        $codes = collect(range(1, 10))->map(function () use ($user) {
            $code = strtoupper(Str::random(4).'-'.Str::random(4));
            BackupCode::create(['user_id' => $user->id, 'code' => $code]);

            return $code;
        })->all();
        session(['security_backup_codes' => $codes]);
        Notification::make()->title(__('Kode cadangan berhasil dibuat.'))->success()->send();
    }

    /* ---------------------------------------------------------------------
     | Sign In tersimpan & perangkat terpercaya
     | ------------------------------------------------------------------ */

    public function toggleSavedLogin(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }
        $user->saved_login_enabled = ! (bool) ($user->saved_login_enabled ?? true);
        $user->save();
        Notification::make()->title(__('Pengaturan info Sign In diperbarui.'))->success()->send();
    }

    public function removeTrustedDevice(int $deviceId): void
    {
        TrustedDevice::query()->where('user_id', Auth::id())->whereKey($deviceId)->delete();
        Notification::make()->title(__('Perangkat tepercaya dihapus.'))->success()->send();
    }

    public function removeAllTrustedDevices(): void
    {
        TrustedDevice::query()->where('user_id', Auth::id())->delete();
        Notification::make()->title(__('Semua perangkat tepercaya dihapus.'))->success()->send();
    }

    /* ---------------------------------------------------------------------
     | Sesi perangkat
     | ------------------------------------------------------------------ */

    public function logoutSession(string $sessionId): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }
        DB::table(config('session.table', 'sessions'))->where('user_id', Auth::id())->where('id', $sessionId)->delete();
        Notification::make()->title(__('Sesi perangkat telah dikeluarkan.'))->success()->send();
    }

    public function logoutAllOtherSessions(): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }
        DB::table(config('session.table', 'sessions'))->where('user_id', Auth::id())->where('id', '!=', request()->session()->getId())->delete();
        Notification::make()->title(__('Semua sesi perangkat lain telah dikeluarkan.'))->success()->send();
    }

    public function startCheckup(): void
    {
        $this->checkupCompleted = true;
        Notification::make()->title(__('Pemeriksaan selesai.'))->success()->send();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = Auth::user();
        $devices = $user ? TrustedDevice::query()->where('user_id', $user->id)->latest('trusted_at')->get() : collect();
        $backupCodes = $user ? BackupCode::query()->where('user_id', $user->id)->where('used', false)->get() : collect();
        $emails = $user ? SecurityEmail::query()->where('user_id', $user->id)->where('sent_at', '>=', now()->subDays(30))->latest('sent_at')->get() : collect();
        $sessions = collect();
        if ($user && config('session.driver') === 'database') {
            $sessions = DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->latest('last_activity')->get();
        }

        return compact('user', 'devices', 'backupCodes', 'emails', 'sessions');
    }
}