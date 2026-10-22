<?php

namespace App\Filament\Admin\Exports\DeviceTokenExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\User\DeviceToken`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class DeviceTokenExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\User\DeviceToken::class;
}
