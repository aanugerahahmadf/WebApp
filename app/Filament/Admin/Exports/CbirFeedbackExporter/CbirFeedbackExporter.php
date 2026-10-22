<?php

namespace App\Filament\Admin\Exports\CbirFeedbackExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\Cbir\CbirFeedback`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class CbirFeedbackExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\Cbir\CbirFeedback::class;
}
