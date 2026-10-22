<?php

namespace App\Filament\Admin\Exports\ImportLogExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `Filament\Actions\Imports\Models\Import`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class ImportLogExporter extends BaseExporter
{
    protected static ?string $model = \Filament\Actions\Imports\Models\Import::class;
}
