<?php

namespace App\Filament\Admin\Exports\UserVoucherExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\Voucher\UserVoucher`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class UserVoucherExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\Voucher\UserVoucher::class;
}
