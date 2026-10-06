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

    /**
     * Tambah jumlah item di keranjang, buat barisnya kalau belum ada.
     *
     * JANGAN pakai `updateOrCreate([...kunci...], ['quantity' => DB::raw('quantity + N')])`
     * untuk keperluan ini. updateOrCreate() memakai array kedua baik untuk UPDATE
     * maupun INSERT, dan pada INSERT ekspresi raw itu dikirim sebagai bound value
     * apa adanya -- sehingga kolom integer menerima literal string "quantity + 2".
     * SQLite menolaknya (no such column / type mismatch) dan MySQL non-strict
     * menyimpannya sebagai 0. Keranjang yang tadinya kosong jadi berisi 0, dan
     * test tidak pernah menangkapnya karena suite ini lama tidak dijalankan.
     *
     * firstOrNew() + penjumlahan di PHP aman untuk kedua cabang.
     */
    public static function incrementQuantity(int $userId, ?int $productId, ?int $packageId, int $delta = 1): self
    {
        $cart = static::firstOrNew([
            'user_id' => $userId,
            'product_id' => $productId,
            'package_id' => $packageId,
        ]);

        $cart->quantity = max(0, ((int) $cart->quantity) + $delta);
        $cart->save();

        return $cart;
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
