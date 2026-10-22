<?php

namespace App\Http\GraphQL\Resolvers;

use App\Models\Catalog\Catalog;
use App\Models\Package\Package;
use App\Models\Category\Category;
use Illuminate\Database\Eloquent\Builder;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class SearchResolver
{
    /**
     * Universal search
     */
    public function index($root, array $args, GraphQLContext $context)
    {
        $query = $args['query'];
        $type = $args['type'] ?? 'BOTH';
        $first = $args['first'] ?? 20;
        $categoryId = $args['categoryId'] ?? null;
        $minPrice = $args['minPrice'] ?? null;
        $maxPrice = $args['maxPrice'] ?? null;
        
        $start = microtime(true);
        
        $catalogs = collect();
        $packages = collect();
        $categories = collect();
        
        if ($type === 'CATALOG' || $type === 'BOTH') {
            $catalogs = $this->searchCatalogs($query, $categoryId, $minPrice, $maxPrice, $first);
        }
        
        if ($type === 'PACKAGE' || $type === 'BOTH') {
            $packages = $this->searchPackages($query, $categoryId, $minPrice, $maxPrice, $first);
        }
        
        $categories = $this->searchCategories($query, $first);
        
        $took = round((microtime(true) - $start) * 1000, 2);
        
        return [
            'catalogs' => $catalogs,
            'packages' => $packages,
            'categories' => $categories,
            'totalCatalogs' => $catalogs->count(),
            'totalPackages' => $packages->count(),
            'query' => $query,
            'took' => $took,
        ];
    }

    /**
     * Search catalogs
     */
    private function searchCatalogs(string $query, ?string $categoryId, ?float $minPrice, ?float $maxPrice, int $limit)
    {
        return Catalog::where('is_active', true)
            ->where('stock', '>', 0)
            ->where(function (Builder $q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%")
                  ->orWhere('theme', 'like', "%{$query}%")
                  ->orWhere('color', 'like', "%{$query}%")
                  ->orWhere('features', 'like', "%{$query}%");
            })
            ->when($categoryId, fn(Builder $q) => $q->where('category_id', $categoryId))
            ->when($minPrice, fn(Builder $q) => $q->where('price', '>=', $minPrice))
            ->when($maxPrice, fn(Builder $q) => $q->where('price', '<=', $maxPrice))
            ->with('category')
            ->latest()
            ->take($limit)
            ->get();
    }

    /**
     * Search packages
     */
    private function searchPackages(string $query, ?string $categoryId, ?float $minPrice, ?float $maxPrice, int $limit)
    {
        return Package::where('is_active', true)
            ->where(function (Builder $q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%")
                  ->orWhere('theme', 'like', "%{$query}%")
                  ->orWhere('color', 'like', "%{$query}%")
                  ->orWhere('features', 'like', "%{$query}%");
            })
            ->when($categoryId, fn(Builder $q) => $q->where('category_id', $categoryId))
            ->when($minPrice, fn(Builder $q) => $q->where('price', '>=', $minPrice))
            ->when($maxPrice, fn(Builder $q) => $q->where('price', '<=', $maxPrice))
            ->with('category')
            ->latest()
            ->take($limit)
            ->get();
    }

    /**
     * Search categories
     */
    private function searchCategories(string $query, int $limit)
    {
        return Category::where('is_active', true)
            ->where(function (Builder $q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            })
            ->orderBy('name')
            ->take($limit)
            ->get();
    }

    /**
     * Search suggestions/autocomplete
     */
    public function suggestions($root, array $args, GraphQLContext $context)
    {
        $query = $args['query'];
        $limit = $args['limit'] ?? 5;
        
        if (strlen($query) < 2) {
            return [];
        }
        
        $suggestions = collect();
        
        // Catalog suggestions
        $catalogs = Catalog::where('is_active', true)
            ->where('name', 'like', "%{$query}%")
            ->select('id', 'name', 'slug', 'image_url')
            ->take($limit)
            ->get()
            ->map(function ($catalog) {
                return [
                    'text' => $catalog->name,
                    'type' => 'CATALOG',
                    'count' => 1,
                    'imageUrl' => $catalog->image_url,
                ];
            });
        
        // Package suggestions
        $packages = Package::where('is_active', true)
            ->where('name', 'like', "%{$query}%")
            ->select('id', 'name', 'slug', 'image_url')
            ->take($limit)
            ->get()
            ->map(function ($package) {
                return [
                    'text' => $package->name,
                    'type' => 'PACKAGE',
                    'count' => 1,
                    'imageUrl' => $package->image_url,
                ];
            });
        
        // Category suggestions
        $categories = Category::where('is_active', true)
            ->where('name', 'like', "%{$query}%")
            ->select('id', 'name', 'slug', 'image_url')
            ->take($limit)
            ->get()
            ->map(function ($category) {
                return [
                    'text' => $category->name,
                    'type' => 'CATEGORY',
                    'count' => 1,
                    'imageUrl' => $category->image_url,
                ];
            });
        
        // Keyword suggestions from popular searches
        $keywords = \App\Models\Search\SearchLog::where('query', 'like', "%{$query}%")
            ->selectRaw('query, count(*) as count')
            ->groupBy('query')
            ->orderByDesc('count')
            ->take($limit)
            ->get()
            ->map(function ($keyword) {
                return [
                    'text' => $keyword->query,
                    'type' => 'KEYWORD',
                    'count' => $keyword->count,
                    'imageUrl' => null,
                ];
            });
        
        return $suggestions
            ->concat($catalogs)
            ->concat($packages)
            ->concat($categories)
            ->concat($keywords)
            ->take($limit)
            ->values();
    }

    /**
     * Popular searches
     */
    public function popular($root, array $args, GraphQLContext $context)
    {
        $limit = $args['limit'] ?? 10;
        
        return \App\Models\Search\SearchLog::selectRaw('query, count(*) as count')
            ->groupBy('query')
            ->orderByDesc('count')
            ->take($limit)
            ->get()
            ->map(function ($item) {
                // Determine trend (simplified - compare last 7 days vs previous 7 days)
                $recentCount = \App\Models\Search\SearchLog::where('query', $item->query)
                    ->where('created_at', '>=', now()->subDays(7))
                    ->count();
                
                $previousCount = \App\Models\Search\SearchLog::where('query', $item->query)
                    ->where('created_at', '>=', now()->subDays(14))
                    ->where('created_at', '<', now()->subDays(7))
                    ->count();
                
                $trend = 'stable';
                if ($previousCount > 0) {
                    $change = (($recentCount - $previousCount) / $previousCount) * 100;
                    if ($change > 10) $trend = 'up';
                    elseif ($change < -10) $trend = 'down';
                }
                
                return [
                    'query' => $item->query,
                    'count' => $item->count,
                    'trend' => $trend,
                ];
            });
    }

    /**
     * Search filters/facets
     */
    public function filters($root, array $args, GraphQLContext $context)
    {
        $query = $args['query'] ?? null;
        $categoryId = $args['categoryId'] ?? null;
        
        $baseCatalogQuery = Catalog::where('is_active', true)->where('stock', '>', 0);
        $basePackageQuery = Package::where('is_active', true);
        
        if ($query) {
            $baseCatalogQuery->where(function (Builder $q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            });
            
            $basePackageQuery->where(function (Builder $q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            });
        }
        
        if ($categoryId) {
            $baseCatalogQuery->where('category_id', $categoryId);
            $basePackageQuery->where('category_id', $categoryId);
        }
        
        // Categories with counts
        $categoryCounts = Category::where('is_active', true)
            ->withCount([
                'categoryCatalogs' => fn(Builder $q) => $q->where('is_active', true)->where('stock', '>', 0),
                'packages' => fn(Builder $q) => $q->where('is_active', true),
            ])
            ->get()
            ->map(function ($category) {
                return [
                    'category' => $category,
                    'count' => $category->category_catalogs_count + $category->packages_count,
                ];
            })
            ->filter(fn($item) => $item['count'] > 0)
            ->values();
        
        // Price range
        $minPrice = min(
            $baseCatalogQuery->min('price') ?? PHP_FLOAT_MAX,
            $basePackageQuery->min('price') ?? PHP_FLOAT_MAX
        );
        $maxPrice = max(
            $baseCatalogQuery->max('price') ?? 0,
            $basePackageQuery->max('price') ?? 0
        );
        
        $minFound = min(
            $baseCatalogQuery->min('discount_price') ?? $baseCatalogQuery->min('price') ?? PHP_FLOAT_MAX,
            $basePackageQuery->min('discount_price') ?? $basePackageQuery->min('price') ?? PHP_FLOAT_MAX
        );
        $maxFound = max(
            $baseCatalogQuery->max('discount_price') ?? $baseCatalogQuery->max('price') ?? 0,
            $basePackageQuery->max('discount_price') ?? $basePackageQuery->max('price') ?? 0
        );
        
        // Themes
        $themes = Catalog::where('is_active', true)
            ->whereNotNull('theme')
            ->where('theme', '!=', '')
            ->pluck('theme')
            ->unique()
            ->merge(Package::where('is_active', true)
                ->whereNotNull('theme')
                ->where('theme', '!=', '')
                ->pluck('theme'))
            ->unique()
            ->values();
        
        // Colors
        $colors = Catalog::where('is_active', true)
            ->whereNotNull('color')
            ->where('color', '!=', '')
            ->pluck('color')
            ->unique()
            ->merge(Package::where('is_active', true)
                ->whereNotNull('color')
                ->where('color', '!=', '')
                ->pluck('color'))
            ->unique()
            ->values();
        
        // Capacity
        $minCapacity = Package::where('is_active', true)->min('min_capacity') ?? 0;
        $maxCapacity = Package::where('is_active', true)->max('max_capacity') ?? 0;
        
        return [
            'categories' => $categoryCounts,
            'priceRange' => [
                'min' => $minPrice === PHP_FLOAT_MAX ? 0 : $minPrice,
                'max' => $maxPrice,
                'minFound' => $minFound === PHP_FLOAT_MAX ? 0 : $minFound,
                'maxFound' => $maxFound,
            ],
            'themes' => $themes,
            'colors' => $colors,
            'minCapacity' => $minCapacity,
            'maxCapacity' => $maxCapacity,
        ];
    }
}