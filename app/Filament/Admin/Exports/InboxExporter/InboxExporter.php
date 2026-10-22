<?php

namespace App\Filament\Admin\Exports\InboxExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\Inbox\Inbox`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class InboxExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\Inbox\Inbox::class;
}
