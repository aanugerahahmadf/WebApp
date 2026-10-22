<?php

namespace App\Filament\Admin\Exports\MessageExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\Message\Message`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class MessageExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\Message\Message::class;
}
