<?php

namespace App\Filament\Admin\Exports\PaymentMethodExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\PaymentMethod\PaymentMethod`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class PaymentMethodExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\PaymentMethod\PaymentMethod::class;
}
