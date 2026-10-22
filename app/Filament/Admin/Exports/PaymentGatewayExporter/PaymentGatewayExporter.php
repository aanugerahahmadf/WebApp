<?php

namespace App\Filament\Admin\Exports\PaymentGatewayExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\PaymentGateway\PaymentGateway`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class PaymentGatewayExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\PaymentGateway\PaymentGateway::class;
}
