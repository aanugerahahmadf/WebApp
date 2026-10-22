<?php

namespace App\Filament\Admin\Exports\CatalogExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\Catalog\Catalog`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class CatalogExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\Catalog\Catalog::class;
}
