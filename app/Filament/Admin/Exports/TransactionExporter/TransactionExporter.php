<?php

namespace App\Filament\Admin\Exports\TransactionExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\Transaction\Transaction`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class TransactionExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\Transaction\Transaction::class;
}
