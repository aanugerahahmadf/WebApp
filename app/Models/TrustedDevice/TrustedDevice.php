<?php

namespace App\Models\TrustedDevice;
use App\Models\Traits\LegacyMorphClass\LegacyMorphClass;


use App\Models\User\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/*
 * Satu baris = satu perangkat yang pernah dipakai satu akun (registry ala
 * Google/Instagram). Tabel ini LEGACY dari app sebelumnya -- sekarang
 * DeviceTrustService yang mengisinya:
 *
 *   device_fingerprint  pengenal perangkat STABIL: hash User-Agent.
 *                       (tak sama dengan signature di tabel trust_devices
 *                       milik paket multi-2fa, yang memakai random salt
 *                       dan hanya ada bagi pengguna 2FA)
 *   trusted_at  NULL    belum menjawab "Apakah ini Anda?"
 *               set     user menjawab "Ya, Ini Saya"
 *               hapus   user menghapus dari daftar perangkat
 *   denied_at           user menjawab "Bukan saya"
 */
class TrustedDevice extends Model
{
    use LegacyMorphClass;
    protected $fillable = [
        'user_id',
        'device_name',
        'device_fingerprint',
        'platform',
        'trusted_at',
        'first_seen_at',
        'last_seen_at',
        'last_ip',
        'last_city',
        'last_region',
        'last_country',
        'denied_at',
        'last_notified_at',
    ];

    protected $casts = [
        'trusted_at' => 'datetime',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'denied_at' => 'datetime',
        'last_notified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Kalimat lokasi "Jakarta, ID" untuk pesan notifikasi.
     */
    public function locationSentence(): string
    {
        $parts = array_values(array_filter([
            $this->last_city,
            $this->last_country,
        ]));

        return $parts ? implode(', ', $parts) : __('Lokasi tidak diketahui');
    }

    /**
     * Nama perangkat yang tampil di daftar: label manusiawi dari
     * DeviceTrustService, dengan jaring pengaman untuk baris legacy.
     */
    public function displayLabel(): string
    {
        return $this->device_name ?: __('Perangkat tidak dikenal');
    }
}
