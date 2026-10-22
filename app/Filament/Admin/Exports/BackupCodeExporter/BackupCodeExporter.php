<?php

namespace App\Filament\Admin\Exports\BackupCodeExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\BackupCode\BackupCode`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class BackupCodeExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\BackupCode\BackupCode::class;
}
