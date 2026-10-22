<?php

namespace App\Filament\Admin\Exports\MediaExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `Spatie\MediaLibrary\MediaCollections\Models\Media`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class MediaExporter extends BaseExporter
{
    protected static ?string $model = \Spatie\MediaLibrary\MediaCollections\Models\Media::class;
}
