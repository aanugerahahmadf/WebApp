<?php

namespace App\Filament\Admin\Exports\ReviewExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\Review\Review`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class ReviewExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\Review\Review::class;
}
