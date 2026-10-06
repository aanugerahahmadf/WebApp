<?php

namespace App\Filament\User\Auth\OtpEmailOrTwoFactory;

use App\Filament\User\Pages\Home\Home;
use App\Models\BackupCode\BackupCode;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use MixCode\FilamentMulti2fa\Enums\TwoFactorAuthType;
use MixCode\FilamentMulti2fa\Pages\OTPVerify;
use PragmaRX\Google2FA\Google2FA;

/**
 * Satu halaman untuk ketiga cara membuktikan identitas setelah Sign In.
 *
 *   Sign In --> OtpEmailOrTwoFactory --> Home
 *
 * Dipakai kalau 2FA sudah aktif. Bedanya dengan OTPVerify bawaan paket:
 *
 *   1. Tiga cara dalam satu halaman, dipilih lewat "Opsi Lainnya":
 *        email          -- kode 6 digit yang dikirim ke email
 *        authenticator  -- kode 6 digit dari Google Authenticator / Duo
 *        recovery       -- kode pemulihan XXXX-XXXX
 *
 *   2. Tidak ada centang "Ingatkan Perangkat Ini". Centang itu default-nya
 *      MENYALA di paket, dan begitu menyala perangkat dicatat tepercaya
 *      selama 30 hari -- artinya Sign In berikutnya tidak pernah Ask 2FA
 *      lagi. Itu persis yang membuat alur "Sign In -> 2FA -> Home" tidak
 *      terjadi. Di sini 2FA selalu diminta.
 *
 *   3. Setelah berhasil langsung ke Home, bukan "redirectAfterVerifyUrl()"
 *      milik paket.
 *
 * Kenapa turunan OTPVerify dan bukan Page baru:
 *
 *   Middleware CheckTrustedDevice milik paket mengarahkan ke
 *   OTPVerify::getRouteName(), dan menolaknya kalau request sudah berada di
 *   route itu -- kalau tidak, middleware akan mengulang redirect tanpa henti.
 *   Route name diturunkan dari getSlug(), jadi halaman ini override
 *   getSlug() dengan slug yang sama persis. Namanya tidak berubah, link lama
 *   tidak rusak, dan middleware tidak masuk loop.
 *
 * Kenapa OTP email memakai Cache dan bukan generateTwoFactorOTPCode() milik
 * paket:
 *
 *   Method itu menulis two_factor_secret = encrypt(kode acak). Kalau user
 *   memakai Authenticator App, secret itu berisi TOTP-nya -- menimpanya
 *   berarti 2FA yang sudah aktif ikut hancur. Di sini kode email disimpan di
 *   cache dengan key terpisah, jadi tidak menyentuh kolom apa pun.
 */
class OtpEmailOrTwoFactory extends OTPVerify implements HasForms
{
    use InteractsWithForms;

    /** Kode 6 digit dari aplikasi autentikasi (TOTP). */
    public const METHOD_AUTHENTICATOR = 'authenticator';

    /** Kode pemulihan XXXX-XXXX hasil "Buat Ulang Kode". */
    public const METHOD_RECOVERY = 'recovery';

    /** Kode 6 digit yang dikirim ke email. */
    public const METHOD_EMAIL = 'email';

    /** Key cache. Dipisah dari "otp_<id>" milik verifikasi email, jangan ditabrakkan. */
    protected const EMAIL_CACHE_KEY = '2fa_email_otp_';

    /** Berapa lama kode email berlaku (menit). */
    protected const EMAIL_TTL_MINUTES = 10;

    protected static string $view = 'User.auth.otp-email-or-two-factory.otp-email-or-two-factory';

    /** Cara yang sedang dipilih: authenticator | recovery | email. */
    public string $method = self::METHOD_AUTHENTICATOR;

    /** "Opsi Lainnya" sedang terbuka atau tidak. */
    public bool $moreOpen = false;

    /** Detik tersisa sebelum kode email boleh dikirim ulang. */
    public int $resendCooldown = 0;

    /**
     * Data form.
     *
     * Parentonymya punya ['two_factor_type' => ..., 'trust_device' => true].
     * Dua-duanya DIHAPUS di sini:
     *
     *   - trust_device: tidak ada lagi checkboxnya di halaman ini. Kalau
     *     tetap dibawa, flag itu ikut terkirim di snapshot Livewire, dan
     *     tidak ada yang membacanya -- padahal itulah yang membuat 2FA
     *     terlihat dilewati.
     *   - two_factor_type: tidak dipakai; metode yang aktif ada di $method.
     */
    public ?array $data = [
        'otp' => null,
    ];

