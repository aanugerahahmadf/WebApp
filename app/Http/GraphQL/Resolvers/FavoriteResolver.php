<?php

namespace App\Http\GraphQL\Resolvers;

use App\Models\Favorite\Favorite;
use App\Models\Catalog\Catalog;
use App\Models\Package\Package;
use App\Models\Cart\Cart;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class FavoriteResolver
{
    /**
     * Toggle Favorite (add if not exists, remove if exists)
     */
    public function toggle($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            throw new \Exception('Unauthorized: Please login to manage Favorite');
        }
        
        $input = $args['input'];
        
        if (!$input['catalogId'] && !$input['packageId']) {
            throw new \Exception('Catalog or Package is required');
        }
        
        $existing = Favorite::where('user_id', $user->id)
            ->where(function ($q) use ($input) {
                if ($input['catalogId']) $q->where('catalog_id', $input['catalogId']);
                if ($input['packageId']) $q->where('package_id', $input['packageId']);
            })
            ->first();
        
        if ($existing) {
            $itemName = $existing->catalog?->name ?? $existing->package?->name ?? 'Item';
            $existing->delete();
            
            return [
                'Favorite' => $existing,
                'action' => 'REMOVED',
                'message' => "{$itemName} removed from Favorite",
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
        
        $Favorite = Favorite::create([
            'user_id' => $user->id,
            'catalog_id' => $input['catalogId'],
            'package_id' => $input['packageId'],
        ]);
        
        $itemName = $Favorite->catalog?->name ?? $Favorite->package?->name ?? 'Item';
        
        return [
            'Favorite' => $Favorite->load(['catalog', 'package']),
            'action' => 'ADDED',
            'message' => "{$itemName} added to Favorite",
        ];
    }

    /**
     * Add to Favorite explicitly
     */
    public function add($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            throw new \Exception('Unauthorized: Please login to manage Favorite');
        }
        
        $input = $args['input'];
        
        $existing = Favorite::where('user_id', $user->id)
            ->where(function ($q) use ($input) {
                if ($input['catalogId']) $q->where('catalog_id', $input['catalogId']);
                if ($input['packageId']) $q->where('package_id', $input['packageId']);
            })
            ->first();
        
        if ($existing) {
            return $existing->load(['catalog', 'package']);
        }
        
        return $this->toggle($root, $args, $context)['Favorite'];
    }

    /**
     * Remove from Favorite
     */
    public function remove($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            throw new \Exception('Unauthorized');
        }
        
        $Favorite = Favorite::where('id', $args['FavoriteId'])
            ->where('user_id', $user->id)
            ->firstOrFail();
        
        $Favorite->delete();
        
        return true;
    }

    /**
     * Clear entire Favorite
     */
    public function clear($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            throw new \Exception('Unauthorized');
        }
        
        Favorite::where('user_id', $user->id)->delete();
        
        return true;
    }

    /**
     * Get current user's Favorite
     */
    public function myFavorite($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            return [];
        }
        
        return Favorite::where('user_id', $user->id)
            ->with(['catalog', 'package', 'catalog.category', 'package.category'])
            ->latest()
            ->paginate($args['first'] ?? 50);
    }

    /**
     * Check if item is in Favorite
     */
    public function isInFavorite($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            return false;
        }
        
        return Favorite::where('user_id', $user->id)
            ->where(function ($q) use ($args) {
                if ($args['catalogId']) $q->where('catalog_id', $args['catalogId']);
                if ($args['packageId']) $q->where('package_id', $args['packageId']);
            })
            ->exists();
    }

    /**
     * Move Favorite item to cart
     */
    public function moveToCart($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            throw new \Exception('Unauthorized');
        }
        
        $Favorite = Favorite::where('id', $args['FavoriteId'])
            ->where('user_id', $user->id)
            ->firstOrFail();
        
        $quantity = $args['quantity'] ?? 1;
        
        // Add to cart
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
        
        // Remove from Favorite
        $Favorite->delete();
        
        return $cartItem->load(['catalog', 'package']);
    }

    /**
     * Share Favorite
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
        \Cache::put("Favorite_share_{$shareToken}", [
            'user_id' => $user->id,
            'expires_at' => $expiresAt,
        ], 30 * 24 * 60);
        
        return [
            'shareToken' => $shareToken,
            'shareUrl' => url("/shared/Favorite/{$shareToken}"),
            'expiresAt' => $expiresAt,
        ];
    }
}