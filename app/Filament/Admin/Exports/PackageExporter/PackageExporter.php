<?php

namespace App\Filament\Admin\Exports\PackageExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\Package\Package`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class PackageExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\Package\Package::class;
}
