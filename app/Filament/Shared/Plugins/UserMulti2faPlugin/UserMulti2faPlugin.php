<?php

namespace App\Filament\Shared\Plugins\UserMulti2faPlugin;

use App\Filament\User\Auth\OtpEmailOrTwoFactory\OtpEmailOrTwoFactory;
use Filament\Panel;
use MixCode\FilamentMulti2fa\FilamentMulti2faPlugin;
use MixCode\FilamentMulti2fa\Middleware\CheckTrustedDevice;

/**
 * Plugin 2FA panel User.
 *
 * Turunan FilamentMulti2faPlugin, tapi mendaftarkan lebih sedikit.
 *
 * Paket aslinya mendaftarkan tiga hal sekaligus:
 *   1. halaman OTPVerify            -- dipakai
 *   2. halaman TwoFactorySetup      -- tidak dipakai
 *   3. item menu "2FA Setup"        -- tidak dipakai
 *   4. middleware CheckTrustedDevice -- dipakai
 *
 * (2) dan (3) tidak dipakai karena 2FA di app ini punya tempat sendiri:
 *   - aktivasi lewat App\Filament\User\Auth\TwoFactorAuth
 *   - dari Pengaturan, section two-factor di halaman Password & Security
 *
 * Dan (3) tidak bisa dibuang dari luar: Panel::userMenuItems() hanya
 * MENAMBAH item, tidak punya API untuk menghapus satu. Satu-satunya cara
 * bersih adalah tidak mendaftarkannya sama sekali -- itulah yang dilakukan
 * kelas ini dengan menimpa register().
 *
 * (1) dan (4) tidak bisa dipisah dari (2)/(3) di paket aslinya karena
 * keduanya berasal dari satu method register(). Maka di sini keduanya
 * didaftarkan ulang secara eksplisit.
 *
 * Side effect yang disengaja: TwoFactorySetup tidak lagi terdaftar, jadi
 * CheckTrustedDevice tidak punya halaman tujuan pada cabang "2FA belum
 * disetup". Cabang itu hanya reachable bila forceSetup2fa() aktif, yang
 * tidak kita pakai -- sehingga tidak praktis terjadi.
 *
 * HARUS extends, bukan implements Plugin: getId() di sini sama dengan
 * milik paket, jadi container menyimpan objek ini. Kode paket memanggil
 * FilamentMulti2faPlugin::get() yang return type-nya class paket -- kalau
 * kelas ini tidakteringunan, return type tidak terpenuhi dan PANEL USER
 * SELURUHNYA 500. boot() juga diwarisi, yang memasang listener Logout
 * untuk menghapus session 2fa_passed; tanpa itu, "sudah lewat 2FA" bisa
 * dipakai ulang di sesi berikutnya.
 */
class UserMulti2faPlugin extends FilamentMulti2faPlugin
{
    public function register(Panel $panel): void
    {
        $panel
            ->pages([
                // Bukan OTPVerify::class milik paket. OtpEmailOrTwoFactory
                // menurunkan OTPVerify dan memakai slug yang sama persis,
                // jadi route name, URL, dan pengecekan routeIs() di
                // CheckTrustedDevice semuanya tidak berubah -- hanya isi
                // halamannya yang diganti.
                //
                // Yang diganti: tiga cara verifikasi dalam satu halaman
                // (kode email / aplikasi autentikasi / kode pemulihan), dan
                // checkbox "Ingatkan Perangkat Ini" yang dihilangkan karena
                // default-nya menyala sehingga Sign In berikutnya tidak pernah
                // Ask 2FA lagi.
                OtpEmailOrTwoFactory::class,
            ])
            ->authMiddleware([
                CheckTrustedDevice::class,
            ]);
    }
}