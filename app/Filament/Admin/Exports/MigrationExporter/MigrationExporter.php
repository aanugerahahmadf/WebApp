<?php

namespace App\Filament\Admin\Exports\MigrationExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel migration di Studio Basis Data.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class MigrationExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\DatabaseStudio\Migration::class;
}
