<?php

namespace App\Grpc\Services;

use App\Models\Catalog\Catalog;
use App\Models\Package\Package;
use App\Models\Category\Category;
use App\Models\Search\SearchLog;
use Grpc\ServerCall;
use Laravel\Sanctum\PersonalAccessToken;

class SearchService
{
    public function Search(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $query = $request->getQuery();
        $type = $request->getType();
        $limit = $request->getLimit() ?: 20;
        $categoryId = $request->getCategoryId();
        $minPrice = $request->getMinPrice();
        $maxPrice = $request->getMaxPrice();
        
        $start = microtime(true);
        
        $catalogs = collect();
        $packages = collect();
        $categories = collect();
        
        if ($type === \Weddingapp\Search\SearchType::CATALOG || $type === \Weddingapp\Search\SearchType::BOTH) {
            $catalogs = $this->searchCatalogs($query, $categoryId, $minPrice, $maxPrice, $limit);
        }
        
        if ($type === \Weddingapp\Search\SearchType::PACKAGE || $type === \Weddingapp\Search\SearchType::BOTH) {
            $packages = $this->searchPackages($query, $categoryId, $minPrice, $maxPrice, $limit);
        }
        
        $categories = $this->searchCategories($query, $limit);
        
        $took = round((microtime(true) - $start) * 1000, 2);
        
        $results = new \Weddingapp\Search\SearchResults();
        foreach ($catalogs as $catalog) {
            $results->addCatalogs($this->buildCatalogProto($catalog));
        }
        foreach ($packages as $package) {
            $results->addPackages($this->buildPackageProto($package));
        }
        foreach ($categories as $category) {
            $catProto = new \Weddingapp\Catalog\Category();
            $catProto->setId($category->id);
            $catProto->setName($category->name);
            $catProto->setSlug($category->slug);
            $catProto->setImageUrl($category->image_url ?? '');
            $results->addCategories($catProto);
        }
        $results->setTotalCatalogs($catalogs->count());
        $results->setTotalPackages($packages->count());
        $results->setQuery($query);
        $results->setTookMs($took);
        
        $response = new \Weddingapp\Search\SearchResponse();
        $response->setResults($results);
        
        return $call->sendResponse($response);
    }

    public function GetSuggestions(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $query = $request->getQuery();
        $limit = $request->getLimit() ?: 5;
        
        if (strlen($query) < 2) {
            $response = new \Weddingapp\Search\SuggestionsResponse();
            return $call->sendResponse($response);
        }
        
        $suggestions = collect();
        
        $catalogs = Catalog::where('is_active', true)
            ->where('name', 'like', "%{$query}%")
            ->select('id', 'name', 'slug', 'image_url')
            ->take($limit)
            ->get()
            ->map(function ($catalog) {
                return [
                    'text' => $catalog->name,
                    'type' => \Weddingapp\Search\SuggestionType::CATALOG,
                    'count' => 1,
                    'imageUrl' => $catalog->image_url,
                ];
            });
        
        $packages = Package::where('is_active', true)
            ->where('name', 'like', "%{$query}%")
            ->select('id', 'name', 'slug', 'image_url')
            ->take($limit)
            ->get()
            ->map(function ($package) {
                return [
                    'text' => $package->name,
                    'type' => \Weddingapp\Search\SuggestionType::PACKAGE,
                    'count' => 1,
                    'imageUrl' => $package->image_url,
                ];
            });
        
        $categories = Category::where('is_active', true)
            ->where('name', 'like', "%{$query}%")
            ->select('id', 'name', 'slug', 'image_url')
            ->take($limit)
            ->get()
            ->map(function ($category) {
                return [
                    'text' => $category->name,
                    'type' => \Weddingapp\Search\SuggestionType::CATEGORY,
                    'count' => 1,
                    'imageUrl' => $category->image_url,
                ];
            });
        
        $keywords = SearchLog::where('query', 'like', "%{$query}%")
            ->selectRaw('query, count(*) as count')
            ->groupBy('query')
            ->orderByDesc('count')
            ->take($limit)
            ->get()
            ->map(function ($keyword) {
                return [
                    'text' => $keyword->query,
                    'type' => \Weddingapp\Search\SuggestionType::KEYWORD,
                    'count' => $keyword->count,
                    'imageUrl' => null,
                ];
            });
        
        $suggestions = $suggestions
            ->concat($catalogs)
            ->concat($packages)
            ->concat($categories)
            ->concat($keywords)
            ->take($limit)
            ->values();
        
        $response = new \Weddingapp\Search\SuggestionsResponse();
        foreach ($suggestions as $suggestion) {
            $suggestionProto = new \Weddingapp\Search\SearchSuggestion();
            $suggestionProto->setText($suggestion['text']);
            $suggestionProto->setType($suggestion['type']);
            $suggestionProto->setCount($suggestion['count']);
            $suggestionProto->setImageUrl($suggestion['imageUrl'] ?? '');
            $response->addSuggestions($suggestionProto);
        }
        
        return $call->sendResponse($response);
    }

