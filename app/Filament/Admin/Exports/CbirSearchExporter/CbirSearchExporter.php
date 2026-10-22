<?php

namespace App\Filament\Admin\Exports\CbirSearchExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\Cbir\CbirSearch`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class CbirSearchExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\Cbir\CbirSearch::class;
}
