<?php

namespace App\Filament\Admin\Exports\HelpExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\Help\Help`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class HelpExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\Help\Help::class;
}
