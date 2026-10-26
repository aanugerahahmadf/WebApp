<?php

namespace App\Models\PaymentMethod;
use App\Models\Traits\LegacyMorphClass\LegacyMorphClass;


use App\Models\Bank\Bank;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PaymentMethod extends Model
{
    use LegacyMorphClass;
    protected $fillable = [
        'name',
        'type',
        'code',
        'deeplink',
        'account_number',
        'account_holder',
        'bank_name',
        'image_url',
        'instructions',
        'fee',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'fee' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function getImageUrlAttribute($value): ?string
    {
        if (! $value) {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        return Storage::disk('public')->url($value);
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'bank_transfer' => __('Transfer Bank'),
            // Nomor VA dibuat unik per transaksi; dana masuk otomatis ke
            // rekening BRI admin tanpa verifikasi manual.
            'virtual_account' => __('Virtual Account'),
            'e_wallet' => __('E-Wallet'),
            'qris' => 'QRIS',
            'credit_card' => __('Kartu Kredit/Debit'),
            'cash' => __('Bayar di Tempat'),
            default => ucwords(str_replace('_', ' ', $type)),
        };
    }

    /**
     * Map of active methods for a Filament Select/Radio, keyed by id.
     */
    public static function activeOptions(): array
    {
        return static::activeQuery()
            ->get()
            ->mapWithKeys(fn (PaymentMethod $m) => [
                $m->id => static::typeLabel($m->type).' · '.$m->name.($m->fee > 0 ? ' ('.number_format($m->fee, 0, ',', '.').')' : ''),
            ])
            ->all();
    }

    /**
     * Short per-method summaries for Radio descriptions.
     */
    public static function activeDescriptions(): array
    {
        return static::activeQuery()
            ->get()
            ->mapWithKeys(function (PaymentMethod $m): array {
                if ($m->type === 'virtual_account') {
                    $user = auth()->user();
                    $name = trim((string) ($user?->full_name ?: $user?->username));

                    return [$m->id => trim(implode(' · ', array_filter([
                        $m->bank_name ?: 'BRI',
                        $name !== '' ? __('Atas nama :name', ['name' => $name]) : null,
                        __('Nomor VA unik dibuat otomatis setelah pesanan dibuat'),
                    ])))];
                }

                return [$m->id => trim(implode(' · ', array_filter([
                    $m->bank_name,
                    $m->account_holder,
                    $m->account_number ? (string) $m->account_number : null,
                    $m->type === 'cash' ? __('Bayar saat layanan tiba') : null,
                ]))) ?: \Illuminate\Support\Str::limit((string) $m->instructions, 90)];
            })
            ->all();
    }

    public static function activeFirstId(): int
    {
        return (int) static::activeQuery()->value('id');
    }

    protected static function activeQuery()
    {
        return static::query()->where('is_active', true)->orderBy('sort_order');
    }
}