    public static function getSlug(): string
    {
        // Sama persis dengan OTPVerify. Lihat catatan di docblock kelas.
        return 'o-t-p-verify';
    }

    public function mount(): void
    {
        $this->user = auth()->user();

        if (! $this->user) {
            $this->redirect(\Filament\Facades\Filament::getLoginUrl());

            return;
        }

        $type = $this->user->two_factor_type?->value;

        // 2FA tidak aktif: tidak ada yang perlu diverifikasi.
        if ($type === TwoFactorAuthType::None->value) {
            $this->redirect(Home::getUrl());

            return;
        }

        $this->form->fill();

        // Metode awal mengikuti tipe yang tersimpan: Email -> kode email,
        // selain itu -> aplikasi autentikasi.
        $this->method = $type === TwoFactorAuthType::Email->value
            ? self::METHOD_EMAIL
            : self::METHOD_AUTHENTICATOR;

        if ($this->method === self::METHOD_EMAIL) {
            $this->sendEmailCode();
        }
    }

    /*
     * ---------------------------------------------------------------------
     * Judul & tombol
     * ---------------------------------------------------------------------
     */

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('Autentikasi Dua Faktor');
    }

    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return $this->method === self::METHOD_RECOVERY
            ? __('Pemulihan Dua Faktor')
            : __('Autentikasi Dua Faktor');
    }

    public function getSubheading(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        return match ($this->method) {
            self::METHOD_AUTHENTICATOR => __('Masukkan kode dari aplikasi autentikasi Anda di bawah.'),
            self::METHOD_RECOVERY => __('Jika Anda tidak dapat mengakses perangkat atau menerima kode autentikasi, masukkan salah satu kode pemulihan Anda untuk memverifikasi identitas.'),
            self::METHOD_EMAIL => __('Kami telah mengirim kode 6 digit ke email Anda. Masukkan kode tersebut di bawah.'),
            default => null,
        };
    }

    public function getMaxWidth(): \Filament\Support\Enums\MaxWidth
    {
        return \Filament\Support\Enums\MaxWidth::Medium;
    }

    /*
     * ---------------------------------------------------------------------
     * Form
     * ---------------------------------------------------------------------
     */

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('otp')
                    ->label($this->method === self::METHOD_RECOVERY ? __('Kode Pemulihan') : __('Kode OTP'))
                    ->placeholder($this->method === self::METHOD_RECOVERY ? 'XXXX-XXXX' : 'XXXXXX')
                    ->required()
                    ->autofocus()
                    ->maxLength(9)
                    ->extraInputAttributes([
                        'inputmode' => $this->method === self::METHOD_RECOVERY ? 'text' : 'numeric',
                        'autocomplete' => 'one-time-code',
                        'class' => 'text-center',
                    ])
                    // recovery code "abcd-efgh" -> "ABCD-EFGH": SQLite/MySQL
                    // membandingkan string secara case-sensitive di collation
                    // binner, dan user bisa saja ketik huruf kecil.
                    ->dehydrateStateUsing(fn (?string $state): string => $this->method === self::METHOD_RECOVERY
                        ? strtoupper(trim((string) $state))
                        : trim((string) $state)),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('Verifikasi'))
                ->submit('save')
                ->color('success')
                ->class('w-full'),
        ];
    }

    public function hasFullWidthFormActions(): bool
    {
        return true;
    }

    /*
     * ---------------------------------------------------------------------
     * Aksi
     * ---------------------------------------------------------------------
     */

    /** Ganti cara verifikasi, lalu tutup "Opsi Lainnya". */
    public function useMethod(string $method): void
    {
        if (! in_array($method, [self::METHOD_AUTHENTICATOR, self::METHOD_RECOVERY, self::METHOD_EMAIL], true)) {
            return;
        }

        $this->method = $method;
        $this->moreOpen = false;
        $this->data['otp'] = null;

        // Kode email hanya berlaku untuk method email. Pindah keluar dari
        // email = jangan kirim ulang diam-diam.
        if ($method === self::METHOD_EMAIL) {
            $this->sendEmailCode();
        }
    }

    public function resendEmailCode(): void
    {
        $this->sendEmailCode(force: true);
    }

    /**
     * Keluar dari akun ini -- jalan keluar terakhir kalau perangkat autentikasi
     * hilang dan kode pemulihan juga tidak tersimpan.
     *
     * Session ikut dihapus supaya 2fa_passed dan status "perangkat tepercaya"
     * tidak ikut terbawa ke Sign In berikutnya.
     */
    public function logout(): void
    {
        auth()->logout();

        session()->invalidate();
        session()->regenerateToken();

        $this->redirect(\Filament\Facades\Filament::getLoginUrl());
    }

    /*
     * ---------------------------------------------------------------------
     * Verifikasi
     * ---------------------------------------------------------------------
     */

    public function save(): void
    {
        try {
            $code = (string) ($this->form->getState()['otp'] ?? '');
        } catch (Halt) {
            return;
        }

        $lolos = match ($this->method) {
            self::METHOD_AUTHENTICATOR => $this->checkTotp($code),
            self::METHOD_RECOVERY => $this->checkRecovery($code),
            self::METHOD_EMAIL => $this->checkEmail($code),
            default => false,
        };

        if (! $lolos) {
            Notification::make()
                ->danger()
                ->title(__('Kode salah'))
                ->body(__('Kode yang Anda masukkan tidak cocok atau sudah kadaluarsa.'))
                ->send();

            $this->data['otp'] = null;

            return;
        }

        // Kode email sudah tidak berlaku -- jangan biarkan bisa dipakai ulang.
        Cache::forget(self::EMAIL_CACHE_KEY . $this->user->id);

        // Penanda yang dibaca CheckTrustedDevice lewat isOtpPassed(). Tanpa
        // ini middleware akan mengarahkan balik ke halaman ini terus.
        session(['2fa_passed' => true]);

        if ($this->method === self::METHOD_EMAIL) {
            $this->user->two_factor_confirmed_at = now();
            $this->user->save();
        }

        Notification::make()
            ->success()
            ->title(__('Verifikasi Berhasil'))
            ->send();

        $this->redirect(Home::getUrl());
    }

    /** Kode 6 digit dari aplikasi autentikasi, dicek dengan Google2FA sungguhan. */
    protected function checkTotp(string $code): bool
    {
        $secret = $this->user->two_factor_secret;

        if (! $secret || $code === '') {
            return false;
        }

        return (new Google2FA)->verifyKey($secret, $code);
    }

    /**
     * Kode pemulihan harus SEKALI PAKAI.
     *
     * Dicari dengan lockForUpdate supaya dua request bersamaan tidak memakai
     * kode yang sama dua kali.
     */
    protected function checkRecovery(string $code): bool
    {
        $code = strtoupper(trim($code));

        if ($code === '') {
            return false;
        }

        $backup = BackupCode::query()
            ->where('user_id', $this->user->id)
            ->where('code', $code)
            ->where('used', false)
            ->lockForUpdate()
            ->first();

        if (! $backup) {
            return false;
        }

        $backup->update(['used' => true, 'used_at' => now()]);

        return true;
    }

    protected function checkEmail(string $code): bool
    {
        $disimpan = Cache::get(self::EMAIL_CACHE_KEY . $this->user->id);

        return $disimpan !== null && hash_equals((string) $disimpan, trim($code));
    }

    /*
     * ---------------------------------------------------------------------
     * Kirim kode email
     * ---------------------------------------------------------------------
     */

    protected function sendEmailCode(bool $force = false): void
    {
        $key = self::EMAIL_CACHE_KEY . $this->user->id;

        if (! $force && Cache::has($key)) {
            return;
        }

        $kode = (string) random_int(100000, 999999);

        Cache::put($key, $kode, now()->addMinutes(self::EMAIL_TTL_MINUTES));

        try {
            Mail::send('User.emails.otp.otp', [
                'title' => __('Kode Autentikasi Dua Faktor'),
                'description' => __('Gunakan kode berikut untuk memverifikasi identitas Anda. Kode berlaku selama 10 menit.'),
                'otp' => $kode,
            ], function ($message): void {
                $message->to($this->user->email)->subject(__('Kode Autentikasi Dua Faktor'));
            });
        } catch (\Exception $e) {
            // Mail gagal = user tidak akan pernah menerima kode. Hapus dari
            // cache supaya tombol "Kirim Ulang" bisa dipakai lagi, jangan
            // menampilkan form yang tidak bisa diisi.
            Cache::forget($key);

            Log::error('Gagal kirim OTP 2FA ke ' . $this->user->email . ': ' . $e->getMessage());

            Notification::make()
                ->danger()
                ->title(__('Gagal Mengirim Kode'))
                ->body(__('Kami tidak dapat mengirim kode ke email Anda. Silakan coba lagi.'))
                ->send();
        }
    }

    public function hasEmailMethod(): bool
    {
        return $this->user?->two_factor_type?->value === TwoFactorAuthType::Email->value
            || $this->user?->email !== null;
    }
}