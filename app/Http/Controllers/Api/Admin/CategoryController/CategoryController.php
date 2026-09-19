<?php

namespace App\Http\Controllers\Api\Admin\CategoryController;

use App\Http\Controllers\Controller;
use App\Models\Category\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $type = $request->type;
            $query = Category::withCount($type === 'product' ? 'categoryProducts' : 'categoryPackages');
            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }
            $categories = $query->orderBy('name')->get();

            return response()->json(['status' => 'success', 'data' => $categories]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal mengambil kategori'), 'error' => $e->getMessage()], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $category = Category::findOrFail($id);
            $category->loadCount($category->type === 'product' ? 'categoryProducts' : 'categoryPackages');

            return response()->json(['status' => 'success', 'data' => $category]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Kategori tidak ditemukan')], 404);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'type' => 'nullable|string|in:package,product',
                'slug' => 'nullable|string|max:255',
                'icon' => 'nullable|string|max:255',
                'color' => 'nullable|string|max:255',
                'description' => 'nullable|string',
            ]);

            if (blank($validated['slug'] ?? null)) {
                $validated['slug'] = Str::slug($validated['name']);
            }
            $validated['type'] ??= 'package';
            $category = Category::create($validated);

            return response()->json([
                'status' => 'success',
                'message' => __('Kategori berhasil dibuat'),
                'data' => ['id' => $category->id],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal membuat kategori'), 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $category = Category::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'type' => 'nullable|string|in:package,product',
                'slug' => 'nullable|string|max:255',
                'icon' => 'nullable|string|max:255',
                'color' => 'nullable|string|max:255',
                'description' => 'nullable|string',
            ]);

            if (empty($validated['slug']) && ! empty($validated['name'])) {
                $validated['slug'] = Str::slug($validated['name']);
            }

            $category->update($validated);

            return response()->json(['status' => 'success', 'message' => __('Kategori berhasil diperbarui')]);
        } catch (ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal memperbarui kategori'), 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $category = Category::findOrFail($id);
            if ($category->categoryPackages()->count() > 0 || $category->categoryProducts()->count() > 0) {
                return response()->json(['status' => 'error', 'message' => __('Kategori memiliki data terkait, tidak dapat dihapus')], 422);
            }
            $category->delete();

            return response()->json(['status' => 'success', 'message' => __('Kategori berhasil dihapus')]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal menghapus kategori')], 500);
        }
    }
}
