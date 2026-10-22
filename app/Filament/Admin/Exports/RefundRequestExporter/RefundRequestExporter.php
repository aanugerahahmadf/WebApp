<?php

namespace App\Filament\Admin\Exports\RefundRequestExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\Order\RefundRequest`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class RefundRequestExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\Order\RefundRequest::class;
}
