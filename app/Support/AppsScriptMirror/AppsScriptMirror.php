<?php

namespace App\Support\AppsScriptMirror;

use App\Jobs\PushToAppsScript\PushToAppsScript;
use App\Models\Order\Order;
use App\Models\Review\Review;

/**
 * Salinan otomatis order & review ke Google Apps Script.
 *
 * Dipasang dari AppServiceProvider lewat satu baris `AppsScriptMirror::boot()`.
 * Kelas ini TIDAK mengubah alur bisnis: ia hanya membaca model yang baru saja
 * tersimpan lalu menaruh job di queue. Semua jalur keluar dilindungi
 * `static::enabled()` supaya kegagalan Apps Script tidak pernah menjalar ke
 * checkout.
 *
 * Kenapa model event dan bukan observer: observer OrderObserver sudah memegang
 * logika stok, notifikasi, dan history. Menambah urusan sinkronisasi ke sana
 * membuat satu kegagalan eksternal ikut menjatuhkan transaksi pengguna.
 *
 * Aturan payload (apps_script/README.md §3.3):
 *  - nominal dan id besar sebagai STRING, jangan float;
 *  - field yang tidak dikirim harus benar-benar hilang dari array, bukan null;
 *  - nama kolom wajib sama dengan registry `Config.gs`.
 */
class AppsScriptMirror
{
    /** Perubahan pada kolom ini yang layak dikirim ulang. */
    protected const ORDER_DIRTY_FIELDS = [
        'status', 'payment_status', 'total_price', 'virtual_account_no',
        'virtual_account_expiry', 'qr_content', 'qr_expiry', 'notes',
        'booking_date', 'booking_time', 'quantity',
    ];

    /** Kolom yang berubah diam-diam (mis. timestamps) tidak perlu disinkron. */
    protected const REVIEW_DIRTY_FIELDS = ['rating', 'comment', 'title', 'photo', 'helpful_count'];

    public static function enabled(string $module): bool
    {
        if (! config('services.google_apps_script.enabled', false)) {
            return false;
        }

        return (bool) config("services.google_apps_script.mirror.$module", false);
    }

    /**
     * Pasang pendengar model. Aman dipanggil sekali saat boot.
     */
    public static function boot(): void
    {
        if (! static::enabled('orders') && ! static::enabled('reviews')) {
            return;
        }

        Order::created(fn (Order $order) => static::order($order, 'created'));
        Order::updated(fn (Order $order) => static::order($order, 'updated'));

        Review::created(fn (Review $review) => static::review($review, 'created'));
        Review::updated(fn (Review $review) => static::review($review, 'updated'));
    }

    /**
     * @param  Order  $order  model yang baru tersimpan
     * @param  string  $reason  'created' | 'updated', masuk ke log queue
     */
    public static function order(Order $order, string $reason = 'updated'): void
    {
        if (! static::enabled('orders')) {
            return;
        }

        // SaveQuietly/webhook meng-update baris yang tidak relevan (mis. hanya
        // updated_at). Mengirim semuanya membuat kuota Apps Script habis.
        if ($reason === 'updated' && ! static::touchesAny($order, self::ORDER_DIRTY_FIELDS)) {
            return;
        }

        $payload = static::orderPayload($order);

        if ($payload === []) {
            return;
        }

        PushToAppsScript::dispatch('orders.rows.append', ['records' => [$payload]], 'order #'.$order->id);
    }

    public static function review(Review $review, string $reason = 'created'): void
    {
        if (! static::enabled('reviews')) {
            return;
        }

        if ($reason === 'updated' && ! static::touchesAny($review, self::REVIEW_DIRTY_FIELDS)) {
            return;
        }

        PushToAppsScript::dispatch(
            'reviews.rows.append',
            ['records' => [static::reviewPayload($review)]],
            'review #'.$review->id,
        );
    }

