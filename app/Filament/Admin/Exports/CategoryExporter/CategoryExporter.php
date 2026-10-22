<?php

namespace App\Filament\Admin\Exports\CategoryExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\Category\Category`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class CategoryExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\Category\Category::class;
}
