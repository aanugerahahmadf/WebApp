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
                ->with(['package.category', 'package.reviews', 'catalog.category', 'catalog.reviews'])
                ->where('user_id', Auth::id());

            $wishlistItems = $query->latest()->paginate($request->get('per_page', 10));

            $items = $wishlistItems->getCollection()->map(function (Wishlist $item) use ($locale): array {
                if ($item->catalog_id && $item->catalog) {
                    $catalogData = $item->catalog->toArray();
                    $catalogData['name'] = $item->catalog->trans('name', $locale);
                    $catalogData['description'] = $item->catalog->trans('description', $locale);

                    return [
                        'id' => $item->id,
                        'resource_type' => 'catalog',
                        'catalog_id' => $item->catalog_id,
                        'created_at' => $item->created_at?->toIso8601String(),
                        ...$catalogData,
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
                'error' => null,
            ], 500);
        }
    }

    public function toggle(Request $request)
    {
        try {
            $request->validate([
                'package_id' => 'nullable|exists:packages,id',
                'catalog_id' => 'nullable|exists:catalogs,id',
            ]);

            $packageId = $request->input('package_id');
            $catalogId = $request->input('catalog_id');

            if (! $packageId && ! $catalogId) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Pilih paket atau katalog untuk wishlist'),
                ], 422);
            }

            $existingWishlist = Wishlist::where('user_id', Auth::id())
                ->where(function ($query) use ($packageId, $catalogId): void {
                    if ($packageId) {
                        $query->where('package_id', $packageId);
                    }
                    if ($catalogId) {
                        $query->orWhere('catalog_id', $catalogId);
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
                'catalog_id' => $catalogId,
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
                'error' => null,
            ], 500);
        }
    }

    public function isInWishlist(Request $request, $packageId)
    {
        try {
            $catalogId = $request->query('catalog_id');

            $exists = Wishlist::where('user_id', Auth::id())
                ->where(function ($query) use ($packageId, $catalogId): void {
                    $query->where('package_id', $packageId);
                    if ($catalogId) {
                        $query->orWhere('catalog_id', $catalogId);
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
                'error' => null,
            ], 500);
        }
    }

    public function removeFromWishlist(Request $request, $packageId)
    {
        try {
            $catalogId = $request->query('catalog_id');

            $wishlist = Wishlist::where('user_id', Auth::id())
                ->where(function ($query) use ($packageId, $catalogId): void {
                    $query->where('package_id', $packageId);
                    if ($catalogId) {
                        $query->orWhere('catalog_id', $catalogId);
                    }
                })
                ->first();

            if (! $wishlist) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Item tidak ditemukan di wishlist'),
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
                'error' => null,
            ], 500);
        }
    }

    public function bulkAdd(Request $request)
    {
        try {
            $request->validate([
                'package_ids' => 'required_without:catalog_ids|array',
                'catalog_ids' => 'required_without:package_ids|array',
                'package_ids.*' => 'exists:packages,id',
                'catalog_ids.*' => 'exists:catalogs,id',
            ]);

            $added = 0;

            foreach ((array) $request->package_ids as $packageId) {
                Wishlist::firstOrCreate([
                    'user_id' => Auth::id(),
                    'package_id' => $packageId,
                ]);
                $added++;
            }

            foreach ((array) $request->catalog_ids as $catalogId) {
                Wishlist::firstOrCreate([
                    'user_id' => Auth::id(),
                    'catalog_id' => $catalogId,
                ]);
                $added++;
            }

            return response()->json([
                'status' => 'success',
                'message' => __('Berhasil menambahkan :count item ke wishlist', ['count' => $added]),
                'added' => $added,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal menambahkan ke wishlist'),
                'error' => null,
            ], 500);
        }
    }
}