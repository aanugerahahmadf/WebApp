<?php

namespace App\Filament\Admin\Exports\UserAddressExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\User\UserAddress`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class UserAddressExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\User\UserAddress::class;
}
