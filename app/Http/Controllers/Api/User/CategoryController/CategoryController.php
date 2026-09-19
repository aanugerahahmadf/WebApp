<?php

namespace App\Http\Controllers\Api\User\CategoryController;

use App\Http\Controllers\Controller;
use App\Models\Category\Category;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CategoryController extends Controller
{
    /**
     * Get all categories with pagination
     */
    public function index(Request $request)
    {
        try {
            $type = $request->type;
            $query = Category::withCount($type === 'product' ? 'categoryProducts' : 'categoryPackages');

            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }

            if ($request->filled('search')) {
                $query->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('description', 'like', '%'.$request->search.'%');
            }

            $sortBy = $request->get('sort_by', 'name');
            $sortDirection = $request->get('sort_direction', 'asc');

            $allowedSortFields = ['name', 'created_at', 'category_packages_count', 'category_products_count'];
            if (! in_array($sortBy, $allowedSortFields)) {
                $sortBy = 'name';
            }

            $allowedDirections = ['asc', 'desc'];
            if (! in_array(strtolower($sortDirection), $allowedDirections)) {
                $sortDirection = 'asc';
            }

            $query->orderBy($sortBy, $sortDirection);

            $categories = $query->paginate($request->get('per_page', 10), ['*']);

            // Apply translations
            $locale = app()->getLocale();
            $data = collect($categories->items())->map(function ($cat) use ($locale) {
                $cat->name = $cat->trans('name', $locale);
                $cat->description = $cat->trans('description', $locale);
                return $cat;
            });

            return response()->json([
                'status' => 'success',
                'data' => $data,
                'pagination' => [
                    'current_page' => $categories->currentPage(),
                    'last_page' => $categories->lastPage(),
                    'per_page' => $categories->perPage(),
                    'total' => $categories->total(),
                    'has_more_pages' => $categories->hasMorePages(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('[Category] index error: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengambil kategori'),
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $category = Category::findOrFail($id, ['*']);
            if ($category->type === 'product') {
                $category->load(['categoryProducts' => function ($query): void {
                    $query->with(['vendor', 'reviews'])->limit(10);
                }])->loadCount('categoryProducts');
            } else {
                $category->load(['categoryPackages' => function ($query): void {
                    $query->with(['vendor', 'reviews'])->limit(10);
                }])->loadCount('categoryPackages');
            }

            $locale = app()->getLocale();
            $category->name = $category->trans('name', $locale);
            $category->description = $category->trans('description', $locale);

            return response()->json([
                'status' => 'success',
                'data' => $category,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Kategori tidak ditemukan'),
            ], 404);
        } catch (\Exception $e) {
            Log::error('[Category] show error: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengambil detail kategori'),
            ], 500);
        }
    }

    public function withTopPackages(Request $request)
    {
        try {
            $categories = Category::with(['categoryPackages' => function ($query) use ($request): void {
                $query->with(['vendor', 'reviews'])
                    ->orderBy('price', 'asc')
                    ->limit($request->get('packages_per_category', 5));
            }])->withCount('categoryPackages')->get(['*']);

            $locale = app()->getLocale();
            $data = $categories->map(function ($cat) use ($locale) {
                $cat->name = $cat->trans('name', $locale);
                $cat->description = $cat->trans('description', $locale);
                return $cat;
            });

            return response()->json([
                'status' => 'success',
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('[Category] withTopPackages error: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengambil kategori dan paket'),
            ], 500);
        }
    }
}
