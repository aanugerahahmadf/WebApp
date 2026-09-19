<?php

namespace App\Http\Controllers\Api\Admin\ProductController;

use App\Http\Controllers\Controller;
use App\Models\Product\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Product::with(['category:id,name', 'vendor:id,store_name', 'media', 'discounts']);

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

            $products = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'status' => 'success',
                'data' => $products->items(),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal mengambil produk'), 'error' => $e->getMessage()], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $product = Product::with(['category', 'vendor:id,store_name', 'media', 'reviews', 'discounts'])->findOrFail($id);

            return response()->json(['status' => 'success', 'data' => $product]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Produk tidak ditemukan')], 404);
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
            $product = Product::create($validated);

            return response()->json([
                'status' => 'success',
                'message' => __('Produk berhasil dibuat'),
                'data' => ['id' => $product->id],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal membuat produk'), 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $product = Product::findOrFail($id);

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

            if (! empty($validated['name']) && $validated['name'] !== $product->name) {
                $validated['slug'] = Str::slug($validated['name']).'-'.Str::random(5);
            }

            $product->update($validated);

            return response()->json(['status' => 'success', 'message' => __('Produk berhasil diperbarui')]);
        } catch (ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal memperbarui produk'), 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $product = Product::findOrFail($id);
            $product->delete();

            return response()->json(['status' => 'success', 'message' => __('Produk berhasil dihapus')]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal menghapus produk')], 500);
        }
    }

    public function uploadImage(Request $request, int $id): JsonResponse
    {
        try {
            $product = Product::findOrFail($id);
            $request->validate(['image' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120']);

            $product->clearMediaCollection('product_image');
            $product->addMediaFromRequest('image')->toMediaCollection('product_image');

            return response()->json([
                'status' => 'success',
                'message' => __('Gambar berhasil diunggah'),
                'data' => ['image_url' => $product->image_url],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal mengunggah gambar'), 'error' => $e->getMessage()], 500);
        }
    }
}
