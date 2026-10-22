<?php

namespace App\Filament\Admin\Exports\WeddingDecorationPolicyExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\WeddingDecorationPolicy\WeddingDecorationPolicy`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class WeddingDecorationPolicyExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\WeddingDecorationPolicy\WeddingDecorationPolicy::class;
}
