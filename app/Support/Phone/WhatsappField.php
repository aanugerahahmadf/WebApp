<?php

namespace App\Support\Phone;

use App\Models\WhatsappOtp\WhatsappOtp;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Satu-satunya definisi field "Nomor WhatsApp" + alur OTP-nya.
 *
 * Sebelumnya blok ini diduplikasi di empat tempat (SignUp, CompleteProfileComponent,
 * PersonalInfoComponent, PersonalInfoComponentSuperAdmin)
 * dan perlahanPokemonjacinan: versi SuperAdmin masih memakai prefix '+62' statis
 * dan menyimpan nomor sebagai '+62 812...' (pakai spasi), sehingga tidak cocok
 * dengan E.164 yang dipakai tiga tempat lain -- nomor itu jadi gagal dicocokkan
 * saat verify OTP di panel mana pun. Semua pemanggil kini memakai kelas ini,
 * jadi bentuk field dan format tersimpan tidak bisa berbeda lagi.
 */
final class WhatsappField
{
    /**
     * Group berisi Select kode negara (dengan bendera) + input nomor.
     *
     * @param  bool  $required  nomor wajib diisi (Complete Profile)
     * @param  bool  $withOtpButton  tampilkan tombol "Kirim Kode OTP" dan
     *                            pastikan nomor tersimpan sebelum OTP dikirim
     */
    public static function group(bool $required = false, bool $withOtpButton = false): Group
    {
        $number = TextInput::make('whatsapp')
            ->label(__('Nomor WhatsApp'))
            ->tel()
            ->required($required)
            ->prefix(fn (Get $get): string => explode('|', $get('whatsapp_country_code') ?: CountryCallingCodeOptions::defaultSelection())[0])
            ->placeholder(__('81234567890'))
            ->dehydrateStateUsing(fn ($state, Get $get): string => CountryCallingCodeOptions::toE164($get('whatsapp_country_code'), $state))
            ->columnSpanFull();

        if ($withOtpButton) {
            $number
                ->live(onBlur: true)
                ->suffixActions([
                    Action::make('sendOtp')
                        ->label(__('Kirim Kode OTP'))
                        ->icon('heroicon-o-paper-airplane')
                        ->action('sendOtp'),
                ]);
        }

        return Group::make([
            Select::make('whatsapp_country_code')
                ->label(__('Negara / Kode Negara'))
                ->options(CountryCallingCodeOptions::all())
                ->default(CountryCallingCodeOptions::defaultSelection())
                ->searchable()
                ->native(false)
                // Label opsi berisi HTML (bendera emoji) -- tanpa ini Filament
                // akan meng-escape-nya dan flag tampil sebagai teks mentah.
                ->allowHtml()
                ->live()
                ->dehydrated(false)
                ->columnSpanFull(),
            $number,
        ])->columns(1)->columnSpanFull();
    }

    /**
     * Nilai form untuk nomor yang sudah tersimpan (E.164).
     *
     * @return array{whatsapp_country_code: string, whatsapp: string}
     */
    public static function state(?string $e164): array
    {
        $split = CountryCallingCodeOptions::split($e164);

        return [
            'whatsapp_country_code' => $split['selection'],
            'whatsapp' => $split['national'],
        ];
    }

    /**
     * Nomor dari nilai form apa adanya.
     */
    public static function e164(?string $selection, ?string $nationalNumber): string
    {
        return CountryCallingCodeOptions::toE164($selection, $nationalNumber);
    }

    /**
     * Samakan bentuk nomor jadi E.164 agar dua nomor bisa dibandingkan.
     *
     * Dipakai untuk membandingkan nomor yang sudah tersimpan dengan yang coming
     * dari form. Nomor lama di database bisa '+62 812...' (pakai spasi, versi
     * SuperAdmin yang lama) atau '+62812...', sedangkan form selalu E.164
     * bersih -- tanpa normalisasi ini kode OTP yang valid tetap dianggap
     * "nomor berubah" dan verifikasi ditolak.
     */
    public static function normalize(?string $phone): string
    {
        $phone = trim((string) $phone);

        if ($phone === '') {
            return '';
        }

        $digits = (string) preg_replace('/\D/', '', $phone);

        if (str_starts_with($phone, '+')) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '00')) {
            return '+'.substr($digits, 2);
        }

        if (str_starts_with($digits, '0')) {
            return '+62'.substr($digits, 1);
        }

        return '+'.(str_starts_with($digits, '62') ? $digits : '62'.$digits);
    }

    /**
     * Cocokkan OTP lalu tandai sudah terverifikasi.
     *
     * Dipakai dua-duanya untuk membandingkan nomor lama dengan yang baru: kode
     * hanya wajib ada kalau nomornya benar-benar berubah.
     */
    public static function isOtpValid(string $e164, mixed $code): bool
    {
        if (empty($code) || mb_strlen((string) $code) !== 6) {
            return false;
        }

        $record = WhatsappOtp::where('whatsapp', $e164)
            ->where('otp_code', (string) $code)
            ->where('expires_at', '>', now())
            ->whereNull('verified_at')
            ->first();

        if (! $record) {
            return false;
        }

        $record->update(['verified_at' => now()]);

        return true;
    }

    /**
     * Buat OTP 6 digit lalu kirim lewat Fonnte.
     *
     * Tidak melempar error kalau token Fonnte belum diset -- OTP tetap
     * tercatat di DB supaya alur verifikasi di UI tidak mati diam-diam.
     */
    public static function sendOtp(string $e164, string $context = ''): void
    {
        if (strlen($e164) < 10) {
            return;
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        WhatsappOtp::updateOrCreate(
            ['whatsapp' => $e164],
            [
                'otp_code' => $otp,
                'expires_at' => now()->addMinutes(5),
                'verified_at' => null,
            ]
        );

        self::dispatchOtp($e164, $otp, $context);
    }

    private static function dispatchOtp(string $e164, string $otp, string $context): void
    {
        try {
            $token = config('services.fonnte_token', env('FONNTE_TOKEN', ''));

            if (empty($token)) {
                Log::warning('[WhatsappField] WhatsApp OTP skipped — FONNTE_TOKEN not set'.($context !== '' ? " ({$context})" : ''));

                return;
            }

            Http::withHeaders(['Authorization' => $token])
                ->timeout(10)
                ->post('https://api.fonnte.com/send', [
                    'target' => $e164,
                    'message' => __('whatsapp.otp_message', ['otp' => $otp]),
                ]);
        } catch (\Throwable $e) {
            Log::warning('[WhatsappField] WhatsApp OTP exception: '.$e->getMessage());
        }
    }
}