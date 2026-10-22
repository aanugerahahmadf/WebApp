<?php

namespace App\Filament\Admin\Exports\TermsOfServiceExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\TermsOfService\TermsOfService`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class TermsOfServiceExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\TermsOfService\TermsOfService::class;
}
