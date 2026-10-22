<?php

namespace App\Filament\Admin\Exports\RoleExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `Spatie\Permission\Models\Role`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class RoleExporter extends BaseExporter
{
    protected static ?string $model = \Spatie\Permission\Models\Role::class;
}
