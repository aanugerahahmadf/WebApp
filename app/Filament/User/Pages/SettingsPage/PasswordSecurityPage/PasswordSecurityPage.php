<?php

namespace App\Filament\User\Pages\SettingsPage\PasswordSecurityPage;

use App\Models\BackupCode\BackupCode;
use App\Models\SecurityEmail\SecurityEmail;
use App\Models\TrustedDevice\TrustedDevice;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;

class PasswordSecurityPage extends Page
{
    protected static string $view = 'User.pages.settings-page.password-security-page.password-security-page';

    protected static bool $shouldRegisterNavigation = false;

    #[Url(as: 'section')]
    public string $section = 'index';

    public array $passwordData = ['logout_others' => false];

    public array $emailData = [];

    public bool $emailOtpSent = false;

    public bool $requestSignInEnabled = false;

    public ?string $authenticatorSecret = null;

    public string $authenticatorCode = '';

    public bool $checkupCompleted = false;

    public function mount(): void
    {
        $this->section = in_array($this->section, $this->sections(), true) ? $this->section : 'index';
        $this->requestSignInEnabled = (bool) session('security_request_sign_in_'.Auth::id(), false);
        $this->form->fill(['logout_others' => false]);
        $this->emailOtpSent = Cache::has('change_email_otp_'.Auth::id());
        $this->changeEmailForm->fill(['email' => Cache::get('change_email_pending_'.Auth::id())]);
    }

    public static function getSlug(): string
    {
        return 'settings/password-security';
    }

    public function getTitle(): string
    {
        return __('Kata Sandi dan Keamanan');
    }

    /** The Blade view supplies a compact header with its own Back button. */
    public function hasHeader(): bool
    {
        return false;
    }

    public function goTo(string $section): void
    {
        if (! in_array($section, $this->sections(), true)) {
            return;
        }

        $this->redirect(static::getUrl(['section' => $section], panel: 'user'), navigate: true);
    }

    /** Native Filament form: it inherits all panel input, label, validation,
     * dark-mode, and responsive spacing styles instead of custom HTML fields. */
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

    /**
     * Register both native Filament forms explicitly. Without this, Filament
     * treats `changeEmailForm` as an infolist when the page is mounted.
     */
    protected function getForms(): array
    {
        return [
            'form',
            'changeEmailForm',
        ];
    }

    public function changeEmailForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('Ubah Email'))
                    ->description(__('Masukkan alamat email baru. Kami akan mengirim kode OTP untuk memastikan email tersebut milik Anda.'))
                    ->schema([
                        TextInput::make('email')
                            ->label(__('Email Baru'))
                            ->email()
                            ->required()
                            ->autocomplete('email')
                            ->disabled(fn (): bool => $this->emailOtpSent),
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
        if (! $user) return;

        $this->validate([
            'emailData.email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
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
        $this->changeEmailForm->fill();
        Notification::make()->title(__('Email berhasil diubah dan diverifikasi.'))->success()->send();
    }

    public function updatePassword(): void
    {
        $this->passwordData = $this->form->getState();
        $this->validate([
            'passwordData.current_password' => ['required', 'string'],
            'passwordData.password' => ['required', 'string', 'min:8', 'regex:/[A-Z]/', 'regex:/[a-z]/', 'regex:/[0-9]/', 'regex:/[^A-Za-z0-9]/'],
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

    public function toggleTwoFactor(): void
    {
        $user = Auth::user();
        if (! $user) return;

        if (! $user->two_factor_enabled && blank($user->whatsapp)) {
            Notification::make()->title(__('Nomor WhatsApp diperlukan'))->body(__('Tambahkan nomor WhatsApp yang terverifikasi pada profil terlebih dahulu.'))->warning()->send();
            return;
        }

        $user->two_factor_enabled = ! (bool) $user->two_factor_enabled;
        $user->save();
        if (! $user->two_factor_enabled) BackupCode::query()->where('user_id', $user->id)->delete();
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
        if (! $user || ! $user->two_factor_enabled) return;
        BackupCode::query()->where('user_id', $user->id)->delete();
        $codes = collect(range(1, 10))->map(function () use ($user) {
            $code = strtoupper(Str::random(4).'-'.Str::random(4));
            BackupCode::create(['user_id' => $user->id, 'code' => $code]);
            return $code;
        })->all();
        session(['security_backup_codes' => $codes]);
        Notification::make()->title(__('Kode cadangan berhasil dibuat.'))->success()->send();
    }

    public function prepareAuthenticator(): void
    {
        $this->authenticatorSecret = strtoupper(Str::random(4).'-'.Str::random(4).'-'.Str::random(4).'-'.Str::random(4));
    }

    public function verifyAuthenticator(): void
    {
        $this->validate(['authenticatorCode' => ['required', 'digits:6']]);
        $user = Auth::user();
        if (! $user || ! $this->authenticatorSecret) return;
        $user->two_factor_secret = $this->authenticatorSecret;
        $user->two_factor_enabled = true;
        $user->save();
        $this->generateBackupCodes();
        $this->authenticatorCode = '';
        Notification::make()->title(__('Aplikasi autentikasi berhasil diverifikasi.'))->success()->send();
    }

    public function toggleSavedLogin(): void
    {
        $user = Auth::user();
        if (! $user) return;
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

    public function logoutSession(string $sessionId): void
    {
        if (config('session.driver') !== 'database') return;
        DB::table(config('session.table', 'sessions'))->where('user_id', Auth::id())->where('id', $sessionId)->delete();
        Notification::make()->title(__('Sesi perangkat telah dikeluarkan.'))->success()->send();
    }

    public function logoutAllOtherSessions(): void
    {
        if (config('session.driver') !== 'database') return;
        DB::table(config('session.table', 'sessions'))->where('user_id', Auth::id())->where('id', '!=', request()->session()->getId())->delete();
        Notification::make()->title(__('Semua sesi perangkat lain telah dikeluarkan.'))->success()->send();
    }

    public function startCheckup(): void
    {
        $this->checkupCompleted = true;
        Notification::make()->title(__('Pemeriksaan selesai.'))->success()->send();
    }

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

    private function sections(): array
    {
        return ['index', 'change-password', 'two-factor', 'saved-login', 'sign-in-activity', 'recent-emails', 'checkup'];
    }
}
