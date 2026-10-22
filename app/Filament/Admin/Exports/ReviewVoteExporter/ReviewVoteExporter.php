<?php

namespace App\Filament\Admin\Exports\ReviewVoteExporter;

use App\Filament\Shared\Exports\BaseExporter\BaseExporter;

/**
 * Exporter untuk tabel yang dipakai model `App\Models\ReviewVote\ReviewVote`.
 *
 * Kolom, notifikasi selesai, dan formatnya ikut dari
 * {@see BaseExporter}; kelas ini cuma menunjuk modelnya.
 */
class ReviewVoteExporter extends BaseExporter
{
    protected static ?string $model = \App\Models\ReviewVote\ReviewVote::class;
}
