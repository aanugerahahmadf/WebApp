<?php

namespace App\Filament\Admin\Exports\TranslationExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\Translation\Translation`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class TranslationExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\Translation\Translation::class;
}