    /**
     * Bentuk satu record order sesuai registry `orders.rows`.
     *
     * @return array<string,string>
     */
    public static function orderPayload(Order $order): array
    {
        $transaction = $order->transactions()->latest('id')->first();

        // Nominal mengikuti transactions.total_amount lebih dulu: order bisa
        // diedit (PUT /orders/{id}) dan AGENTS.md §5.5 menetapkan transaksi
        // terakhir sebagai angka yang berlaku.
        $amount = $transaction?->total_amount ?? $order->total_price;

        return static::filterNulls([
            'order_id' => (string) $order->id,
            'order_number' => (string) ($order->order_number ?? ''),
            'user_id' => $order->user_id !== null ? (string) $order->user_id : null,
            'package_name' => static::packageName($order),
            'quantity' => isset($order->quantity) ? (string) $order->quantity : null,
            'total_amount' => $amount !== null ? number_format((float) $amount, 0, '.', '') : null,
            'status' => static::enumValue($order->status),
            'payment_status' => static::enumValue($order->payment_status),
            'payment_type' => $transaction?->checkout_payment_type !== null
                ? (string) $transaction->checkout_payment_type
                : ($transaction?->payment_gateway !== null ? (string) $transaction->payment_gateway : null),
            'va_number' => $order->virtual_account_no ?? null,
            'payment_deadline' => static::stringify($order->virtual_account_expiry ?? $order->qr_expiry ?? null),
            'event_date' => static::stringify($order->booking_date ?? null),
            'event_time' => static::stringify($order->booking_time ?? null),
            'notes' => $order->notes !== null ? mb_substr((string) $order->notes, 0, 500) : null,
            'source' => 'laravel',
            'observed_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @return array<string,string>
     */
    public static function reviewPayload(Review $review): array
    {
        // Review menyimpan path sebagai kolom (`photos` array + `photo` tunggal),
        // BUKAN lewat Spatie MediaLibrary. Mengikuti aturan yang sama dengan
        // accessor model di App\Models\Review\Review.
        $paths = array_values(array_filter((array) ($review->photos ?: [])));

        if ($paths === [] && ! empty($review->photo)) {
            $paths = [(string) $review->photo];
        }

        return static::filterNulls([
            'review_id' => (string) $review->id,
            'catalog_id' => $review->catalog_id !== null ? (string) $review->catalog_id : null,
            'user_id' => $review->user_id !== null ? (string) $review->user_id : null,
            'rating' => $review->rating !== null ? (string) $review->rating : null,
            'title' => $review->title ?? null,
            // Komentar boleh panjang; sheet bukan tempat arsip penuh.
            'comment' => $review->comment !== null ? mb_substr((string) $review->comment, 0, 1000) : null,
            'has_media' => $paths !== [] ? '1' : '0',
            'media_paths' => $paths !== [] ? implode('|', $paths) : null,
            'is_verified_purchase' => '0',
            'source' => 'laravel',
            'observed_at' => now()->toIso8601String(),
        ]);
    }

    protected static function packageName(Order $order): ?string
    {
        return $order->catalog?->name ?? $order->package?->name ?? null;
    }

    protected static function enumValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof \BackedEnum ? (string) $value->value : (string) $value;
    }

    protected static function stringify(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : (string) $value;
    }

    /**
     * FieldApps Script membuang kolom yang tidak dikenal, tapi key bernilai
     * null akan tertulis sebagai teks kosong. Hilangkan supaya baris terakhir
     * tidak berubah jadi tembok nol.
     *
     * @param  array<string,string|null>  $payload
     * @return array<string,string>
     */
    protected static function filterNulls(array $payload): array
    {
        return array_filter(
            $payload,
            static fn ($value) => $value !== null && $value !== '',
        );
    }

    /**
     * Cek kolom yang benar-benar berubah. `wasChanged()` dipakai karena event
     * model fires SETELAH save, saat `isDirty()` sudah kosong.
     */
    protected static function touchesAny(mixed $model, array $columns): bool
    {
        $changed = method_exists($model, 'wasChanged') ? (array) $model->wasChanged() : [];

        return array_intersect(array_keys($changed), $columns) !== [];
    }
}
