<?php

namespace App\Filament\Admin\Exports\WhatsappOtpExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\WhatsappOtp\WhatsappOtp`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class WhatsappOtpExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\WhatsappOtp\WhatsappOtp::class;
}
