<?php

namespace App\Models\Discount;
use App\Models\Traits\LegacyMorphClass\LegacyMorphClass;

use App\Enums\DiscountType\DiscountType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $description
 * @property string $discountable_type
 * @property int $discountable_id
 * @property string $type
 * @property float $value
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|\Eloquent $discountable
 */
class Discount extends Model
{
    use LegacyMorphClass;
    protected $fillable = [
        'description',
        'discountable_type',
        'discountable_id',
        'type',
        'value',
        'min_purchase',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_purchase' => 'decimal:2',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_active' => 'boolean',
        'type' => DiscountType::class,
    ];

    public function discountable(): MorphTo
    {
        return $this->morphTo()->withDefault();
    }

    public function isValid(): bool
    {
        if (! $this->is_active) return false;
        if ($this->start_date && $this->start_date->isFuture()) return false;
        if ($this->end_date && $this->end_date->isPast()) return false;
        return true;
    }

    public function calculateDiscount(float $price): float
    {
        if (! $this->isValid()) return 0;
        if ($this->type === DiscountType::PERCENTAGE) {
            return round($price * ($this->value / 100), 2);
        }
        return min((float) $this->value, $price);
    }
}
