<?php

namespace App\Grpc\Services;

use App\Models\Favorite\Favorite;
use App\Models\Catalog\Catalog;
use App\Models\Package\Package;
use App\Models\Cart\Cart;
use Grpc\ServerCall;
use Laravel\Sanctum\PersonalAccessToken;

class Favoriteservice
{
    public function GetFavorite(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $perPage = $request->getPerPage() ?: 50;
        $page = $request->getPage() ?: 1;
        
        $paginated = Favorite::where('user_id', $user->id)
            ->with(['catalog', 'package', 'catalog.category', 'package.category'])
            ->latest()
            ->paginate($perPage, ['*'], 'page', $page);
        
        $response = new \Weddingapp\Favorite\FavoriteResponse();
        foreach ($paginated->items() as $item) {
            $response->addItems($this->buildFavoriteItemProto($item));
        }
        
        $pagination = new \Weddingapp\Favorite\PaginationMeta();
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

    public function ToggleFavorite(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        if (!$request->getCatalogId() && !$request->getPackageId()) {
            $response = new \Weddingapp\Favorite\FavoriteActionResponse();
            $response->setAction(\Weddingapp\Favorite\FavoriteActionType::Favorite_ACTION_TYPE_UNSPECIFIED);
            $response->setMessage('Catalog or Package is required');
            return $call->sendResponse($response);
        }
        
        $existing = Favorite::where('user_id', $user->id)
            ->where(function ($q) use ($request) {
                if ($request->getCatalogId()) $q->where('catalog_id', $request->getCatalogId());
                if ($request->getPackageId()) $q->where('package_id', $request->getPackageId());
            })
            ->first();
        
        if ($existing) {
            $itemName = $existing->catalog?->name ?? $existing->package?->name ?? 'Item';
            $existing->delete();
            
            $response = new \Weddingapp\Favorite\FavoriteActionResponse();
            $response->setAction(\Weddingapp\Favorite\FavoriteActionType::REMOVED);
            $response->setMessage("{$itemName} removed from Favorite");
            return $call->sendResponse($response);
        }
        
        if ($request->getCatalogId()) {
            $catalog = Catalog::find($request->getCatalogId());
            if (!$catalog || !$catalog->is_active) {
                $response = new \Weddingapp\Favorite\FavoriteActionResponse();
                $response->setMessage('Catalog is not available');
                return $call->sendResponse($response);
            }
        } else {
            $package = Package::find($request->getPackageId());
            if (!$package || !$package->is_active) {
                $response = new \Weddingapp\Favorite\FavoriteActionResponse();
                $response->setMessage('Package is not available');
                return $call->sendResponse($response);
            }
        }
        
        $Favorite = Favorite::create([
            'user_id' => $user->id,
            'catalog_id' => $request->getCatalogId() ?: null,
            'package_id' => $request->getPackageId() ?: null,
        ]);
        
        $itemName = $Favorite->catalog?->name ?? $Favorite->package?->name ?? 'Item';
        
        $response = new \Weddingapp\Favorite\FavoriteActionResponse();
        $response->setFavorite($this->buildFavoriteItemProto($Favorite));
        $response->setAction(\Weddingapp\Favorite\FavoriteActionType::ADDED);
        $response->setMessage("{$itemName} added to Favorite");
        
        return $call->sendResponse($response);
    }

    public function AddToFavorite(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $existing = Favorite::where('user_id', $user->id)
            ->where(function ($q) use ($request) {
                if ($request->getCatalogId()) $q->where('catalog_id', $request->getCatalogId());
                if ($request->getPackageId()) $q->where('package_id', $request->getPackageId());
            })
            ->first();
        
        if ($existing) {
            $response = new \Weddingapp\Favorite\FavoriteItemResponse();
            $response->setItem($this->buildFavoriteItemProto($existing));
            $response->setMessage('Item already in Favorite');
            return $call->sendResponse($response);
        }
        
        $Favorite = Favorite::create([
            'user_id' => $user->id,
            'catalog_id' => $request->getCatalogId() ?: null,
            'package_id' => $request->getPackageId() ?: null,
        ]);
        
        $response = new \Weddingapp\Favorite\FavoriteItemResponse();
        $response->setItem($this->buildFavoriteItemProto($Favorite));
        $response->setMessage('Item added to Favorite');
        
        return $call->sendResponse($response);
    }

    public function RemoveFromFavorite(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $Favorite = Favorite::where('id', $request->getFavoriteId())
            ->where('user_id', $user->id)
            ->first();
        
        if (!$Favorite) {
            $response = new \Weddingapp\Favorite\RemoveFromFavoriteResponse();
            $response->setSuccess(false);
            $response->setMessage('Favorite item not found');
            return $call->sendResponse($response);
        }
        
        $Favorite->delete();
        
        $response = new \Weddingapp\Favorite\RemoveFromFavoriteResponse();
        $response->setSuccess(true);
        $response->setMessage('Item removed from Favorite');
        
        return $call->sendResponse($response);
    }

    public function ClearFavorite(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        Favorite::where('user_id', $user->id)->delete();
        
        $response = new \Weddingapp\Favorite\ClearFavoriteResponse();
        $response->setSuccess(true);
        $response->setMessage('Favorite cleared');
        
        return $call->sendResponse($response);
    }

    public function IsInFavorite(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $exists = Favorite::where('user_id', $user->id)
            ->where(function ($q) use ($request) {
                if ($request->getCatalogId()) $q->where('catalog_id', $request->getCatalogId());
                if ($request->getPackageId()) $q->where('package_id', $request->getPackageId());
            })
            ->exists();
        
        $response = new \Weddingapp\Favorite\IsInFavoriteResponse();
        $response->setInFavorite($exists);
        
        return $call->sendResponse($response);
    }

    public function MoveToCart(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $Favorite = Favorite::where('id', $request->getFavoriteId())
            ->where('user_id', $user->id)
            ->first();
        
        if (!$Favorite) {
            $response = new \Weddingapp\Cart\CartItemResponse();
            $response->setMessage('Favorite item not found');
            return $call->sendResponse($response);
        }
        
        $quantity = $request->getQuantity() ?: 1;
        
        $cartItem = Cart::where('user_id', $user->id)
            ->where(function ($q) use ($Favorite) {
                if ($Favorite->catalog_id) $q->where('catalog_id', $Favorite->catalog_id);
                if ($Favorite->package_id) $q->where('package_id', $Favorite->package_id);
            })
            ->first();
        
        if ($cartItem) {
            $cartItem->quantity += $quantity;
            $cartItem->save();
        } else {
            $price = $Favorite->catalog?->price ?? $Favorite->package?->price;
            $discountPrice = $Favorite->catalog?->discount_price ?? $Favorite->package?->discount_price;
            $finalPrice = $discountPrice ?: $price;
            
            $cartItem = Cart::create([
                'user_id' => $user->id,
                'catalog_id' => $Favorite->catalog_id,
                'package_id' => $Favorite->package_id,
                'quantity' => $quantity,
                'subtotal' => $finalPrice * $quantity,
                'options' => [],
            ]);
        }
        
        $Favorite->delete();
        
        $response = new \Weddingapp\Cart\CartItemResponse();
        $response->setItem($this->buildCartItemProto($cartItem));
        $response->setMessage('Item moved to cart');
        
        return $call->sendResponse($response);
    }

    public function ShareFavorite(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $shareToken = \Illuminate\Support\Str::random(32);
        $expiresAt = now()->addDays(30);
        
        \Cache::put("Favorite_share_{$shareToken}", [
            'user_id' => $user->id,
            'expires_at' => $expiresAt,
        ], 30 * 24 * 60);
        
        $response = new \Weddingapp\Favorite\ShareFavoriteResponse();
        $response->setShareToken($shareToken);
        $response->setShareUrl(url("/shared/Favorite/{$shareToken}"));
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

    protected function buildFavoriteItemProto($item): \Weddingapp\Favorite\FavoriteItem
    {
        $proto = new \Weddingapp\Favorite\FavoriteItem();
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