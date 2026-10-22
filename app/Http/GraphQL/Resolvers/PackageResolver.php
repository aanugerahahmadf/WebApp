<?php

namespace App\Http\GraphQL\Resolvers;

use App\Models\Package\Package;
use App\Models\Category\Category;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class PackageResolver
{
    /**
     * Get featured packages
     */
    public function featuredPackages($root, array $args, GraphQLContext $context)
    {
        $first = $args['first'] ?? 10;
        
        return Package::where('is_featured', true)
            ->where('is_active', true)
            ->with('category')
            ->latest()
            ->take($first)
            ->get();
    }

    /**
     * Get on-sale packages
     */
    public function onSalePackages($root, array $args, GraphQLContext $context)
    {
        $first = $args['first'] ?? 10;
        
        return Package::where('discount_price', '>', 0)
            ->where('is_active', true)
            ->with('category')
            ->latest()
            ->take($first)
            ->get();
    }

    /**
     * Get packages by category
     */
    public function byCategory($root, array $args, GraphQLContext $context)
    {
        $categoryId = $args['categoryId'];
        $first = $args['first'] ?? 10;
        
        return Package::where('category_id', $categoryId)
            ->where('is_active', true)
            ->with('category')
            ->latest()
            ->take($first)
            ->get();
    }

    /**
     * Get packages by capacity
     */
    public function byCapacity($root, array $args, GraphQLContext $context)
    {
        $minCapacity = $args['minCapacity'];
        $maxCapacity = $args['maxCapacity'] ?? 999;
        $first = $args['first'] ?? 10;
        
        return Package::where('min_capacity', '<=', $maxCapacity)
            ->where('max_capacity', '>=', $minCapacity)
            ->where('is_active', true)
            ->with('category')
            ->latest()
            ->take($first)
            ->get();
    }

    /**
     * Get related packages
     */
    public function related($root, array $args, GraphQLContext $context)
    {
        $packageId = $args['packageId'];
        $first = $args['first'] ?? 5;
        
        $package = Package::findOrFail($packageId);
        
        return Package::where('category_id', $package->category_id)
            ->where('id', '!=', $packageId)
            ->where('is_active', true)
            ->with('category')
            ->inRandomOrder()
            ->take($first)
            ->get();
    }

    /**
     * Search packages
     */
    public function search($root, array $args, GraphQLContext $context)
    {
        $query = $args['query'];
        $first = $args['first'] ?? 10;
        
        return Package::where('is_active', true)
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
     * Get package statistics (admin)
     */
    public function stats($root, array $args, GraphQLContext $context)
    {
        $totalPackages = Package::count();
        $activePackages = Package::where('is_active', true)->count();
        $featuredPackages = Package::where('is_featured', true)->count();
        $onSalePackages = Package::where('discount_price', '>', 0)->count();
        $totalValue = Package::sum('price');
        
        $byCategory = Category::withCount(['packages' => function ($q) {
            $q->where('is_active', true);
        }])->get()->map(function ($category) {
            return [
                'category' => $category,
                'count' => $category->packages_count,
            ];
        });
        
        $byCapacity = Package::selectRaw('min_capacity, max_capacity, count(*) as count')
            ->where('is_active', true)
            ->groupBy('min_capacity', 'max_capacity')
            ->get()
            ->map(function ($item) {
                return [
                    'minCapacity' => $item->min_capacity,
                    'maxCapacity' => $item->max_capacity,
                    'count' => $item->count,
                ];
            });
        
        return [
            'totalPackages' => $totalPackages,
            'activePackages' => $activePackages,
            'featuredPackages' => $featuredPackages,
            'onSalePackages' => $onSalePackages,
            'totalValue' => $totalValue,
            'byCategory' => $byCategory,
            'byCapacity' => $byCapacity,
        ];
    }

    /**
     * Admin: Create Package
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
        while (Package::where('slug', $input['slug'])->exists()) {
            $input['slug'] = $originalSlug . '-' . $counter++;
        }
        
        // Calculate final price
        $input['final_price'] = $input['discount_price'] ?? $input['price'];
        
        return Package::create($input);
    }

    /**
     * Admin: Update Package
     */
    public function update($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user || !$user->hasRole('admin')) {
            throw new \Exception('Unauthorized: Admin access required');
        }
        
        $package = Package::findOrFail($args['id']);
        $input = $args['input'];
        
        // Calculate final price if price or discount_price changed
        if (isset($input['price']) || isset($input['discount_price'])) {
            $price = $input['price'] ?? $package->price;
            $discountPrice = $input['discount_price'] ?? $package->discount_price;
            $input['final_price'] = $discountPrice ?? $price;
        }
        
        $package->update($input);
        
        return $package->fresh();
    }

    /**
     * Admin: Delete Package
     */
    public function delete($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user || !$user->hasRole('admin')) {
            throw new \Exception('Unauthorized: Admin access required');
        }
        
        $package = Package::findOrFail($args['id']);
        $package->delete();
        
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
        
        $package = Package::findOrFail($args['id']);
        $package->is_featured = !$package->is_featured;
        $package->save();
        
        return $package->fresh();
    }

    /**
     * Admin: Bulk Update Packages
     */
    public function bulkUpdate($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user || !$user->hasRole('admin')) {
            throw new \Exception('Unauthorized: Admin access required');
        }
        
        $ids = $args['ids'];
        $input = $args['input'];
        
        $packages = Package::whereIn('id', $ids)->get();
        
        foreach ($packages as $package) {
            $updateData = $input;
            
            if (isset($input['price']) || isset($input['discount_price'])) {
                $price = $input['price'] ?? $package->price;
                $discountPrice = $input['discount_price'] ?? $package->discount_price;
                $updateData['final_price'] = $discountPrice ?? $price;
            }
            
            $package->update($updateData);
        }
        
        return Package::whereIn('id', $ids)->get();
    }
}