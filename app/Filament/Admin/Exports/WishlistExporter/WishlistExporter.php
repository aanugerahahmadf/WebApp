<?php

namespace App\Filament\Admin\Exports\WishlistExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\Wishlist\Wishlist`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class WishlistExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\Wishlist\Wishlist::class;
}
