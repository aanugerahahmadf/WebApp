<?php

namespace App\Filament\Admin\Exports\FavoriteExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\Favorite\Favorite`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class FavoriteExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\Favorite\Favorite::class;
}
