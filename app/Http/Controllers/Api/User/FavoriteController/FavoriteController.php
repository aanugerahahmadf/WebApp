<?php

namespace App\Http\Controllers\Api\User\FavoriteController;

use App\Http\Controllers\Controller;
use App\Models\Package\Package;
use App\Models\Favorite\Favorite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    public function index(Request $request)
    {
        try {
            $locale = app()->getLocale();
            $query = Favorite::query()
                ->with(['package.category', 'package.reviews', 'catalog.category', 'catalog.reviews'])
                ->where('user_id', Auth::id());

            $FavoriteItems = $query->latest()->paginate($request->get('per_page', 10));

            $items = $FavoriteItems->getCollection()->map(function (Favorite $item) use ($locale): array {
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
                        'resource_type' => 'tier',
                        'legacy_resource_type' => 'package',
                        'tier_id' => $item->package_id,
                        'package_id' => $item->package_id,
                        'created_at' => $item->created_at?->toIso8601String(),
                        ...$packageData,
                    ];
                }

                return [
                    'id' => $item->id,
                    'resource_type' => 'tier',
                    'legacy_resource_type' => 'package',
                    'created_at' => $item->created_at?->toIso8601String(),
                ];
            })->values();

            return response()->json([
                'status' => 'success',
                'data' => $items,
                'pagination' => [
                    'current_page' => $FavoriteItems->currentPage(),
                    'last_page' => $FavoriteItems->lastPage(),
                    'per_page' => $FavoriteItems->perPage(),
                    'total' => $FavoriteItems->total(),
                    'has_more_pages' => $FavoriteItems->hasMorePages(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengambil Favorite'),
                'error' => null,
            ], 500);
        }
    }

    public function toggle(Request $request)
    {
        try {
            if ($request->filled('tier_id') && ! $request->filled('package_id')) {
                $request->merge(['package_id' => $request->input('tier_id')]);
            }
            $request->validate([
                'package_id' => 'nullable|exists:packages,id',
                'catalog_id' => 'nullable|exists:catalogs,id',
            ]);

            $packageId = $request->input('package_id');
            $catalogId = $request->input('catalog_id');

            if ($packageId && $catalogId && ! Package::whereKey($packageId)->where('catalog_id', $catalogId)->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Tier tidak termasuk dalam katalog yang dipilih'),
                ], 422);
            }

            if (! $packageId && ! $catalogId) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Pilih paket atau katalog untuk Favorite'),
                ], 422);
            }

            $existingFavorite = Favorite::where('user_id', Auth::id())
                ->where(function ($query) use ($packageId, $catalogId): void {
                    if ($packageId) {
                        $query->where('package_id', $packageId);
                    }
                    if ($catalogId) {
                        $query->orWhere('catalog_id', $catalogId);
                    }
                })
                ->first(['*']);

            if ($existingFavorite) {
                $existingFavorite->delete();

                return response()->json([
                    'status' => 'success',
                    'message' => __('Dihapus dari Favorite'),
                    'in_Favorite' => false,
                ]);
            }

            $Favorite = Favorite::create([
                'user_id' => Auth::id(),
                'package_id' => $packageId,
                'catalog_id' => $catalogId,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => __('Ditambahkan ke Favorite'),
                'in_Favorite' => true,
                'data' => $Favorite,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal memperbarui Favorite'),
                'error' => null,
            ], 500);
        }
    }

    public function isInFavorite(Request $request, $packageId)
    {
        try {
            $catalogId = $request->query('catalog_id');

            $exists = Favorite::where('user_id', Auth::id())
                ->where(function ($query) use ($packageId, $catalogId): void {
                    $query->where('package_id', $packageId);
                    if ($catalogId) {
                        $query->orWhere('catalog_id', $catalogId);
                    }
                })
                ->exists();

            return response()->json([
                'status' => 'success',
                'in_Favorite' => $exists,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal memeriksa status Favorite'),
                'error' => null,
            ], 500);
        }
    }

    public function removeFromFavorite(Request $request, $packageId)
    {
        try {
            $catalogId = $request->query('catalog_id');

            $Favorite = Favorite::where('user_id', Auth::id())
                ->where(function ($query) use ($packageId, $catalogId): void {
                    $query->where('package_id', $packageId);
                    if ($catalogId) {
                        $query->orWhere('catalog_id', $catalogId);
                    }
                })
                ->first();

            if (! $Favorite) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Item tidak ditemukan di Favorite'),
                ], 404);
            }

            $Favorite->delete();

            return response()->json([
                'status' => 'success',
                'message' => __('Dihapus dari Favorite'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal menghapus dari Favorite'),
                'error' => null,
            ], 500);
        }
    }

    public function bulkAdd(Request $request)
    {
        try {
            if ($request->filled('tier_ids') && ! $request->filled('package_ids')) {
                $request->merge(['package_ids' => $request->input('tier_ids')]);
            }
            $request->validate([
                'package_ids' => 'required_without:catalog_ids|array',
                'catalog_ids' => 'required_without:package_ids|array',
                'package_ids.*' => 'exists:packages,id',
                'catalog_ids.*' => 'exists:catalogs,id',
            ]);

            $added = 0;

            foreach ((array) $request->package_ids as $packageId) {
                Favorite::firstOrCreate([
                    'user_id' => Auth::id(),
                    'package_id' => $packageId,
                ]);
                $added++;
            }

            foreach ((array) $request->catalog_ids as $catalogId) {
                Favorite::firstOrCreate([
                    'user_id' => Auth::id(),
                    'catalog_id' => $catalogId,
                ]);
                $added++;
            }

            return response()->json([
                'status' => 'success',
                'message' => __('Berhasil menambahkan :count item ke Favorite', ['count' => $added]),
                'added' => $added,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal menambahkan ke Favorite'),
                'error' => null,
            ], 500);
        }
    }
}
