<?php

namespace App\Http\GraphQL\Resolvers;

use App\Models\Wishlist\Wishlist;
use App\Models\Catalog\Catalog;
use App\Models\Package\Package;
use App\Models\Cart\Cart;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class WishlistResolver
{
    /**
     * Toggle wishlist (add if not exists, remove if exists)
     */
    public function toggle($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            throw new \Exception('Unauthorized: Please login to manage wishlist');
        }
        
        $input = $args['input'];
        
        if (!$input['catalogId'] && !$input['packageId']) {
            throw new \Exception('Catalog or Package is required');
        }
        
        $existing = Wishlist::where('user_id', $user->id)
            ->where(function ($q) use ($input) {
                if ($input['catalogId']) $q->where('catalog_id', $input['catalogId']);
                if ($input['packageId']) $q->where('package_id', $input['packageId']);
            })
            ->first();
        
        if ($existing) {
            $itemName = $existing->catalog?->name ?? $existing->package?->name ?? 'Item';
            $existing->delete();
            
            return [
                'wishlist' => $existing,
                'action' => 'REMOVED',
                'message' => "{$itemName} removed from wishlist",
            ];
        }
        
        // Validate catalog/package exists and is active
        if ($input['catalogId']) {
            $catalog = Catalog::findOrFail($input['catalogId']);
            if (!$catalog->is_active) {
                throw new \Exception('Catalog is not available');
            }
        } else {
            $package = Package::findOrFail($input['packageId']);
            if (!$package->is_active) {
                throw new \Exception('Package is not available');
            }
        }
        
        $wishlist = Wishlist::create([
            'user_id' => $user->id,
            'catalog_id' => $input['catalogId'],
            'package_id' => $input['packageId'],
        ]);
        
        $itemName = $wishlist->catalog?->name ?? $wishlist->package?->name ?? 'Item';
        
        return [
            'wishlist' => $wishlist->load(['catalog', 'package']),
            'action' => 'ADDED',
            'message' => "{$itemName} added to wishlist",
        ];
    }

    /**
     * Add to wishlist explicitly
     */
    public function add($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            throw new \Exception('Unauthorized: Please login to manage wishlist');
        }
        
        $input = $args['input'];
        
        $existing = Wishlist::where('user_id', $user->id)
            ->where(function ($q) use ($input) {
                if ($input['catalogId']) $q->where('catalog_id', $input['catalogId']);
                if ($input['packageId']) $q->where('package_id', $input['packageId']);
            })
            ->first();
        
        if ($existing) {
            return $existing->load(['catalog', 'package']);
        }
        
        return $this->toggle($root, $args, $context)['wishlist'];
    }

    /**
     * Remove from wishlist
     */
    public function remove($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            throw new \Exception('Unauthorized');
        }
        
        $wishlist = Wishlist::where('id', $args['wishlistId'])
            ->where('user_id', $user->id)
            ->firstOrFail();
        
        $wishlist->delete();
        
        return true;
    }

    /**
     * Clear entire wishlist
     */
    public function clear($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            throw new \Exception('Unauthorized');
        }
        
        Wishlist::where('user_id', $user->id)->delete();
        
        return true;
    }

    /**
     * Get current user's wishlist
     */
    public function myWishlist($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            return [];
        }
        
        return Wishlist::where('user_id', $user->id)
            ->with(['catalog', 'package', 'catalog.category', 'package.category'])
            ->latest()
            ->paginate($args['first'] ?? 50);
    }

    /**
     * Check if item is in wishlist
     */
    public function isInWishlist($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            return false;
        }
        
        return Wishlist::where('user_id', $user->id)
            ->where(function ($q) use ($args) {
                if ($args['catalogId']) $q->where('catalog_id', $args['catalogId']);
                if ($args['packageId']) $q->where('package_id', $args['packageId']);
            })
            ->exists();
    }

    /**
     * Move wishlist item to cart
     */
    public function moveToCart($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            throw new \Exception('Unauthorized');
        }
        
        $wishlist = Wishlist::where('id', $args['wishlistId'])
            ->where('user_id', $user->id)
            ->firstOrFail();
        
        $quantity = $args['quantity'] ?? 1;
        
        // Add to cart
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
        
        // Remove from wishlist
        $wishlist->delete();
        
        return $cartItem->load(['catalog', 'package']);
    }

    /**
     * Share wishlist
     */
    public function share($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            throw new \Exception('Unauthorized');
        }
        
        $shareToken = \Illuminate\Support\Str::random(32);
        $expiresAt = now()->addDays(30);
        
        // Store share token (in real app, save to database)
        \Cache::put("wishlist_share_{$shareToken}", [
            'user_id' => $user->id,
            'expires_at' => $expiresAt,
        ], 30 * 24 * 60);
        
        return [
            'shareToken' => $shareToken,
            'shareUrl' => url("/shared/wishlist/{$shareToken}"),
            'expiresAt' => $expiresAt,
        ];
    }
}