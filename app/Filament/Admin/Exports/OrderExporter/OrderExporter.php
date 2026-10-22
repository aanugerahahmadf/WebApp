<?php

namespace App\Filament\Admin\Exports\OrderExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\Order\Order`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class OrderExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\Order\Order::class;
}
