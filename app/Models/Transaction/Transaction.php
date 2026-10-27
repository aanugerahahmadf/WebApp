<?php

namespace App\Models\Transaction;

use App\Enums\OrderPaymentStatus\OrderPaymentStatus;
use App\Enums\OrderStatus\OrderStatus;
use App\Enums\PaymentStatus\PaymentStatus;
use App\Enums\TransactionType\TransactionType;
use App\Events\OrderStatusUpdated\OrderStatusUpdated;
use App\Models\Order\Order;
use App\Models\Traits\LegacyMorphClass\LegacyMorphClass;
use App\Models\User\User;
use App\Models\Voucher\Voucher;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Transaction extends Model
{
    use HasFactory;
    use LegacyMorphClass;

    protected $fillable = [
        'user_id',
        'order_id',
        'type', // 'topup' or 'order'
        'reference_number',
        'amount',
        'admin_fee',
        'total_amount',
        'payment_gateway',
        'payment_method',
        'payment_method_id',
        'payment_url',
        'virtual_account_no',
        'virtual_account_expiry',
        'status', // 'pending', 'success', 'failed', 'expired', 'cancelled'
        'paid_at',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'admin_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'virtual_account_expiry' => 'datetime',
        'metadata' => 'json',
        'status' => PaymentStatus::class,
        'type' => TransactionType::class,
    ];

    protected $appends = [
        'virtual_account_name',
    ];

    /**
     * Nama yang dipakai sebagai pemilik Virtual Account.
     *
     * Dipakai dua kali: saatBRI membuat VA (sebelum VA ada) dan saat model
     * diserialisasi. Satu fungsi supaya keduanya tidak mungkin berbeda.
     */
    public static function virtualAccountNameFor(?User $user): string
    {
        if ($user) {
            // Keep the provider name in the same order as the profile form:
            // first name, middle name, last name. This prevents a provider
            // request from accidentally using only `full_name` or username.
            $nameParts = array_values(array_filter([
                trim((string) $user->first_name),
                trim((string) $user->mid_name),
                trim((string) $user->last_name),
            ], static fn (string $part): bool => $part !== ''));
            $structuredName = trim(implode(' ', $nameParts));
            $fullName = trim((string) $user->full_name);

            // full_name is the persisted canonical value. It also protects
            // older/imported users whose component fields are stale or were
            // generated independently of the displayed full name.
            $name = $fullName !== '' && $structuredName !== $fullName
                ? $fullName
                : $structuredName;

            // Older users may only have full_name. Split it into the same
            // first/middle/last order before falling back to username.
            if ($name === '') {
                $fullNameParts = preg_split('/\s+/', trim((string) $user->full_name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                if (count($fullNameParts) >= 3) {
                    $name = implode(' ', $fullNameParts);
                } elseif (count($fullNameParts) === 2) {
                    $name = implode(' ', $fullNameParts);
                }
            }

            if ($name === '') {
                $name = trim((string) $user->username);
            }
        } else {
            $name = '';
        }

        if ($name === '') {
            // VA tanpa nama ditolak BRI, jadi selalu ada nilai yang dikirim.
            $name = trim((string) config('bri.account_holder', 'ADMIN'));
        }

        // BRI membatasi panjang virtualAccountName maksimal 40 karakter.
        return mb_substr($name !== '' ? $name : 'ADMIN', 0, 40);
    }

    /**
     * Nama pemilik Virtual Account, sesuai yang tampil di aplikasi BRI.
     *
     * Tidak butuh kolom sendiri: nama asli tersimpan di payload BRI pada
     * `metadata.bri_va`. Kalau payload itu belum ada, turunkan dari user.
     */
    protected function virtualAccountName(): Attribute
    {
        return Attribute::get(function (): string {
            $stored = data_get($this->metadata, 'bri_va.virtualAccountData.virtualAccountName')
                ?: data_get($this->metadata, 'bri_va.virtualAccountName');

            if (is_string($stored) && trim($stored) !== '') {
                return $stored;
            }

            return self::virtualAccountNameFor($this->user);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function markAsSuccess(): void
    {
        DB::transaction(function () {
            $transaction = static::lockForUpdate()->find($this->id);
            if (! $transaction || $transaction->status === PaymentStatus::SUCCESS) {
                return;
            }

            $transaction->update([
                'status' => 'success',
                'paid_at' => now(),
            ]);

            if ($transaction->type === TransactionType::TOPUP) {
                // Balance di-increment oleh TransactionObserver::updated()
                // (dengan guard idempotent getOriginal). Jangan increment di sini
                // ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â duplikasi bikin saldo bertambah 2x.
            } elseif ($transaction->type === TransactionType::ORDER && $transaction->order) {
                $transaction->order->update([
                    'status' => OrderStatus::CONFIRMED,
                    'payment_status' => OrderPaymentStatus::PAID,
                ]);

                event(new OrderStatusUpdated([
                    'order_id' => $transaction->order_id,
                    'order_number' => $transaction->order->order_number,
                    'status' => 'confirmed',
                    'payment_status' => 'paid',
                    'updated_at' => now()->toISOString(),
                ], $transaction->user_id));

                try {
                    $voucherLink = DB::table('user_vouchers')
                        ->where('order_id', $transaction->order_id)
                        ->where('user_id', $transaction->user_id)
                        ->first();

                    if ($voucherLink && $voucherLink->voucher_id) {
                        $voucher = Voucher::find($voucherLink->voucher_id);
                        if ($voucher) {
                            $voucher->markAsUsedBy($transaction->user_id, $transaction->order_id);
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('[Transaction] Voucher mark failed: '.$e->getMessage());
                }
            }
        });
    }

    public function markAsFailed(?string $reason = null): void
    {
        $this->update([
            'status' => 'failed',
            'notes' => $reason ?? $this->notes,
        ]);

        if ($this->type === TransactionType::ORDER && $this->order) {
            $this->order->update([
                'payment_status' => OrderPaymentStatus::FAILED,
            ]);

            event(new OrderStatusUpdated([
                'order_id' => $this->order_id,
                'order_number' => $this->order->order_number,
                'status' => $this->order->status->value ?? (string) $this->order->status,
                'payment_status' => 'failed',
                'updated_at' => now()->toISOString(),
            ], $this->user_id));
        }
    }
}
