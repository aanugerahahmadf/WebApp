<?php

namespace App\Filament\Admin\Exports\PermissionExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `Spatie\Permission\Models\Permission`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class PermissionExporter extends BaseExporter
{
    protected static ?string $model = \Spatie\Permission\Models\Permission::class;
}
