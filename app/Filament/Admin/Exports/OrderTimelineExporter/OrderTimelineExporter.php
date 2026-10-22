<?php

namespace App\Filament\Admin\Exports\OrderTimelineExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\Order\OrderTimeline`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class OrderTimelineExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\Order\OrderTimeline::class;
}
