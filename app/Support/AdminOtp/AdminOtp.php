<?php

namespace App\Support\AdminOtp;

use App\Models\Admin\Admin;
use Illuminate\Support\Str;

/**
 * Kunci cache & pencarian akun untuk flow "Lupa Kata Sandi?" panel admin.
 *
 * Kenapa kelas ini perlu, padahal cuma dua baris nama cache:
 *
 *   Tiga kelas berurutan memakai kunci yang sama -- `OtpRequestPasswordReset`
 *   menaruh OTP-nya, `VerifyOtp` mencocokkannya, `OtpResetPassword`
 *   membersihkannya. Dulu kuncinya `password_reset_otp_<email>` dan
 *   `otp_verified_for_<email>` -- PERSIS sama dengan yang dipakai flow panel
 *   user.
 *
 *   Itu bukan sekadar tidak rapi. Begitu tabel `admins` dipisah dari
 *   `users`, kunci yang sama berarti satu email di dua tabel berbeda berbagi
 *   satu OTP. Kalau suatu saat seorang admin punya identitas belanja dengan
 *   email yang sama, OTP yang diminta lewat panel USER akan diterima panel
 *   ADMIN -- dan `OtpResetPassword` akan menulis kata sandi baru ke `admins`.
 *   Itu jalur eskalasi dari akun belanja ke akun admin, dan gejalanya tidak
 *   akan pernah muncul sebagai error.
 *
 *   Kuncinya di-scope per audience di sini, di satu tempat, supaya tiga file
 *   itu tidak bisa kembar berbeda lagi.
 */
class AdminOtp
{
    /**
     * Kunci OTP aktif untuk satu email admin.
     */
    public static function otpKey(string $email): string
    {
        return 'admin_password_reset_otp_'.Str::lower($email);
    }

    /**
     * Kunci penanda "OTP ini sudah diverifikasi untuk email ini".
     */
    public static function verifiedKey(string $email): string
    {
        return 'admin_otp_verified_for_'.Str::lower($email);
    }

    /**
     * Admin yang email-nya cocok, atau null.
     *
     * Sengaja `Admin`, bukan `User`. Dulu kelas-kelas ini menanyakan
     * `User::where('email', ...)`, yang setelah pemisahan tabel selalu null --
     * jadi "Lupa Kata Sandi?" di panel admin tidak pernah mengirim kode sama
     * sekali, tanpa error apa pun karena memang tidak ada yang salah.
     */
    public static function findAdmin(string $email): ?Admin
    {
        return Admin::where('email', $email)->first();
    }
}