<?php

namespace App\Filament\Admin\Exports\BankExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\Bank\Bank`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class BankExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\Bank\Bank::class;
}
