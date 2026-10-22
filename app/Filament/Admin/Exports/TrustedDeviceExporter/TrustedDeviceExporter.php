<?php

namespace App\Filament\Admin\Exports\TrustedDeviceExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\TrustedDevice\TrustedDevice`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class TrustedDeviceExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\TrustedDevice\TrustedDevice::class;
}
