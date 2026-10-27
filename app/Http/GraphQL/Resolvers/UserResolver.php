<?php

namespace App\Http\GraphQL\Resolvers;

use App\Models\User\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class UserResolver
{
    /**
     * Get current authenticated user
     */
    public function me($root, array $args, GraphQLContext $context)
    {
        return $context->user();
    }

    /**
     * Get user profile with stats
     */
    public function profile($root, array $args, GraphQLContext $context)
    {
        $currentUser = $context->user();
        $requestedId = $args['id'] ?? null;
        if (! $currentUser || ($requestedId !== null
            && (int) $requestedId !== (int) $currentUser->getKey()
            && ! $currentUser->hasRole('admin'))) {
            throw new \Exception('Unauthorized');
        }
        $user = $requestedId ? User::findOrFail($requestedId) : $currentUser;
        
        if (!$user) {
            throw new \Exception('User not found');
        }

        $orderCount = $user->orders()->count();
        $reviewCount = $user->reviews()->count();
        $wishlistCount = $user->wishlist()->count();
        $totalSpent = $user->orders()
            ->where('payment_status', 'paid')
            ->sum('total_price');

        // Get favorite category
        $favoriteCategory = DB::table('orders')
            ->join('catalogs', 'orders.catalog_id', '=', 'catalogs.id')
            ->join('categories', 'catalogs.category_id', '=', 'categories.id')
            ->where('orders.user_id', $user->id)
            ->where('orders.payment_status', 'paid')
            ->select('categories.*', DB::raw('count(*) as order_count'))
            ->groupBy('categories.id')
            ->orderByDesc('order_count')
            ->first();

        return [
            'user' => $user,
            'orderCount' => $orderCount,
            'reviewCount' => $reviewCount,
            'wishlistCount' => $wishlistCount,
            'totalSpent' => $totalSpent,
            'favoriteCategory' => $favoriteCategory,
            'joinedAt' => $user->created_at,
        ];
    }

    /**
     * Get user activity/history
     */
    public function activity($root, array $args, GraphQLContext $context)
    {
        $currentUser = $context->user();
        $userId = $args['userId'];
        if (! $currentUser || ((int) $userId !== (int) $currentUser->getKey()
            && ! $currentUser->hasRole('admin'))) {
            throw new \Exception('Unauthorized');
        }
        $first = $args['first'] ?? 20;

        $activities = collect();

        // Recent orders
        $orders = \App\Models\Order\Order::where('user_id', $userId)
            ->latest()
            ->take($first)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => 'order_' . $order->id,
                    'type' => 'ORDER',
                    'description' => 'Order #' . $order->order_number . ' ' . ucfirst($order->status),
                    'metadata' => [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'status' => $order->status,
                        'total' => $order->total_price,
                    ],
                    'createdAt' => $order->created_at,
                ];
            });

        // Recent reviews
        $reviews = \App\Models\Review\Review::where('user_id', $userId)
            ->latest()
            ->take($first)
            ->get()
            ->map(function ($review) {
                return [
                    'id' => 'review_' . $review->id,
                    'type' => 'REVIEW',
                    'description' => 'Reviewed ' . ($review->catalog?->name ?? $review->package?->name),
                    'metadata' => [
                        'review_id' => $review->id,
                        'rating' => $review->rating,
                        'catalog_id' => $review->catalog_id,
                        'package_id' => $review->package_id,
                    ],
                    'createdAt' => $review->created_at,
                ];
            });

        // Recent wishlist additions
        $wishlist = \App\Models\Wishlist\Wishlist::where('user_id', $userId)
            ->latest()
            ->take($first)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => 'wishlist_' . $item->id,
                    'type' => 'WISHLIST',
                    'description' => 'Added to wishlist: ' . ($item->catalog?->name ?? $item->package?->name),
                    'metadata' => [
                        'wishlist_id' => $item->id,
                        'catalog_id' => $item->catalog_id,
                        'package_id' => $item->package_id,
                    ],
                    'createdAt' => $item->created_at,
                ];
            });

        $activities = $activities->concat($orders)->concat($reviews)->concat($wishlist)
            ->sortByDesc('createdAt')
            ->take($first)
            ->values();

        return $activities;
    }
}
