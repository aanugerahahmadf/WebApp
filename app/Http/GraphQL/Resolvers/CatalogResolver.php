<?php

namespace App\Http\GraphQL\Resolvers;

use App\Models\Catalog\Catalog;
use App\Models\Category\Category;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class CatalogResolver
{
    /**
     * Get featured catalogs
     */
    public function featuredCatalogs($root, array $args, GraphQLContext $context)
    {
        $first = $args['first'] ?? 10;
        
        return Catalog::where('is_featured', true)
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->with('category')
            ->latest()
            ->take($first)
            ->get();
    }

    /**
     * Get on-sale catalogs
     */
    public function onSaleCatalogs($root, array $args, GraphQLContext $context)
    {
        $first = $args['first'] ?? 10;
        
        return Catalog::where('discount_price', '>', 0)
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->with('category')
            ->latest()
            ->take($first)
            ->get();
    }

    /**
     * Get catalogs by category
     */
    public function byCategory($root, array $args, GraphQLContext $context)
    {
        $categoryId = $args['categoryId'];
        $first = $args['first'] ?? 10;
        
        return Catalog::where('category_id', $categoryId)
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->with('category')
            ->latest()
            ->take($first)
            ->get();
    }

    /**
     * Get related catalogs
     */
    public function related($root, array $args, GraphQLContext $context)
    {
        $catalogId = $args['catalogId'];
        $first = $args['first'] ?? 5;
        
        $catalog = Catalog::findOrFail($catalogId);
        
        return Catalog::where('category_id', $catalog->category_id)
            ->where('id', '!=', $catalogId)
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->with('category')
            ->inRandomOrder()
            ->take($first)
            ->get();
    }

    /**
     * Search catalogs
     */
    public function search($root, array $args, GraphQLContext $context)
    {
        $query = $args['query'];
        $first = $args['first'] ?? 10;
        
        return Catalog::where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%")
                  ->orWhere('theme', 'like', "%{$query}%")
                  ->orWhere('color', 'like', "%{$query}%");
            })
            ->with('category')
            ->latest()
            ->take($first)
            ->get();
    }

    /**
     * Get catalog statistics (admin)
     */
    public function stats($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        if (! $user || ! $user->hasRole('admin')) {
            throw new \Exception('Unauthorized');
        }

        $totalCatalogs = Catalog::count();
        $activeCatalogs = Catalog::where('is_active', true)->count();
        $featuredCatalogs = Catalog::where('is_featured', true)->count();
        $outOfStockCatalogs = Catalog::where('stock', 0)->count();
        $lowStockCatalogs = Catalog::where('stock', '>', 0)->where('stock', '<=', 5)->count();
        $totalValue = Catalog::sum('price');
        
        $byCategory = Category::withCount(['categoryCatalogs' => function ($q) {
            $q->where('is_active', true);
        }])->get()->map(function ($category) {
            return [
                'category' => $category,
                'count' => $category->category_catalogs_count,
            ];
        });
        
        return [
            'totalCatalogs' => $totalCatalogs,
            'activeCatalogs' => $activeCatalogs,
            'featuredCatalogs' => $featuredCatalogs,
            'outOfStockCatalogs' => $outOfStockCatalogs,
            'lowStockCatalogs' => $lowStockCatalogs,
            'totalValue' => $totalValue,
            'byCategory' => $byCategory,
        ];
    }

    /**
     * Admin: Create Catalog
     */
    public function create($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user || !$user->hasRole('admin')) {
            throw new \Exception('Unauthorized: Admin access required');
        }
        
        $input = $args['input'];
        
        // Generate slug if not provided
        if (empty($input['slug'])) {
            $input['slug'] = Str::slug($input['name']);
        }
        
        // Ensure unique slug
        $originalSlug = $input['slug'];
        $counter = 1;
        while (Catalog::where('slug', $input['slug'])->exists()) {
            $input['slug'] = $originalSlug . '-' . $counter++;
        }
        
        // Calculate final price
        $input['final_price'] = $input['discount_price'] ?? $input['price'];
        $input['is_out_of_stock'] = ($input['stock'] ?? 0) <= 0;
        
        return Catalog::create($input);
    }

    /**
     * Admin: Update Catalog
     */
    public function update($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user || !$user->hasRole('admin')) {
            throw new \Exception('Unauthorized: Admin access required');
        }
        
        $catalog = Catalog::findOrFail($args['id']);
        $input = $args['input'];
        
        // Calculate final price if price or discount_price changed
        if (isset($input['price']) || isset($input['discount_price'])) {
            $price = $input['price'] ?? $catalog->price;
            $discountPrice = $input['discount_price'] ?? $catalog->discount_price;
            $input['final_price'] = $discountPrice ?? $price;
        }
        
        // Update stock status
        if (isset($input['stock'])) {
            $input['is_out_of_stock'] = $input['stock'] <= 0;
        }
        
        $catalog->update($input);
        
        return $catalog->fresh();
    }

    /**
     * Admin: Delete Catalog
     */
    public function delete($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user || !$user->hasRole('admin')) {
            throw new \Exception('Unauthorized: Admin access required');
        }
        
        $catalog = Catalog::findOrFail($args['id']);
        $catalog->delete();
        
        return true;
    }

    /**
     * Admin: Toggle Featured
     */
    public function toggleFeatured($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user || !$user->hasRole('admin')) {
            throw new \Exception('Unauthorized: Admin access required');
        }
        
        $catalog = Catalog::findOrFail($args['id']);
        $catalog->is_featured = !$catalog->is_featured;
        $catalog->save();
        
        return $catalog->fresh();
    }

    /**
     * Admin: Update Stock
     */
    public function updateStock($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user || !$user->hasRole('admin')) {
            throw new \Exception('Unauthorized: Admin access required');
        }
        
        $catalog = Catalog::findOrFail($args['id']);
        $catalog->stock = $args['quantity'];
        $catalog->is_out_of_stock = $args['quantity'] <= 0;
        $catalog->save();
        
        return $catalog->fresh();
    }

    /**
     * Admin: Bulk Update Catalogs
     */
    public function bulkUpdate($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user || !$user->hasRole('admin')) {
            throw new \Exception('Unauthorized: Admin access required');
        }
        
        $ids = $args['ids'];
        $input = $args['input'];
        
        // Calculate final price if needed
        if (isset($input['price']) || isset($input['discount_price'])) {
            // We need to handle this per catalog since they may have different prices
            // For simplicity, we'll update individually
        }
        
        $catalogs = Catalog::whereIn('id', $ids)->get();
        
        foreach ($catalogs as $catalog) {
            $updateData = $input;
            
            if (isset($input['price']) || isset($input['discount_price'])) {
                $price = $input['price'] ?? $catalog->price;
                $discountPrice = $input['discount_price'] ?? $catalog->discount_price;
                $updateData['final_price'] = $discountPrice ?? $price;
            }
            
            if (isset($input['stock'])) {
                $updateData['is_out_of_stock'] = $input['stock'] <= 0;
            }
            
            $catalog->update($updateData);
        }
        
        return Catalog::whereIn('id', $ids)->get();
    }
}
