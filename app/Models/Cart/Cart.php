<?php

namespace App\Models\Cart;
use App\Models\Traits\LegacyMorphClass\LegacyMorphClass;


use App\Models\Package\Package;
use App\Models\Product\Product;
use App\Models\User\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cart extends Model
{
    use LegacyMorphClass;
    protected $fillable = [
        'user_id',
        'product_id',
        'package_id',
        'quantity',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'quantity' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function getItemAttribute()
    {
        return $this->product ?? $this->package;
    }

    public function getSubtotalAttribute()
    {
        $item = $this->item;
        if (! $item) {
            return 0;
        }

        $price = $item->discount_price > 0 ? $item->discount_price : $item->price;

        return $price * $this->quantity;
    }
}
