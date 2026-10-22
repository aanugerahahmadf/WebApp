<?php

namespace App\Filament\Admin\Exports\PromotionExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\Home\Promotion`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class PromotionExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\Home\Promotion::class;
}
