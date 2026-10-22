<?php

namespace App\Filament\Admin\Exports\SecurityEmailExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\SecurityEmail\SecurityEmail`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class SecurityEmailExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\SecurityEmail\SecurityEmail::class;
}
