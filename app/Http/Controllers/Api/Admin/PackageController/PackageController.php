<?php

namespace App\Http\Controllers\Api\Admin\PackageController;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Catalog;
use App\Models\Package\Package;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Tier management.
 *
 * A Package is never a standalone catalog: it is a pricing/description tier that
 * belongs to exactly one Catalog (Standar / Sedang / Pelaminan / Tunangan /
 * Lengkap). Photos live on the Catalog, so there is deliberately no image
 * endpoint here.
 */
class PackageController extends Controller
{
    /** Shared validation rules; `catalog_id` is what turns a row into a tier. */
    protected const RULES = [
        'catalog_id' => 'required|integer|exists:catalogs,id',
        'sort_order' => 'nullable|integer|min:0|max:99',
        'name' => 'required|string|max:255',
        'description' => 'nullable|string',
        'price' => 'required|numeric|min:0',
        'discount_price' => 'nullable|numeric|min:0',
        'stock' => 'integer|min:0',
        'is_active' => 'boolean',
        'features' => 'nullable|array',
        'features.*' => 'nullable|string|max:255',
    ];

    public function index(Request $request): JsonResponse
    {
        try {
            $query = Package::with(['catalog:id,name,slug']);

            if ($request->filled('catalog_id')) {
                $query->where('catalog_id', $request->catalog_id);
            }

            if ($request->filled('search')) {
                $s = $request->search;
                $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$s}%")
                    ->orWhere('description', 'like', "%{$s}%"));
            }

            if ($request->filled('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $packages = $query
                ->orderByRaw('sort_order IS NULL')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->paginate(min((int) $request->get('per_page', 50), 200));

            return response()->json([
                'status' => 'success',
                'data' => $packages->items(),
                'pagination' => [
                    'current_page' => $packages->currentPage(),
                    'last_page' => $packages->lastPage(),
                    'per_page' => $packages->perPage(),
                    'total' => $packages->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengambil pilihan paket'),
                'error' => null,
            ], 500);
        }
    }

    /** Every tier of one catalog, in picker order. */
    public function tiers(int $catalogId): JsonResponse
    {
        try {
            $catalog = Catalog::with('tiers')->findOrFail($catalogId);

            return response()->json([
                'status' => 'success',
                'data' => $catalog->tiers,
                'meta' => [
                    'catalog_id' => (int) $catalog->id,
                    'catalog_name' => $catalog->name,
                    'total' => $catalog->tiers->count(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Katalog tidak ditemukan'),
            ], 404);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $package = Package::with(['catalog:id,name,slug'])->findOrFail($id);

            return response()->json(['status' => 'success', 'data' => $package]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Pilihan paket tidak ditemukan'),
            ], 404);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate(self::RULES);
            $validated['slug'] = Str::slug($validated['name']).'-'.Str::random(5);

            $package = Package::create($validated);

            return response()->json([
                'status' => 'success',
                'message' => __('Pilihan paket berhasil dibuat'),
                'data' => ['id' => $package->id],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal membuat pilihan paket'),
                'error' => null,
            ], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $package = Package::findOrFail($id);

            $validated = $request->validate([
                ...self::RULES,
                'catalog_id' => 'sometimes|integer|exists:catalogs,id',
                'name' => 'sometimes|string|max:255',
                'price' => 'sometimes|numeric|min:0',
                'stock' => 'sometimes|integer|min:0',
            ]);

            if (! empty($validated['name']) && $validated['name'] !== $package->name) {
                $validated['slug'] = Str::slug($validated['name']).'-'.Str::random(5);
            }

            $package->update($validated);

            return response()->json([
                'status' => 'success',
                'message' => __('Pilihan paket berhasil diperbarui'),
            ]);
        } catch (ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal memperbarui pilihan paket'),
                'error' => null,
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            Package::findOrFail($id)->delete();

            return response()->json([
                'status' => 'success',
                'message' => __('Pilihan paket berhasil dihapus'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal menghapus pilihan paket'),
            ], 500);
        }
    }
}
