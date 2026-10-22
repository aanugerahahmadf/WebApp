<?php

namespace App\Filament\Admin\Exports\ReportExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\Report\Report`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class ReportExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\Report\Report::class;
}
