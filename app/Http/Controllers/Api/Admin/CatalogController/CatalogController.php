<?php

namespace App\Http\Controllers\Api\Admin\CatalogController;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Catalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CatalogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Catalog::with(['category:id,name', 'media', 'discounts']);

            if ($request->filled('search')) {
                $s = $request->search;
                $query->where(function ($q) use ($s): void {
                    $q->where('name', 'like', "%{$s}%")
                        ->orWhere('description', 'like', "%{$s}%");
                });
            }
            if ($request->filled('category_id')) {
                $query->where('category_id', $request->category_id);
            }
            if ($request->filled('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $catalogs = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'status' => 'success',
                'data' => $catalogs->items(),
                'pagination' => [
                    'current_page' => $catalogs->currentPage(),
                    'last_page' => $catalogs->lastPage(),
                    'per_page' => $catalogs->perPage(),
                    'total' => $catalogs->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal mengambil katalog'), 'error' => null], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $catalog = Catalog::with(['category', 'media', 'reviews', 'discounts'])->findOrFail($id);

            return response()->json(['status' => 'success', 'data' => $catalog]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Katalog tidak ditemukan')], 404);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'category_id' => 'nullable|exists:categories,id',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'price' => 'required|numeric|min:0',
                'discount_price' => 'nullable|numeric|min:0',
                'stock' => 'integer|min:0',
                'is_active' => 'boolean',
                'is_featured' => 'boolean',
                'features' => 'nullable|array',
                'theme' => 'nullable|string|max:255',
                'color' => 'nullable|string|max:255',
                'min_capacity' => 'nullable|integer|min:0',
                'max_capacity' => 'nullable|integer|min:0',
            ]);

            $validated['slug'] = Str::slug($validated['name']).'-'.Str::random(5);
            $catalog = Catalog::create($validated);

            return response()->json([
                'status' => 'success',
                'message' => __('Katalog berhasil dibuat'),
                'data' => ['id' => $catalog->id],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal membuat katalog'), 'error' => null], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $catalog = Catalog::findOrFail($id);

            $validated = $request->validate([
                'category_id' => 'nullable|exists:categories,id',
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'price' => 'sometimes|numeric|min:0',
                'discount_price' => 'nullable|numeric|min:0',
                'stock' => 'sometimes|integer|min:0',
                'is_active' => 'boolean',
                'is_featured' => 'boolean',
                'features' => 'nullable|array',
                'theme' => 'nullable|string|max:255',
                'color' => 'nullable|string|max:255',
                'min_capacity' => 'nullable|integer|min:0',
                'max_capacity' => 'nullable|integer|min:0',
            ]);

            if (! empty($validated['name']) && $validated['name'] !== $catalog->name) {
                $validated['slug'] = Str::slug($validated['name']).'-'.Str::random(5);
            }

            $catalog->update($validated);

            return response()->json(['status' => 'success', 'message' => __('Katalog berhasil diperbarui')]);
        } catch (ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal memperbarui katalog'), 'error' => null], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $catalog = Catalog::findOrFail($id);
            $catalog->delete();

            return response()->json(['status' => 'success', 'message' => __('Katalog berhasil dihapus')]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal menghapus katalog')], 500);
        }
    }

    public function uploadImage(Request $request, int $id): JsonResponse
    {
        try {
            $catalog = Catalog::findOrFail($id);
            $request->validate(['image' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120']);

            $catalog->clearMediaCollection('catalog_image');
            $catalog->addMediaFromRequest('image')->toMediaCollection('catalog_image');

            return response()->json([
                'status' => 'success',
                'message' => __('Gambar berhasil diunggah'),
                'data' => ['image_url' => $catalog->image_url],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal mengunggah gambar'), 'error' => null], 500);
        }
    }
}