    public function GetPopularSearches(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $limit = $request->getLimit() ?: 10;
        
        $searches = SearchLog::selectRaw('query, count(*) as count')
            ->groupBy('query')
            ->orderByDesc('count')
            ->take($limit)
            ->get()
            ->map(function ($item) {
                $recentCount = SearchLog::where('query', $item->query)
                    ->where('created_at', '>=', now()->subDays(7))
                    ->count();
                
                $previousCount = SearchLog::where('query', $item->query)
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
        
        $response = new \Weddingapp\Search\PopularSearchesResponse();
        foreach ($searches as $search) {
            $searchProto = new \Weddingapp\Search\PopularSearch();
            $searchProto->setQuery($search['query']);
            $searchProto->setCount($search['count']);
            $searchProto->setTrend($search['trend']);
            $response->addSearches($searchProto);
        }
        
        return $call->sendResponse($response);
    }

    public function GetFilters(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $query = $request->getQuery();
        $categoryId = $request->getCategoryId();
        
        $baseCatalogQuery = Catalog::where('is_active', true)->where('stock', '>', 0);
        $basePackageQuery = Package::where('is_active', true);
        
        if ($query) {
            $baseCatalogQuery->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            });
            $basePackageQuery->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            });
        }
        
        if ($categoryId) {
            $baseCatalogQuery->where('category_id', $categoryId);
            $basePackageQuery->where('category_id', $categoryId);
        }
        
        $categoryCounts = Category::where('is_active', true)
            ->withCount([
                'categoryCatalogs' => fn($q) => $q->where('is_active', true)->where('stock', '>', 0),
                'packages' => fn($q) => $q->where('is_active', true),
            ])
            ->get()
            ->map(function ($category) {
                return [
                    'category' => $category,
                    'count' => $category->catalogs_count + $category->packages_count,
                ];
            })
            ->filter(fn($item) => $item['count'] > 0)
            ->values();
        
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
        
        $minCapacity = Package::where('is_active', true)->min('min_capacity') ?? 0;
        $maxCapacity = Package::where('is_active', true)->max('max_capacity') ?? 0;
        
        $filters = new \Weddingapp\Search\SearchFilters();
        
        foreach ($categoryCounts as $item) {
            $catFilter = new \Weddingapp\Search\CategoryFilter();
            $catProto = new \Weddingapp\Catalog\Category();
            $catProto->setId($item['category']->id);
            $catProto->setName($item['category']->name);
            $catProto->setSlug($item['category']->slug);
            $catProto->setImageUrl($item['category']->image_url ?? '');
            $catFilter->setCategory($catProto);
            $catFilter->setCount($item['count']);
            $filters->addCategories($catFilter);
        }
        
        $priceRange = new \Weddingapp\Search\PriceRange();
        $priceRange->setMin($minPrice === PHP_FLOAT_MAX ? 0 : $minPrice);
        $priceRange->setMax($maxPrice);
        $priceRange->setMinFound($minFound === PHP_FLOAT_MAX ? 0 : $minFound);
        $priceRange->setMaxFound($maxFound);
        $filters->setPriceRange($priceRange);
        
        foreach ($themes as $theme) {
            $filters->addThemes($theme);
        }
        
        foreach ($colors as $color) {
            $filters->addColors($color);
        }
        
        $filters->setMinCapacity($minCapacity);
        $filters->setMaxCapacity($maxCapacity);
        
        $response = new \Weddingapp\Search\FiltersResponse();
        $response->setFilters($filters);
        
        return $call->sendResponse($response);
    }

    protected function getUserFromToken(string $token): \App\Models\User\User
    {
        $accessToken = PersonalAccessToken::findToken($token);
        if (!$accessToken) {
            throw new \Exception('Unauthorized: Invalid token');
        }
        return $accessToken->tokenable;
    }

    protected function searchCatalogs(string $query, ?int $categoryId, ?float $minPrice, ?float $maxPrice, int $limit)
    {
        return Catalog::where('is_active', true)
            ->where('stock', '>', 0)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%")
                  ->orWhere('theme', 'like', "%{$query}%")
                  ->orWhere('color', 'like', "%{$query}%");
            })
            ->when($categoryId, fn($q) => $q->where('category_id', $categoryId))
            ->when($minPrice, fn($q) => $q->where('price', '>=', $minPrice))
            ->when($maxPrice, fn($q) => $q->where('price', '<=', $maxPrice))
            ->with('category')
            ->latest()
            ->take($limit)
            ->get();
    }

    protected function searchPackages(string $query, ?int $categoryId, ?float $minPrice, ?float $maxPrice, int $limit)
    {
        return Package::where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%")
                  ->orWhere('theme', 'like', "%{$query}%")
                  ->orWhere('color', 'like', "%{$query}%");
            })
            ->when($categoryId, fn($q) => $q->where('category_id', $categoryId))
            ->when($minPrice, fn($q) => $q->where('price', '>=', $minPrice))
            ->when($maxPrice, fn($q) => $q->where('price', '<=', $maxPrice))
            ->with('category')
            ->latest()
            ->take($limit)
            ->get();
    }

    protected function searchCategories(string $query, int $limit)
    {
        return Category::where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            })
            ->orderBy('name')
            ->take($limit)
            ->get();
    }

    protected function buildCatalogProto($catalog): \Weddingapp\Catalog\Catalog
    {
        $proto = new \Weddingapp\Catalog\Catalog();
        $proto->setId($catalog->id);
        $proto->setName($catalog->name);
        $proto->setSlug($catalog->slug);
        $proto->setPrice($catalog->price);
        $proto->setDiscountPrice($catalog->discount_price ?? 0);
        $proto->setFinalPrice($catalog->final_price);
        $proto->setImageUrl($catalog->image_url ?? '');
        $proto->setIsFeatured($catalog->is_featured);
        $proto->setIsActive($catalog->is_active);
        $proto->setStock($catalog->stock);
        
        if ($catalog->category) {
            $catProto = new \Weddingapp\Catalog\Category();
            $catProto->setId($catalog->category->id);
            $catProto->setName($catalog->category->name);
            $catProto->setSlug($catalog->category->slug);
            $catProto->setImageUrl($catalog->category->image_url ?? '');
            $proto->setCategory($catProto);
        }
        
        return $proto;
    }

    protected function buildPackageProto($package): \Weddingapp\Package\Package
    {
        $proto = new \Weddingapp\Package\Package();
        $proto->setId($package->id);
        $proto->setName($package->name);
        $proto->setSlug($package->slug);
        $proto->setPrice($package->price);
        $proto->setDiscountPrice($package->discount_price ?? 0);
        $proto->setFinalPrice($package->final_price);
        $proto->setImageUrl($package->image_url ?? '');
        $proto->setIsFeatured($package->is_featured);
        $proto->setIsActive($package->is_active);
        
        if ($package->category) {
            $catProto = new \Weddingapp\Catalog\Category();
            $catProto->setId($package->category->id);
            $catProto->setName($package->category->name);
            $catProto->setSlug($package->category->slug);
            $catProto->setImageUrl($package->category->image_url ?? '');
            $proto->setCategory($catProto);
        }
        
        return $proto;
    }
}