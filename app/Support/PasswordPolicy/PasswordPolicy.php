<?php

namespace App\Support\PasswordPolicy;

/**
 * PasswordPolicy — satu-satunya tempat yang mendefinisikan kekuatan kata sandi
 * yang sah di seluruh aplikasi.
 *
 * MASALAH YANG DISELESAIKAN
 * -------------------------
 * Aturan password dulu tersebar di 17 titik dan tidak konsisten satu pun:
 *
 *     app/Filament/User/Auth/SignUp.php            min:12   (komposisi dicek)
 *     app/Filament/Admin/Resources/UserResource    min:12   (komposisi dicek)
 *     app/Http/.../User/AuthController             min:12   (komposisi TIDAK)
 *     app/Http/.../User/ProfileController          min:12   (komposisi TIDAK)
 *     app/Http/.../Admin/UserController            min:8    (komposisi TIDAK)
 *     app/Filament/User/.../PasswordSecurityPage   min:8    (komposisi dicek)
 *     app/Filament/Welcome/.../PasswordSecurityPage min:8   (komposisi dicek)
 *     app/Console/Commands/AppInstall             min 8    (komposisi TIDAK)
 *     app/Console/Commands/InitAdmin              (tidak ada validasi sama sekali)
 *     app/Livewire/.../EditPasswordComponent        (tidak ada aturan min sama sekali)
 *     app/Filament/.../OtpResetPassword             (default Filament, bisa 8)
 *
 * Akibatnya satu pengguna bisa mendaftar dengan "@Superadmin123" di satu form,
 * tapi admin bisa membuat akun dengan "password123" lewat form lain, dan
 * pengguna bisa mengganti kata sandinya tanpa batas panjang sama sekali --
 * semuanya di aplikasi yang sama.
 *
 * ATURAN SEKARANG
 * ---------------
 * Semua titik yang menerima kata sandi baru wajib lewat kelas ini, lewat
 *   - rules()          untuk validator Laravel
 *   - filamentRules()  untuk komponen Filament
 *   - violations()     untuk validasi manual (command, factory, seeder)
 *
 * Kriteria (semuanya wajib terpenuhi):
 *   - minimal 12 karakter
 *   - minimal satu huruf besar  (A-Z)
 *   - minimal satu huruf kecil  (a-z)
 *   - minimal satu angka       (0-9)
 *   - minimal satu simbol      (selain huruf dan angka)
 *
 * Contoh yang memenuhi: @Superadmin123, K4t4Sand1!2024, Raja#Mawar99
 *
 * Client Flutter punya salinan aturan yang sama di
 * mobile_app/lib/core/utils/validators/validators.dart (Validators.password).
 * Kalau salah satu diubah, ubah yang satunya juga.
 */
class PasswordPolicy
{
    /**
     * Panjang minimum kata sandi.
     */
    public const MIN_LENGTH = 12;

    /**
     * Aturan Laravel untuk field "password" (tanpa "confirmed").
     *
     * Dipakai di validator: 'password' => PasswordPolicy::rules()
     */
    public const RULES = [
        'required',
        'string',
        'min:'.self::MIN_LENGTH,
        'regex:/[A-Z]/',
        'regex:/[a-z]/',
        'regex:/[0-9]/',
        'regex:/[^A-Za-z0-9]/',
    ];

    /**
     * Aturan yang sama plus konfirmasi, untuk field berpasangan.
     *
     * Dipakai di validator: 'password' => PasswordPolicy::confirmedRules()
     */
    public const CONFIRMED_RULES = [
        'required',
        'string',
        'min:'.self::MIN_LENGTH,
        'regex:/[A-Z]/',
        'regex:/[a-z]/',
        'regex:/[0-9]/',
        'regex:/[^A-Za-z0-9]/',
        'confirmed',
    ];

    /**
     * Aturan tanpa "required", untuk field opsional (patch sebagian).
     *
     * Dipakai di validator:
     *     'password' => ['sometimes', ...PasswordPolicy::optionalRules()]
     *
     * Catatan soal "required": di Laravel, `sometimes` membuat seluruh aturan
     * field dilewati ketika field-nya tidak ada di payload -- jadi
     * `['sometimes', 'required']` TIDAK membuat field opsional jadi wajib
     * (field absen tetap lolos). Karena itu `required` di sini tidak berguna
     * dan hanya membingungkan: hapus saja.
     *
     * Yang dikejar oleh variabel ini: "kalau password dikirim, jangan sampai
     * lemah" -- tanpa ikut menentukan apakah field itu wajib atau tidak. Itu
     * keputusan bentuk form, bukan kebijakan kekuatan kata sandi.
     *
     * @var array<int, string>
     */
    public const OPTIONAL_RULES = [
        'string',
        'min:'.self::MIN_LENGTH,
        'regex:/[A-Z]/',
        'regex:/[a-z]/',
        'regex:/[0-9]/',
        'regex:/[^A-Za-z0-9]/',
    ];

    /**
     * Aturan Filament. Formatnya berbeda: Filament menerima objek Rule yang
     * dirangkai dengan |, bukan array aturan string.
     */
    public static function filamentRules(bool $confirmed = false): \Illuminate\Validation\Rules\Password
    {
        $rules = \Illuminate\Validation\Rules\Password::min(self::MIN_LENGTH)
            ->mixedCase()
            ->numbers()
            ->symbols();

        return $confirmed ? $rules->confirmed() : $rules;
    }

    /**
     * Aturan Laravel sebagai array.
     *
     * @return array<int, string>
     */
    public static function rules(bool $confirmed = false): array
    {
        return $confirmed ? self::CONFIRMED_RULES : self::RULES;
    }

    /**
     * Aturan Laravel untuk field opsional, tanpa "required".
     *
     * @return array<int, string>
     */
    public static function optionalRules(): array
    {
        return self::OPTIONAL_RULES;
    }

    /**
     * Aturan dengan konfirmasi, dalam bentuk array.
     *
     * Setara dengan rules(true), tapi dipanggil eksplisit di tempat yang
     * memang butuh konfirmasi supaya maksudnya terbaca.
     *
     * @return array<int, string>
     */
    public static function confirmedRules(): array
    {
        return self::CONFIRMED_RULES;
    }

    /**
     * Daftar pelanggaran kebijakan, kosong bila kata sandi sah.
     *
     * Dipakai oleh command dan seeder yang tidak lewat validator HTTP.
     *
     * @return array<int, string>
     */
    public static function violations(?string $value): array
    {
        if ($value === null || $value === '') {
            return ['Kata sandi wajib diisi.'];
        }

        $problems = [];

        if (mb_strlen($value) < self::MIN_LENGTH) {
            $problems[] = 'Kata sandi minimal '.self::MIN_LENGTH.' karakter.';
        }

        if (! preg_match('/[A-Z]/', $value)) {
            $problems[] = 'Kata sandi harus mengandung huruf besar.';
        }

        if (! preg_match('/[a-z]/', $value)) {
            $problems[] = 'Kata sandi harus mengandung huruf kecil.';
        }

        if (! preg_match('/[0-9]/', $value)) {
            $problems[] = 'Kata sandi harus mengandung angka.';
        }

        if (! preg_match('/[^A-Za-z0-9]/', $value)) {
            $problems[] = 'Kata sandi harus mengandung simbol.';
        }

        return $problems;
    }

    /**
     * True bila kata sandi memenuhi seluruh kebijakan.
     */
    public static function isValid(?string $value): bool
    {
        return self::violations($value) === [];
    }
}
