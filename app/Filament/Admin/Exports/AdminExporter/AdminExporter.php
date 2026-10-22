<?php

namespace App\Filament\Admin\Exports\AdminExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\Admin\Admin`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class AdminExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\Admin\Admin::class;
}
