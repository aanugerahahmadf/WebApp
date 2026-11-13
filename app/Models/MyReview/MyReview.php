<?php

namespace App\Models\MyReview;

use App\Models\Review\Review;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * "Komentar Penilaian Katalog Saya" milik user.
 *
 * Terhubung langsung ke App\Models\Review\Review: tabel dan perilaku sama
 * (reviews), sehingga tetap satu sumber data dengan ReviewResource admin.
 */
class MyReview extends Review
{
    protected $table = 'reviews';

    /**
     * Batasi ke ulasan milik user yang sedang terautentikasi bila ada konteks auth.
     */
    public function scopeMine(Builder $query, ?int $userId = null): Builder
    {
        $userId ??= Auth::id();

        return $userId ? $query->where('user_id', $userId) : $query;
    }
}