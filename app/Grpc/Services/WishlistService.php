<?php

namespace App\Grpc\Services;

use App\Models\Wishlist\Wishlist;
use App\Models\Catalog\Catalog;
use App\Models\Package\Package;
use App\Models\Cart\Cart;
use Grpc\ServerCall;
use Laravel\Sanctum\PersonalAccessToken;

class WishlistService
{
    public function GetWishlist(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $perPage = $request->getPerPage() ?: 50;
        $page = $request->getPage() ?: 1;
        
        $paginated = Wishlist::where('user_id', $user->id)
            ->with(['catalog', 'package', 'catalog.category', 'package.category'])
            ->latest()
            ->paginate($perPage, ['*'], 'page', $page);
        
        $response = new \Weddingapp\Wishlist\WishlistResponse();
        foreach ($paginated->items() as $item) {
            $response->addItems($this->buildWishlistItemProto($item));
        }
        
        $pagination = new \Weddingapp\Wishlist\PaginationMeta();
        $pagination->setCurrentPage($paginated->currentPage());
        $pagination->setPerPage($paginated->perPage());
        $pagination->setTotal($paginated->total());
        $pagination->setLastPage($paginated->lastPage());
        $pagination->setFrom($paginated->firstItem());
        $pagination->setTo($paginated->lastItem());
        $pagination->setHasNextPage($paginated->hasMorePages());
        $pagination->setHasPrevPage($paginated->currentPage() > 1);
        $response->setPagination($pagination);
        
        return $call->sendResponse($response);
    }

    public function ToggleWishlist(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        if (!$request->getCatalogId() && !$request->getPackageId()) {
            $response = new \Weddingapp\Wishlist\WishlistActionResponse();
            $response->setAction(\Weddingapp\Wishlist\WishlistActionType::WISHLIST_ACTION_TYPE_UNSPECIFIED);
            $response->setMessage('Catalog or Package is required');
            return $call->sendResponse($response);
        }
        
        $existing = Wishlist::where('user_id', $user->id)
            ->where(function ($q) use ($request) {
                if ($request->getCatalogId()) $q->where('catalog_id', $request->getCatalogId());
                if ($request->getPackageId()) $q->where('package_id', $request->getPackageId());
            })
            ->first();
        
        if ($existing) {
            $itemName = $existing->catalog?->name ?? $existing->package?->name ?? 'Item';
            $existing->delete();
            
            $response = new \Weddingapp\Wishlist\WishlistActionResponse();
            $response->setAction(\Weddingapp\Wishlist\WishlistActionType::REMOVED);
            $response->setMessage("{$itemName} removed from wishlist");
            return $call->sendResponse($response);
        }
        
        if ($request->getCatalogId()) {
            $catalog = Catalog::find($request->getCatalogId());
            if (!$catalog || !$catalog->is_active) {
                $response = new \Weddingapp\Wishlist\WishlistActionResponse();
                $response->setMessage('Catalog is not available');
                return $call->sendResponse($response);
            }
        } else {
            $package = Package::find($request->getPackageId());
            if (!$package || !$package->is_active) {
                $response = new \Weddingapp\Wishlist\WishlistActionResponse();
                $response->setMessage('Package is not available');
                return $call->sendResponse($response);
            }
        }
        
        $wishlist = Wishlist::create([
            'user_id' => $user->id,
            'catalog_id' => $request->getCatalogId() ?: null,
            'package_id' => $request->getPackageId() ?: null,
        ]);
        
        $itemName = $wishlist->catalog?->name ?? $wishlist->package?->name ?? 'Item';
        
        $response = new \Weddingapp\Wishlist\WishlistActionResponse();
        $response->setWishlist($this->buildWishlistItemProto($wishlist));
        $response->setAction(\Weddingapp\Wishlist\WishlistActionType::ADDED);
        $response->setMessage("{$itemName} added to wishlist");
        
        return $call->sendResponse($response);
    }

    public function AddToWishlist(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $existing = Wishlist::where('user_id', $user->id)
            ->where(function ($q) use ($request) {
                if ($request->getCatalogId()) $q->where('catalog_id', $request->getCatalogId());
                if ($request->getPackageId()) $q->where('package_id', $request->getPackageId());
            })
            ->first();
        
        if ($existing) {
            $response = new \Weddingapp\Wishlist\WishlistItemResponse();
            $response->setItem($this->buildWishlistItemProto($existing));
            $response->setMessage('Item already in wishlist');
            return $call->sendResponse($response);
        }
        
        $wishlist = Wishlist::create([
            'user_id' => $user->id,
            'catalog_id' => $request->getCatalogId() ?: null,
            'package_id' => $request->getPackageId() ?: null,
        ]);
        
        $response = new \Weddingapp\Wishlist\WishlistItemResponse();
        $response->setItem($this->buildWishlistItemProto($wishlist));
        $response->setMessage('Item added to wishlist');
        
        return $call->sendResponse($response);
    }

