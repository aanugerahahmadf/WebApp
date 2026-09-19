<?php

namespace App\Http\Controllers\Api\User\WishlistController;

use App\Http\Controllers\Controller;
use App\Models\Wishlist\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        try {
            $locale = app()->getLocale();
            $query = Wishlist::query()
                ->with(['package.vendor', 'package.category', 'package.reviews', 'product.category', 'product.reviews'])
                ->where('user_id', Auth::id());

            $wishlistItems = $query->latest()->paginate($request->get('per_page', 10));

            $items = $wishlistItems->getCollection()->map(function (Wishlist $item) use ($locale): array {
                if ($item->product_id && $item->product) {
                    $productData = $item->product->toArray();
                    $productData['name'] = $item->product->trans('name', $locale);
                    $productData['description'] = $item->product->trans('description', $locale);

                    return [
                        'id' => $item->id,
                        'resource_type' => 'product',
                        'product_id' => $item->product_id,
                        'created_at' => $item->created_at?->toIso8601String(),
                        ...$productData,
                    ];
                }

                if ($item->package_id && $item->package) {
                    $packageData = $item->package->toArray();
                    $packageData['name'] = $item->package->trans('name', $locale);
                    $packageData['description'] = $item->package->trans('description', $locale);

                    return [
                        'id' => $item->id,
                        'resource_type' => 'package',
                        'package_id' => $item->package_id,
                        'created_at' => $item->created_at?->toIso8601String(),
                        ...$packageData,
                    ];
                }

                return [
                    'id' => $item->id,
                    'resource_type' => 'package',
                    'created_at' => $item->created_at?->toIso8601String(),
                ];
            })->values();

            return response()->json([
                'status' => 'success',
                'data' => $items,
                'pagination' => [
                    'current_page' => $wishlistItems->currentPage(),
                    'last_page' => $wishlistItems->lastPage(),
                    'per_page' => $wishlistItems->perPage(),
                    'total' => $wishlistItems->total(),
                    'has_more_pages' => $wishlistItems->hasMorePages(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengambil wishlist'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function toggle(Request $request)
    {
        try {
            $request->validate([
                'package_id' => 'nullable|exists:packages,id',
                'product_id' => 'nullable|exists:products,id',
            ]);

            $packageId = $request->input('package_id');
            $productId = $request->input('product_id');

            if (! $packageId && ! $productId) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Pilih paket atau produk untuk wishlist'),
                ], 422);
            }

            $existingWishlist = Wishlist::where('user_id', Auth::id())
                ->where(function ($query) use ($packageId, $productId): void {
                    if ($packageId) {
                        $query->where('package_id', $packageId);
                    }
                    if ($productId) {
                        $query->orWhere('product_id', $productId);
                    }
                })
                ->first(['*']);

            if ($existingWishlist) {
                $existingWishlist->delete();

                return response()->json([
                    'status' => 'success',
                    'message' => __('Dihapus dari wishlist'),
                    'in_wishlist' => false,
                ]);
            }

            $wishlist = Wishlist::create([
                'user_id' => Auth::id(),
                'package_id' => $packageId,
                'product_id' => $productId,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => __('Ditambahkan ke wishlist'),
                'in_wishlist' => true,
                'data' => $wishlist,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal memperbarui wishlist'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function isInWishlist(Request $request, $packageId)
    {
        try {
            $productId = $request->query('product_id');

            $exists = Wishlist::where('user_id', Auth::id())
                ->where(function ($query) use ($packageId, $productId): void {
                    $query->where('package_id', $packageId);
                    if ($productId) {
                        $query->orWhere('product_id', $productId);
                    }
                })
                ->exists();

            return response()->json([
                'status' => 'success',
                'in_wishlist' => $exists,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal memeriksa status wishlist'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function bulkAdd(Request $request)
    {
        try {
            $request->validate([
                'package_ids' => 'required_without:product_ids|array',
                'package_ids.*' => 'exists:packages,id',
                'product_ids' => 'required_without:package_ids|array',
                'product_ids.*' => 'exists:products,id',
            ]);

            $addedCount = 0;
            $skippedIds = [];

            foreach ((array) $request->package_ids as $packageId) {
                $existing = Wishlist::where('user_id', Auth::id())
                    ->where('package_id', $packageId)
                    ->first(['*']);

                if (! $existing) {
                    Wishlist::create([
                        'user_id' => Auth::id(),
                        'package_id' => $packageId,
                    ]);
                    $addedCount++;
                } else {
                    $skippedIds[] = $packageId;
                }
            }

            foreach ((array) $request->product_ids as $productId) {
                $existing = Wishlist::where('user_id', Auth::id())
                    ->where('product_id', $productId)
                    ->first(['*']);

                if (! $existing) {
                    Wishlist::create([
                        'user_id' => Auth::id(),
                        'product_id' => $productId,
                    ]);
                    $addedCount++;
                } else {
                    $skippedIds[] = $productId;
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => $addedCount.' '.__('item ditambahkan ke wishlist'),
                'added_count' => $addedCount,
                'skipped_count' => count($skippedIds),
                'skipped_ids' => $skippedIds,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal menambahkan item ke wishlist'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function removeFromWishlist(Request $request, $packageId)
    {
        try {
            $productId = $request->query('product_id');

            $wishlist = Wishlist::where('user_id', Auth::id())
                ->where(function ($query) use ($packageId, $productId): void {
                    $query->where('package_id', $packageId);
                    if ($productId) {
                        $query->orWhere('product_id', $productId);
                    }
                })
                ->first(['*']);

            if (! $wishlist) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Item tidak ditemukan dalam wishlist'),
                ], 404);
            }

            $wishlist->delete();

            return response()->json([
                'status' => 'success',
                'message' => __('Dihapus dari wishlist'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal menghapus dari wishlist'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
