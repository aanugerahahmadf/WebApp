<?php

namespace App\Filament\Admin\Exports\ActivityLogExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `Spatie\Activitylog\Models\Activity`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class ActivityLogExporter extends BaseExporter
{
    protected static ?string $model = \Spatie\Activitylog\Models\Activity::class;
}
