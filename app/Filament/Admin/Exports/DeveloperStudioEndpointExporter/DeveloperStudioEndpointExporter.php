<?php

namespace App\Filament\Admin\Exports\DeveloperStudioEndpointExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\DeveloperStudio\DeveloperStudioEndpoint`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class DeveloperStudioEndpointExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\DeveloperStudio\DeveloperStudioEndpoint::class;
}
