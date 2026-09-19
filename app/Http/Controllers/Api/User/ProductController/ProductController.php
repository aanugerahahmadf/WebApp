<?php

namespace App\Http\Controllers\Api\User\ProductController;

use App\Http\Controllers\Controller;
use App\Models\Product\Product;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    protected function localizedProduct($product)
    {
        $locale = app()->getLocale();
        $product->name = $product->trans('name', $locale);
        $product->description = $product->trans('description', $locale);
        return $product;
    }

    protected function localizedProducts($products)
    {
        return $products->map(function ($p) {
            return $this->localizedProduct($p);
        });
    }

    public function index(Request $request)
    {
        try {
            $query = Product::with(['vendor', 'category', 'reviews', 'media']);

            if ($request->filled('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            if ($request->filled('organizer_id')) {
                $query->where('vendor_id', $request->organizer_id);
            }

            if ($request->filled('theme')) {
                $query->where('theme', 'like', '%'.$request->theme.'%');
            }

            if ($request->filled('min_price')) {
                $query->where('price', '>=', $request->min_price);
            }

            if ($request->filled('max_price')) {
                $query->where('price', '<=', $request->max_price);
            }

            if ($request->filled('search')) {
                $searchTerm = $request->search;
                $query->where(function ($q) use ($searchTerm): void {
                    $q->where('name', 'like', '%'.$searchTerm.'%')
                        ->orWhere('description', 'like', '%'.$searchTerm.'%');
                });
            }

            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $allowedSortFields = ['name', 'price', 'created_at', 'discount_price'];
            if (! in_array($sortBy, $allowedSortFields)) {
                $sortBy = 'created_at';
            }
            if (! in_array(strtolower($sortDirection), ['asc', 'desc'])) {
                $sortDirection = 'desc';
            }

            $query->orderBy($sortBy, $sortDirection);

            $perPage = $request->get('per_page', 'all');

            if ($perPage === 'all') {
                $perPage = 100;
            }

            $perPage = min((int) $perPage, 100);

            $products = $query->paginate($perPage, ['*']);

            return response()->json([
                'status' => 'success',
                'data' => $this->localizedProducts(collect($products->items())),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'has_more_pages' => $products->hasMorePages(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('[Product] index error: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengambil produk'),
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $product = Product::with([
                'vendor:id,store_name,logo,is_active',
                'category:id,name,description,name_translations,description_translations',
                'reviews' => function ($query): void {
                    $query->with('user:id,full_name,avatar_url')->latest()->limit(5);
                },
                'media',
            ])->findOrFail($id, ['*']);

            return response()->json([
                'status' => 'success',
                'data' => $this->localizedProduct($product),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Produk tidak ditemukan'),
            ], 404);
        } catch (\Exception $e) {
            Log::error('[Product] show error: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengambil detail produk'),
            ], 500);
        }
    }

    public function featured(Request $request)
    {
        $products = Product::with(['vendor', 'category', 'reviews', 'media'])
            ->where('is_featured', true)
            ->paginate($request->get('per_page', 10), ['*']);

        return response()->json([
            'status' => 'success',
            'data' => $this->localizedProducts(collect($products->items())),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'has_more_pages' => $products->hasMorePages(),
            ],
        ]);
    }

    public function onSale(Request $request)
    {
        $products = Product::with(['vendor', 'category', 'reviews', 'media'])
            ->whereNotNull('discount_price')
            ->where('discount_price', '<', 'price')
            ->paginate($request->get('per_page', 10), ['*']);

        return response()->json([
            'status' => 'success',
            'data' => $this->localizedProducts(collect($products->items())),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'has_more_pages' => $products->hasMorePages(),
            ],
        ]);
    }
}
