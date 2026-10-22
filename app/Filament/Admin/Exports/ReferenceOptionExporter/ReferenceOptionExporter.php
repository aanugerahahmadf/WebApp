<?php

namespace App\Filament\Admin\Exports\ReferenceOptionExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\ReferenceOption\ReferenceOption`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class ReferenceOptionExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\ReferenceOption\ReferenceOption::class;
}