    public function RemoveFromWishlist(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $wishlist = Wishlist::where('id', $request->getWishlistId())
            ->where('user_id', $user->id)
            ->first();
        
        if (!$wishlist) {
            $response = new \Weddingapp\Wishlist\RemoveFromWishlistResponse();
            $response->setSuccess(false);
            $response->setMessage('Wishlist item not found');
            return $call->sendResponse($response);
        }
        
        $wishlist->delete();
        
        $response = new \Weddingapp\Wishlist\RemoveFromWishlistResponse();
        $response->setSuccess(true);
        $response->setMessage('Item removed from wishlist');
        
        return $call->sendResponse($response);
    }

    public function ClearWishlist(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        Wishlist::where('user_id', $user->id)->delete();
        
        $response = new \Weddingapp\Wishlist\ClearWishlistResponse();
        $response->setSuccess(true);
        $response->setMessage('Wishlist cleared');
        
        return $call->sendResponse($response);
    }

    public function IsInWishlist(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $exists = Wishlist::where('user_id', $user->id)
            ->where(function ($q) use ($request) {
                if ($request->getCatalogId()) $q->where('catalog_id', $request->getCatalogId());
                if ($request->getPackageId()) $q->where('package_id', $request->getPackageId());
            })
            ->exists();
        
        $response = new \Weddingapp\Wishlist\IsInWishlistResponse();
        $response->setInWishlist($exists);
        
        return $call->sendResponse($response);
    }

    public function MoveToCart(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $wishlist = Wishlist::where('id', $request->getWishlistId())
            ->where('user_id', $user->id)
            ->first();
        
        if (!$wishlist) {
            $response = new \Weddingapp\Cart\CartItemResponse();
            $response->setMessage('Wishlist item not found');
            return $call->sendResponse($response);
        }
        
        $quantity = $request->getQuantity() ?: 1;
        
        $cartItem = Cart::where('user_id', $user->id)
            ->where(function ($q) use ($wishlist) {
                if ($wishlist->catalog_id) $q->where('catalog_id', $wishlist->catalog_id);
                if ($wishlist->package_id) $q->where('package_id', $wishlist->package_id);
            })
            ->first();
        
        if ($cartItem) {
            $cartItem->quantity += $quantity;
            $cartItem->save();
        } else {
            $price = $wishlist->catalog?->price ?? $wishlist->package?->price;
            $discountPrice = $wishlist->catalog?->discount_price ?? $wishlist->package?->discount_price;
            $finalPrice = $discountPrice ?: $price;
            
            $cartItem = Cart::create([
                'user_id' => $user->id,
                'catalog_id' => $wishlist->catalog_id,
                'package_id' => $wishlist->package_id,
                'quantity' => $quantity,
                'subtotal' => $finalPrice * $quantity,
                'options' => [],
            ]);
        }
        
        $wishlist->delete();
        
        $response = new \Weddingapp\Cart\CartItemResponse();
        $response->setItem($this->buildCartItemProto($cartItem));
        $response->setMessage('Item moved to cart');
        
        return $call->sendResponse($response);
    }

    public function ShareWishlist(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $shareToken = \Illuminate\Support\Str::random(32);
        $expiresAt = now()->addDays(30);
        
        \Cache::put("wishlist_share_{$shareToken}", [
            'user_id' => $user->id,
            'expires_at' => $expiresAt,
        ], 30 * 24 * 60);
        
        $response = new \Weddingapp\Wishlist\ShareWishlistResponse();
        $response->setShareToken($shareToken);
        $response->setShareUrl(url("/shared/wishlist/{$shareToken}"));
        $response->setExpiresAt($expiresAt->toISOString());
        
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

    protected function buildWishlistItemProto($item): \Weddingapp\Wishlist\WishlistItem
    {
        $proto = new \Weddingapp\Wishlist\WishlistItem();
        $proto->setId($item->id);
        $proto->setUserId($item->user_id);
        $proto->setCatalogId($item->catalog_id ?? 0);
        $proto->setPackageId($item->package_id ?? 0);
        $proto->setCreatedAt($item->created_at?->toISOString() ?? '');
        
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
}