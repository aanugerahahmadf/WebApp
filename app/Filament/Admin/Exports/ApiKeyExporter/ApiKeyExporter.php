<?php

namespace App\Filament\Admin\Exports\ApiKeyExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel API key (tabel `personal_access_tokens`).
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 *
 * CATATAN: tabel ini berisi token API -- token yang dipakai untuk memanggil
 * API. Isinya sama persis dengan yang ditampilkan di tabel `/admin/api-keys`,
 * jadi tidak ada kebocoran baru dari tombol Ekspor.
 */
class ApiKeyExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\ApiKey\ApiKey::class;
}
