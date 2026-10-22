<?php

namespace App\Filament\Admin\Exports\UserLanguageExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\UserLanguage\UserLanguage`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class UserLanguageExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\UserLanguage\UserLanguage::class;
}
