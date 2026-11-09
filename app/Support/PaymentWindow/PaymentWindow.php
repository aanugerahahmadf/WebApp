<?php

namespace App\Support\PaymentWindow;

use App\Models\Order\Order;
use App\Models\Transaction\Transaction;
use Illuminate\Support\Carbon;

/**
 * Satu sumber kebenaran untuk "berapa nominalnya" dan "sampai kapan".
 *
 * Sebelum ini dua angka itu dihitung ulang di setiap tempat (blade panel
 * Welcome, blade panel User, wizard checkout, aplikasi HP) dan hasilnya bisa
 * berbeda:
 *
 * - halaman pesanan menampilkan `total_price` (harga katalog),
 *   sedangkan webhook BRI mencocokkan `transactions.total_amount`
 *   (harga + biaya admin) -> nominal yang benar-benar harus ditransfer
 *   tidak pernah terlihat kalau ada biaya admin;
 * - masa berlaku VA diambil dari `virtual_account_expiry`, sementara QRIS
 *   statis tidak punya kolom itu sama sekali -> QRIS tampil tanpa batas
 *   waktu, padahal pesanannya tetap kedaluwarsa.
 *
 * Semua tempat sekarang memanggil class ini supaya angkanya tidak mungkin
 * berbeda. `deadline()` sengaja mengembalikan `null` kalau pesanan belum
 * punya `created_at` (mis. preview checkout sebelum pesanan dibuat); di
 * situ pemanggil menampilkan "berlaku N jam setelah pesanan dibuat".
 */
class PaymentWindow
{
    /** Dipakai kalau `bri.va_expiry_hours` kosong / tidak masuk akal. */
    public const DEFAULT_HOURS = 24;

    /**
     * Masa berlaku pembayaran (jam), DEFAULT 24 JAM.
     *
     * Satu nilai untuk VA DAN QRIS: dua metode itu sama-sama menunggu uang
     * masuk ke rekening yang sama, jadi tidak masuk akal kalau batas
     * waktunya berbeda.
     */
    public static function expiryHours(): int
    {
        $hours = (int) config('bri.va_expiry_hours', self::DEFAULT_HOURS);

        return $hours > 0 ? $hours : self::DEFAULT_HOURS;
    }

    /**
     * Batas waktu untuk sesuatu yang belum punya pesanan (preview checkout).
     */
    public static function deadlineFromNow(): Carbon
    {
        return Carbon::now()->addHours(static::expiryHours());
    }

    /**
     * Batas waktu pembayaran sebuah pesanan.
     *
     * Urutan sumber:
     * 1. `virtual_account_expiry` -- nilai yang benar-benar dipakai BRI;
     * 2. `metadata.bri_qris.validityPeriod` -- kalau QR dinamis BRI dipakai;
     * 3. `order.created_at + expiryHours()` -- QRIS statis / transfer manual.
     */
    public static function deadline(?Order $order = null, ?Transaction $transaction = null): ?Carbon
    {
        if ($transaction?->virtual_account_expiry) {
            return Carbon::parse($transaction->virtual_account_expiry);
        }

        $qrisExpiry = data_get($transaction?->metadata, 'bri_qris.qr_expiry')
            ?: data_get($transaction?->metadata, 'bri_qris.validityPeriod');

        if (is_string($qrisExpiry) && trim($qrisExpiry) !== '') {
            try {
                return Carbon::parse($qrisExpiry);
            } catch (\Throwable) {
                // Nilai provider yang tidak bisa diurai tidak boleh membuat
                // halaman pembayaran 500 -- jatuh ke batas dari pesanan.
            }
        }

        if ($order?->created_at) {
            return Carbon::parse($order->created_at)->addHours(static::expiryHours());
        }

        return null;
    }

    /**
     * Nominal yang harus dibayar pembeli.
     *
     * `total_amount` didahulukan karena itulah angka yang dibandingkan
     * webhook BRI (`amountMatches()`). Menampilkan angka lain berarti pembeli
     * mentransfer nominal yang "benar" menurut layar tapi ditolak sistem.
     */
    public static function amount(?Order $order = null, ?Transaction $transaction = null): float
    {
        $total = (float) ($transaction?->total_amount ?? 0);
        if ($total > 0) {
            return $total;
        }

        $amount = (float) ($transaction?->amount ?? 0);
        if ($amount > 0) {
            return $amount;
        }

        return (float) ($order?->total_price ?? 0);
    }

    /**
     * Total yang akan ditagihkan pada checkout (sebelum transaksi ada).
     *
     * Rumusnya HARUS sama dengan `handleCheckout()`:
     * `harga satuan * jumlah - diskon voucher + biaya admin`.
     */
    public static function payable(
        float $unitPrice,
        mixed $quantity = 1,
        mixed $discount = 0,
        mixed $adminFee = 0,
    ): float {
        $qty = max(1, (int) $quantity);
        $subtotal = $unitPrice * $qty;

        return max(0, $subtotal - (float) $discount) + (float) $adminFee;
    }

    /**
     * Format Rupiah tanpa desimal: 22000 -> "Rp 22.000".
     */
    public static function format(float|int|string|null $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }

    public static function isExpired(?Carbon $deadline): bool
    {
        return $deadline !== null && $deadline->isPast();
    }
}
