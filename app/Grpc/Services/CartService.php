<?php

namespace App\Grpc\Services;

use App\Models\Cart\Cart;
use App\Models\Catalog\Catalog;
use App\Models\Package\Package;
use App\Models\Voucher\Voucher;
use App\Models\User\UserAddress;
use Grpc\ServerCall;
use Laravel\Sanctum\PersonalAccessToken;

class CartService
{
    public function GetCart(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        $cart = $this->buildCart($user);
        
        $response = new \Weddingapp\Cart\CartResponse();
        $response->setCart($cart);
        
        return $call->sendResponse($response);
    }

    public function AddToCart(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        if (!$request->getCatalogId() && !$request->getPackageId()) {
            $response = new \Weddingapp\Cart\CartItemResponse();
            $response->setMessage('Catalog or Package is required');
            return $call->sendResponse($response);
        }
        
        if ($request->getCatalogId() && $request->getPackageId()) {
            $response = new \Weddingapp\Cart\CartItemResponse();
            $response->setMessage('Cannot add both catalog and package at once');
            return $call->sendResponse($response);
        }
        
        $quantity = $request->getQuantity() ?: 1;
        $options = $request->getOptions();
        
        $existingItem = Cart::where('user_id', $user->id)
            ->where(function ($q) use ($request) {
                if ($request->getCatalogId()) $q->where('catalog_id', $request->getCatalogId());
                if ($request->getPackageId()) $q->where('package_id', $request->getPackageId());
            })
            ->where('options', json_encode($options))
            ->first();
        
        if ($existingItem) {
            $existingItem->quantity += $quantity;
            $existingItem->save();
            
            $response = new \Weddingapp\Cart\CartItemResponse();
            $response->setItem($this->buildCartItemProto($existingItem->fresh()));
            $response->setMessage('Cart item updated');
            return $call->sendResponse($response);
        }
        
        $price = 0;
        $discountPrice = 0;
        
        if ($request->getCatalogId()) {
            $catalog = Catalog::find($request->getCatalogId());
            if (!$catalog || !$catalog->is_active || $catalog->stock <= 0) {
                $response = new \Weddingapp\Cart\CartItemResponse();
                $response->setMessage('Catalog is not available');
                return $call->sendResponse($response);
            }
            if ($quantity > $catalog->stock) {
                $response = new \Weddingapp\Cart\CartItemResponse();
                $response->setMessage('Insufficient stock');
                return $call->sendResponse($response);
            }
            $price = $catalog->price;
            $discountPrice = $catalog->discount_price ?? 0;
        } else {
            $package = Package::find($request->getPackageId());
            if (!$package || !$package->is_active) {
                $response = new \Weddingapp\Cart\CartItemResponse();
                $response->setMessage('Package is not available');
                return $call->sendResponse($response);
            }
            $price = $package->price;
            $discountPrice = $package->discount_price ?? 0;
        }
        
        $finalPrice = $discountPrice ?: $price;
        $subtotal = $finalPrice * $quantity;
        
        $cartItem = Cart::create([
            'user_id' => $user->id,
            'catalog_id' => $request->getCatalogId() ?: null,
            'package_id' => $request->getPackageId() ?: null,
            'quantity' => $quantity,
            'subtotal' => $subtotal,
            'options' => $options,
        ]);
        
        $response = new \Weddingapp\Cart\CartItemResponse();
        $response->setItem($this->buildCartItemProto($cartItem));
        $response->setMessage('Item added to cart');
        
        return $call->sendResponse($response);
    }

    public function UpdateCartItem(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $cartItem = Cart::where('id', $request->getCartItemId())
            ->where('user_id', $user->id)
            ->first();
        
        if (!$cartItem) {
            $response = new \Weddingapp\Cart\CartItemResponse();
            $response->setMessage('Cart item not found');
            return $call->sendResponse($response);
        }
        
        $quantity = $request->getQuantity();
        $options = $request->getOptions();
        
        if ($quantity <= 0) {
            $cartItem->delete();
            $response = new \Weddingapp\Cart\CartItemResponse();
            $response->setMessage('Cart item removed');
            return $call->sendResponse($response);
        }
        
        if ($cartItem->catalog_id) {
            $catalog = Catalog::find($cartItem->catalog_id);
            if ($catalog && $quantity > $catalog->stock) {
                $response = new \Weddingapp\Cart\CartItemResponse();
                $response->setMessage('Insufficient stock');
                return $call->sendResponse($response);
            }
        }
        
        $finalPrice = $cartItem->catalog?->discount_price ?? $cartItem->catalog?->price 
            ?? $cartItem->package?->discount_price ?? $cartItem->package?->price;
        
        $cartItem->quantity = $quantity;
        $cartItem->subtotal = $finalPrice * $quantity;
        $cartItem->options = $options;
        $cartItem->save();
        
        $response = new \Weddingapp\Cart\CartItemResponse();
        $response->setItem($this->buildCartItemProto($cartItem->fresh()));
        $response->setMessage('Cart item updated');
        
        return $call->sendResponse($response);
    }

