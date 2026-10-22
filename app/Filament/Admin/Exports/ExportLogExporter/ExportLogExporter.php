<?php

namespace App\Filament\Admin\Exports\ExportLogExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `Filament\Actions\Exports\Models\Export`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class ExportLogExporter extends BaseExporter
{
    protected static ?string $model = \Filament\Actions\Exports\Models\Export::class;
}
