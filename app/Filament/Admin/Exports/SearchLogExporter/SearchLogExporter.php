<?php

namespace App\Filament\Admin\Exports\SearchLogExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\Search\SearchLog`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class SearchLogExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\Search\SearchLog::class;
}
