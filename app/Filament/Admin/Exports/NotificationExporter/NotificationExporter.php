<?php

namespace App\Filament\Admin\Exports\NotificationExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `Illuminate\Notifications\DatabaseNotification`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class NotificationExporter extends BaseExporter
{
    protected static ?string $model = \Illuminate\Notifications\DatabaseNotification::class;
}
