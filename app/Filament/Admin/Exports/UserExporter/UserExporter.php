<?php

namespace App\Filament\Admin\Exports\UserExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\User\User`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class UserExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\User\User::class;
}
