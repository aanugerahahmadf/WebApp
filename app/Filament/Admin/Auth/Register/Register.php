<?php

namespace App\Filament\Admin\Auth\Register;

use Filament\Pages\Auth\Register as BaseRegister;

/**
 * Pendaftaran admin.
 *
 * TIDAK DIDAFTARKAN di panel: akun admin dibuat dari luar (seed / panel
 * user), jadi `/admin/register` sengaja 404.
 *
 * Class-nya tetap ada supaya tidak hilang begitu suatu saat dibutuhkan --
 * tinggal mendaftarkannya di `AdminPanelProvider`.
 */
class Register extends BaseRegister
{

    public function getHeading(): string
    {
        return __('Daftar Admin');
    }
}
