<?php

namespace App\Models\Voucher;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tautan user <-> voucher (tabel pivot `user_vouchers`).
 *
 * Kenapa perlu model sendiri: tabel pivot ini ADA isinya (voucher yang sudah
 * diklaim pengguna), tapi sebelumnya tidak punya model -- cuma relasi
 * `belongsToMany` di `User` dan `Voucher`.
 *
 * Tanpa model, tabel `user_vouchers` tidak bisa diekspor. Pusat Unduhan Data
 * harus bisa menurunkan SELURUH isi database, termasuk tabel pivot -- kalau
 * tidak, "voucher diklaim" (data penting untuk analisis) hilang dari laporan.
 *
 * @property int $id
 * @property int $user_id
 * @property int $voucher_id
 * @property int|null $order_id
 * @property \Illuminate\Support\Carbon|null $claimed_at
 * @property \Illuminate\Support\Carbon|null $used_at
 */
class UserVoucher extends Model
{
    protected $table = 'user_vouchers';

    protected $guarded = [];

    protected $casts = [
        'claimed_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }
}
