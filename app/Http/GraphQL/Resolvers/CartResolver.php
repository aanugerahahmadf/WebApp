<?php

namespace App\Http\GraphQL\Resolvers;

use App\Models\Cart\Cart;
use App\Models\Catalog\Catalog;
use App\Models\Package\Package;
use App\Models\Voucher\Voucher;
use App\Models\User\UserAddress;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class CartResolver
{
    /**
     * Get current user's cart
     */
    public function myCart($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            return $this->getEmptyCart();
        }
        
        return $this->buildCart($user);
    }

    /**
     * Add item to cart
     */
    public function add($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            throw new \Exception('Unauthorized: Please login to add to cart');
        }
        
        $input = $args['input'];
        
        if (!$input['catalogId'] && !$input['packageId']) {
            throw new \Exception('Catalog or Package is required');
        }
        
        if ($input['catalogId'] && $input['packageId']) {
            throw new \Exception('Cannot add both catalog and package at once');
        }
        
        $quantity = $input['quantity'] ?? 1;
        $options = $input['options'] ?? [];
        
        // Check if item already exists in cart
        $existingItem = Cart::where('user_id', $user->id)
            ->where(function ($q) use ($input) {
                if ($input['catalogId']) {
                    $q->where('catalog_id', $input['catalogId']);
                }
                if ($input['packageId']) {
                    $q->where('package_id', $input['packageId']);
                }
            })
            ->where('options', json_encode($options))
            ->first();
        
        if ($existingItem) {
            $existingItem->quantity += $quantity;
            $existingItem->save();
            return $existingItem->load(['catalog', 'package']);
        }
        
        // Validate and get price
        $price = 0;
        $discountPrice = 0;
        
        if ($input['catalogId']) {
            $catalog = Catalog::findOrFail($input['catalogId']);
            
            if (!$catalog->is_active || $catalog->stock <= 0) {
                throw new \Exception('Catalog is not available');
            }
            
            if ($quantity > $catalog->stock) {
                throw new \Exception('Insufficient stock');
            }
            
            $price = $catalog->price;
            $discountPrice = $catalog->discount_price ?? 0;
        } else {
            $package = Package::findOrFail($input['packageId']);
            
            if (!$package->is_active) {
                throw new \Exception('Package is not available');
            }
            
            $price = $package->price;
            $discountPrice = $package->discount_price ?? 0;
        }
        
        $finalPrice = $discountPrice ?: $price;
        $subtotal = $finalPrice * $quantity;
        
        $cartItem = Cart::create([
            'user_id' => $user->id,
            'catalog_id' => $input['catalogId'],
            'package_id' => $input['packageId'],
            'quantity' => $quantity,
            'subtotal' => $subtotal,
            'options' => $options,
        ]);
        
        return $cartItem->load(['catalog', 'package']);
    }

    /**
     * Update cart item quantity
     */
    public function update($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            throw new \Exception('Unauthorized');
        }
        
        $cartItem = Cart::where('id', $args['cartItemId'])
            ->where('user_id', $user->id)
            ->firstOrFail();
        
        $quantity = $args['input']['quantity'];
        $options = $args['input']['options'] ?? [];
        
        if ($quantity <= 0) {
            $cartItem->delete();
            return $cartItem;
        }
        
        // Validate stock for catalogs
        if ($cartItem->catalog_id) {
            $catalog = Catalog::find($cartItem->catalog_id);
            if ($catalog && $quantity > $catalog->stock) {
                throw new \Exception('Insufficient stock');
            }
        }
        
        $finalPrice = $cartItem->catalog?->discount_price ?? $cartItem->catalog?->price 
            ?? $cartItem->package?->discount_price ?? $cartItem->package?->price;
        
        $cartItem->quantity = $quantity;
        $cartItem->subtotal = $finalPrice * $quantity;
        $cartItem->options = $options;
        $cartItem->save();
        
        return $cartItem->fresh()->load(['catalog', 'package']);
    }

    /**
     * Remove item from cart
     */
    public function remove($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            throw new \Exception('Unauthorized');
        }
        
        $cartItem = Cart::where('id', $args['cartItemId'])
            ->where('user_id', $user->id)
            ->firstOrFail();
        
        $cartItem->delete();
        
        return true;
    }

    /**
     * Clear entire cart
     */
    public function clear($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            throw new \Exception('Unauthorized');
        }
        
        Cart::where('user_id', $user->id)->delete();
        
        return true;
    }

    /**
     * Apply voucher to cart
     */
    public function applyVoucher($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            throw new \Exception('Unauthorized');
        }
        
        $cart = $this->buildCart($user);
        $voucher = Voucher::where('code', $args['code'])
            ->where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>=', now())
            ->first();
        
        if (!$voucher) {
            throw new \Exception('Invalid or expired voucher');
        }
        
        // Validate voucher for cart items
        $validation = (new VoucherResolver())->validate($root, [
            'code' => $args['code'],
            'cartTotal' => $cart['subtotal'],
            'items' => $cart['items']->map(function ($item) {
                return [
                    'id' => $item->catalog_id ?? $item->package_id,
                    'type' => $item->catalog_id ? 'catalog' : 'package',
                    'quantity' => $item->quantity,
                    'price' => $item->catalog?->price ?? $item->package?->price,
                ];
            })->toArray(),
        ], $context);
        
        if (!$validation['valid']) {
            throw new \Exception($validation['message']);
        }
        
        // Store voucher in session or cart metadata
        session(['cart_voucher_' . $user->id => $voucher->code]);
        
        return $this->buildCart($user);
    }

    /**
     * Remove voucher from cart
     */
    public function removeVoucher($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            throw new \Exception('Unauthorized');
        }
        
        session()->forget('cart_voucher_' . $user->id);
        
        return $this->buildCart($user);
    }

    /**
     * Sync cart from local storage
     */
    public function sync($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            throw new \Exception('Unauthorized');
        }
        
        foreach ($args['items'] as $item) {
            if ($item['quantity'] <= 0) continue;
            
            $existing = Cart::where('user_id', $user->id)
                ->where(function ($q) use ($item) {
                    if ($item['catalogId']) $q->where('catalog_id', $item['catalogId']);
                    if ($item['packageId']) $q->where('package_id', $item['packageId']);
                })
                ->first();
            
            if ($existing) {
                $existing->quantity = max($existing->quantity, $item['quantity']);
                $existing->save();
            } else {
                $this->add($root, ['input' => $item], $context);
            }
        }
        
        return $this->buildCart($user);
    }

    /**
     * Estimate shipping
     */
    public function estimateShipping($root, array $args, GraphQLContext $context)
    {
        $address = UserAddress::findOrFail($args['addressId']);
        
        // Simple shipping calculation based on location
        $baseCost = 15000; // Base shipping cost
        $estimatedDays = 3;
        
        // Adjust based on province (simplified)
        $remoteProvinces = ['Papua', 'Papua Barat', 'Maluku', 'Maluku Utara', 'Nusa Tenggara Timur', 'Nusa Tenggara Barat'];
        
        if (in_array($address->province, $remoteProvinces)) {
            $baseCost += 25000;
            $estimatedDays += 4;
        }
        
        return [
            'cost' => $baseCost,
            'estimatedDays' => $estimatedDays,
            'method' => 'Regular Shipping',
            'available' => true,
        ];
    }

    /**
     * Build cart object
     */
    private function buildCart($user)
    {
        $items = Cart::where('user_id', $user->id)
            ->with(['catalog', 'package'])
            ->get();
        
        $subtotal = $items->sum('subtotal');
        $voucherCode = session('cart_voucher_' . $user->id);
        $voucher = $voucherCode ? Voucher::where('code', $voucherCode)->first() : null;
        $discount = 0;
        
        if ($voucher) {
            $validation = (new VoucherResolver())->validate($root, [
                'code' => $voucherCode,
                'cartTotal' => $subtotal,
                'items' => $items->map(function ($item) {
                    return [
                        'id' => $item->catalog_id ?? $item->package_id,
                        'type' => $item->catalog_id ? 'catalog' : 'package',
                        'quantity' => $item->quantity,
                        'price' => $item->catalog?->price ?? $item->package?->price,
                    ];
                })->toArray(),
            ], new \Nuwave\Lighthouse\Support\Contracts\GraphQLContext());
            
            if ($validation['valid']) {
                $discount = $validation['discount'];
            }
        }
        
        // Estimate shipping (simplified)
        $shippingCost = 15000;
        $tax = round(($subtotal - $discount) * 0.11, 2); // 11% PPN
        
        return [
            'id' => 'cart_' . $user->id,
            'user' => $user,
            'items' => $items,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'voucher' => $voucher,
            'shippingCost' => $shippingCost,
            'tax' => $tax,
            'total' => $subtotal - $discount + $shippingCost + $tax,
            'itemCount' => $items->sum('quantity'),
            'updatedAt' => now(),
        ];
    }

    /**
     * Get empty cart structure
     */
    private function getEmptyCart()
    {
        return [
            'id' => 'cart_guest',
            'user' => null,
            'items' => collect(),
            'subtotal' => 0,
            'discount' => 0,
            'voucher' => null,
            'shippingCost' => 0,
            'tax' => 0,
            'total' => 0,
            'itemCount' => 0,
            'updatedAt' => now(),
        ];
    }
}