    public function RemoveFromCart(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $cartItem = Cart::where('id', $request->getCartItemId())
            ->where('user_id', $user->id)
            ->first();
        
        if (!$cartItem) {
            $response = new \Weddingapp\Cart\RemoveFromCartResponse();
            $response->setSuccess(false);
            $response->setMessage('Cart item not found');
            return $call->sendResponse($response);
        }
        
        $cartItem->delete();
        
        $response = new \Weddingapp\Cart\RemoveFromCartResponse();
        $response->setSuccess(true);
        $response->setMessage('Item removed from cart');
        
        return $call->sendResponse($response);
    }

    public function ClearCart(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        Cart::where('user_id', $user->id)->delete();
        
        $response = new \Weddingapp\Cart\ClearCartResponse();
        $response->setSuccess(true);
        $response->setMessage('Cart cleared');
        
        return $call->sendResponse($response);
    }

    public function ApplyVoucher(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $voucher = Voucher::where('code', $request->getCode())
            ->where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>=', now())
            ->first();
        
        if (!$voucher) {
            $response = new \Weddingapp\Cart\CartResponse();
            return $call->sendResponse($response);
        }
        
        session(['cart_voucher_' . $user->id => $voucher->code]);
        
        $cart = $this->buildCart($user);
        $response = new \Weddingapp\Cart\CartResponse();
        $response->setCart($cart);
        
        return $call->sendResponse($response);
    }

    public function RemoveVoucher(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        session()->forget('cart_voucher_' . $user->id);
        
        $cart = $this->buildCart($user);
        $response = new \Weddingapp\Cart\CartResponse();
        $response->setCart($cart);
        
        return $call->sendResponse($response);
    }

    public function SyncCart(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        foreach ($request->getItems() as $item) {
            if ($item->getQuantity() <= 0) continue;
            
            $existing = Cart::where('user_id', $user->id)
                ->where(function ($q) use ($item) {
                    if ($item->getCatalogId()) $q->where('catalog_id', $item->getCatalogId());
                    if ($item->getPackageId()) $q->where('package_id', $item->getPackageId());
                })
                ->first();
            
            if ($existing) {
                $existing->quantity = max($existing->quantity, $item->getQuantity());
                $existing->save();
            } else {
                $this->addToCart($call, $item);
            }
        }
        
        $cart = $this->buildCart($user);
        $response = new \Weddingapp\Cart\CartResponse();
        $response->setCart($cart);
        
        return $call->sendResponse($response);
    }

    public function EstimateShipping(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        $address = UserAddress::find($request->getAddressId());
        
        $baseCost = 15000;
        $estimatedDays = 3;
        
        $remoteProvinces = ['Papua', 'Papua Barat', 'Maluku', 'Maluku Utara', 'Nusa Tenggara Timur', 'Nusa Tenggara Barat'];
        
        if ($address && in_array($address->province, $remoteProvinces)) {
            $baseCost += 25000;
            $estimatedDays += 4;
        }
        
        $estimate = new \Weddingapp\Cart\ShippingEstimate();
        $estimate->setCost($baseCost);
        $estimate->setEstimatedDays($estimatedDays);
        $estimate->setMethod('Regular Shipping');
        $estimate->setAvailable(true);
        
        $response = new \Weddingapp\Cart\ShippingEstimateResponse();
        $response->setEstimate($estimate);
        
        return $call->sendResponse($response);
    }

    protected function getUserFromToken(string $token): \App\Models\User\User
    {
        $accessToken = PersonalAccessToken::findToken($token);
        if (!$accessToken) {
            throw new \Exception('Unauthorized: Invalid token');
        }
        return $accessToken->tokenable;
    }

