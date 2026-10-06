<?php

namespace App\Filament\Shared\Pages\TwoFactorAuthenticatorPage;

use App\Filament\Shared\Concerns\HandlesTwoFactorAuthenticator\HandlesTwoFactorAuthenticator;
use Filament\Forms\Form;
use MixCode\FilamentMulti2fa\Pages\TwoFactorySetup as PackageTwoFactorySetup;

/**
 * Dasar untuk dua halaman setup 2FA.
 *
 *   Auth\TwoFactorAuth                 -- "strengthen your account?" di alur login
 *   …\TwoFactorySetup                  -- dari Pengaturan
 *
 * Keduanya menjalankan alur yang sama persis dari paket
 * mix-code/filament-multi-2fa: pilih tipe (Email / Authenticator App / None),
 * verifikasi OTP atau TOTP, simpan, plus daftar perangkat tepercaya. Semua itu
 * warisan kelas paket di bawah dan tidak dicopy.
 *
 * Yang dibedakan hanya tiga hal, masing-masing di kelas turunannya:
 * URL, breadcrumb, dan tujuan setelah selesai.
 *
 * Kelas dasar ini ada karena tanpa itu kedua halaman akan mengulang hal yang
 * sama -- dan saat satu diubah, yang lain tidak ikut. Ketika itu terjadi
 * QR code muncul di satu halaman dan tidak di halaman lain.
 */
abstract class TwoFactorAuthenticatorPage extends PackageTwoFactorySetup
{
    use HandlesTwoFactorAuthenticator;

    /**
     * View milik paket, disalin ke resources/views/User/vendor/ karena paket
     * tidak mempublish view-nya. Salinan itu memakai page biasa, bukan
     * page.simple, supaya Top Navigation ikut tampil.
     */
    protected static string $view = 'User.vendor.filament-multi-2fa.pages.two-factory-setup';

    /**
     * Paket menetapkan layout.simple, yang tidak punya Top Navigation maupun
     * breadcrumb -- tampilannya polos dengan tombol bahasa saja di kanan atas.
     */
    protected static string $layout = 'filament-panels::components.layout.index';

    /**
     * Form verifikasi TOTP milik paket, tanpa placeholder QR.
     *
     * QR-nya pindah ke infolist (HandlesTwoFactorAuthenticator) supaya bisa
     * duduk berdampingan dengan Kode Manual, dan supaya tampilannya sama
     * dengan halaman saudaranya.
     *
     * Schema-nya sendiri tetap milik paket: menyalinnya berarti ikut
     * menggandakan aturan verifikasi Google2FA dan daftar perangkat, yang
     * harusnya tetap satu sumber kebenaran.
     */
    public function verifyTOTPForm(Form $form): Form
    {
        $form = parent::verifyTOTPForm($form);

        $schema = $form->getComponents(withHidden: true);

        // Komponen pertama milik paket adalah QR code; buang supaya tidak
        // tampil dua kali.
        foreach ($schema as $index => $component) {
            if (method_exists($component, 'getName') && $component->getName() === 'qr_code') {
                unset($schema[$index]);

                break;
            }
        }

        return $form->components(array_values($schema));
    }
}