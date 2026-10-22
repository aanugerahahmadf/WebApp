<?php

namespace App\Filament\Admin\Exports\ReviewReportExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\Review\ReviewReport`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class ReviewReportExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\Review\ReviewReport::class;
}
