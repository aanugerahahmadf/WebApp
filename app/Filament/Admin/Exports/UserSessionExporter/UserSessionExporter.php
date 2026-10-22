<?php

namespace App\Filament\Admin\Exports\UserSessionExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\UserSession\UserSession`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class UserSessionExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\UserSession\UserSession::class;
}