    protected function buildCart($user): \Weddingapp\Cart\Cart
    {
        $items = Cart::where('user_id', $user->id)
            ->with(['catalog', 'package'])
            ->get();
        
        $subtotal = $items->sum('subtotal');
        $voucherCode = session('cart_voucher_' . $user->id);
        $voucher = $voucherCode ? Voucher::where('code', $voucherCode)->first() : null;
        $discount = 0;
        
        if ($voucher) {
            $discount = $this->calculateVoucherDiscount($voucher, $subtotal, $items);
        }
        
        $shippingCost = 15000;
        $tax = round(($subtotal - $discount) * 0.11, 2);
        
        $cart = new \Weddingapp\Cart\Cart();
        $cart->setId('cart_' . $user->id);
        
        $userProto = new \Weddingapp\Cart\User();
        $userProto->setId($user->id);
        $userProto->setName($user->name);
        $userProto->setEmail($user->email);
        $cart->setUser($userProto);
        
        foreach ($items as $item) {
            $cart->addItems($this->buildCartItemProto($item));
        }
        
        $cart->setSubtotal($subtotal);
        $cart->setDiscount($discount);
        
        if ($voucher) {
            $voucherProto = new \Weddingapp\Voucher\Voucher();
            $voucherProto->setId($voucher->id);
            $voucherProto->setCode($voucher->code);
            $voucherProto->setName($voucher->name ?? '');
            $voucherProto->setDiscountAmount($voucher->discount_amount);
            $voucherProto->setDiscountType($voucher->discount_type);
            $cart->setVoucher($voucherProto);
        }
        
        $cart->setShippingCost($shippingCost);
        $cart->setTax($tax);
        $cart->setTotal($subtotal - $discount + $shippingCost + $tax);
        $cart->setItemCount($items->sum('quantity'));
        $cart->setUpdatedAt(now()->toISOString());
        
        return $cart;
    }

    protected function buildCartItemProto($item): \Weddingapp\Cart\CartItem
    {
        $proto = new \Weddingapp\Cart\CartItem();
        $proto->setId($item->id);
        $proto->setUserId($item->user_id);
        $proto->setCatalogId($item->catalog_id ?? 0);
        $proto->setPackageId($item->package_id ?? 0);
        $proto->setQuantity($item->quantity);
        $proto->setSubtotal($item->subtotal);
        $proto->setCreatedAt($item->created_at?->toISOString() ?? '');
        $proto->setUpdatedAt($item->updated_at?->toISOString() ?? '');
        
        if ($item->catalog) {
            $catalogProto = new \Weddingapp\Catalog\Catalog();
            $catalogProto->setId($item->catalog->id);
            $catalogProto->setName($item->catalog->name);
            $catalogProto->setSlug($item->catalog->slug);
            $catalogProto->setPrice($item->catalog->price);
            $catalogProto->setDiscountPrice($item->catalog->discount_price ?? 0);
            $catalogProto->setFinalPrice($item->catalog->final_price);
            $catalogProto->setImageUrl($item->catalog->image_url ?? '');
            $proto->setCatalog($catalogProto);
        }
        
        if ($item->package) {
            $packageProto = new \Weddingapp\Package\Package();
            $packageProto->setId($item->package->id);
            $packageProto->setName($item->package->name);
            $packageProto->setSlug($item->package->slug);
            $packageProto->setPrice($item->package->price);
            $packageProto->setDiscountPrice($item->package->discount_price ?? 0);
            $packageProto->setFinalPrice($item->package->final_price);
            $packageProto->setImageUrl($item->package->image_url ?? '');
            $proto->setPackage($packageProto);
        }
        
        return $proto;
    }

    protected function calculateVoucherDiscount(Voucher $voucher, float $subtotal, $items): float
    {
        $discount = 0;
        
        foreach ($items as $item) {
            $itemTotal = $item->subtotal;
            
            if ($voucher->discount_type === 'PERCENTAGE') {
                $itemDiscount = $itemTotal * ($voucher->discount_amount / 100);
                if ($voucher->max_discount && $itemDiscount > $voucher->max_discount) {
                    $itemDiscount = $voucher->max_discount;
                }
                $discount += $itemDiscount;
            } elseif ($voucher->discount_type === 'FIXED_AMOUNT') {
                $discount += min($voucher->discount_amount, $itemTotal);
            }
        }
        
        return $discount;
    }
}