<?php

namespace App\Filament\Admin\Exports\PrivacyPolicyExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\PrivacyPolicy\PrivacyPolicy`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class PrivacyPolicyExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\PrivacyPolicy\PrivacyPolicy::class;
}
