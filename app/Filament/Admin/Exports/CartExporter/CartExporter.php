<?php

namespace App\Filament\Admin\Exports\CartExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\Cart\Cart`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class CartExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\Cart\Cart::class;
}
