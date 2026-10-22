<?php

namespace App\Filament\Admin\Exports\OrderPaymentExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\Order\OrderPayment`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class OrderPaymentExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\Order\OrderPayment::class;
}
