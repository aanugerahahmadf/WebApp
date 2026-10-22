<?php

namespace App\Filament\Admin\Exports\FmAppSettingExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\FmAppSetting\FmAppSetting`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class FmAppSettingExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\FmAppSetting\FmAppSetting::class;
}
