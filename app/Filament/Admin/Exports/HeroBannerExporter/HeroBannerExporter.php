<?php

namespace App\Filament\Admin\Exports\HeroBannerExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\Home\HeroBanner`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class HeroBannerExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\Home\HeroBanner::class;
}
