<?php

namespace App\Filament\Admin\Exports\DiscountExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\Discount\Discount`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class DiscountExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\Discount\Discount::class;
}
