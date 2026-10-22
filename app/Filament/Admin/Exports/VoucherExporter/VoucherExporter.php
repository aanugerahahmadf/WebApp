<?php

namespace App\Filament\Admin\Exports\VoucherExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\Voucher\Voucher`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class VoucherExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\Voucher\Voucher::class;
}
