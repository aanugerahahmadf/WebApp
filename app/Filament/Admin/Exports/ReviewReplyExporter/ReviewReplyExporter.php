<?php

namespace App\Filament\Admin\Exports\ReviewReplyExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\ReviewReply\ReviewReply`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class ReviewReplyExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\ReviewReply\ReviewReply::class;
}
