<?php

namespace App\Grpc\Services;

use App\Models\Catalog\Catalog;
use App\Models\Category\Category;
use Grpc\ServerCall;
use Illuminate\Database\Eloquent\Builder;

class CatalogService
{
    public function GetCatalog(ServerCall $call, $request)
    {
        $catalog = Catalog::with('category')->find($request->getId());
        
        $response = new \Weddingapp\Catalog\CatalogResponse();
        if ($catalog) {
            $response->setCatalog($this->buildCatalogProto($catalog));
        }
        return $call->sendResponse($response);
    }

    public function ListCatalogs(ServerCall $call, $request)
    {
        $query = Catalog::with('category');
        
        if ($request->getSearch()) {
            $query->where(function (Builder $q) use ($request) {
                $q->where('name', 'like', "%{$request->getSearch()}%")
                  ->orWhere('description', 'like', "%{$request->getSearch()}%");
            });
        }
        
        if ($request->getCategoryId()) {
            $query->where('category_id', $request->getCategoryId());
        }
        
        if ($request->getMinPrice()) {
            $query->where('price', '>=', $request->getMinPrice());
        }
        
        if ($request->getMaxPrice()) {
            $query->where('price', '<=', $request->getMaxPrice());
        }
        
        if ($request->getFeatured()) {
            $query->where('is_featured', true);
        }
        
        if ($request->getOnSale()) {
            $query->where('discount_price', '>', 0);
        }
        
        if ($request->getActive()) {
            $query->where('is_active', true);
        }
        
        if ($request->getTheme()) {
            $query->where('theme', $request->getTheme());
        }
        
        if ($request->getColor()) {
            $query->where('color', $request->getColor());
        }
        
        $sortBy = $request->getSortBy() ?: 'created_at';
        $sortOrder = $request->getSortOrder() ?: 'desc';
        $query->orderBy($sortBy, $sortOrder);
        
        $perPage = $request->getPerPage() ?: 10;
        $page = $request->getPage() ?: 1;
        
        $paginated = $query->paginate($perPage, ['*'], 'page', $page);
        
        $response = new \Weddingapp\Catalog\CatalogListResponse();
        foreach ($paginated->items() as $catalog) {
            $response->addCatalogs($this->buildCatalogProto($catalog));
        }
        
        $pagination = new \Weddingapp\Catalog\PaginationMeta();
        $pagination->setCurrentPage($paginated->currentPage());
        $pagination->setPerPage($paginated->perPage());
        $pagination->setTotal($paginated->total());
        $pagination->setLastPage($paginated->lastPage());
        $pagination->setFrom($paginated->firstItem());
        $pagination->setTo($paginated->lastItem());
        $pagination->setHasNextPage($paginated->hasMorePages());
        $pagination->setHasPrevPage($paginated->currentPage() > 1);
        $response->setPagination($pagination);
        
        return $call->sendResponse($response);
    }

    public function GetCatalogBySlug(ServerCall $call, $request)
    {
        $catalog = Catalog::with('category')->where('slug', $request->getSlug())->first();
        
        $response = new \Weddingapp\Catalog\CatalogResponse();
        if ($catalog) {
            $response->setCatalog($this->buildCatalogProto($catalog));
        }
        return $call->sendResponse($response);
    }

    public function GetCategories(ServerCall $call, $request)
    {
        $query = Category::query();
        
        if ($request->getActiveOnly()) {
            $query->where('is_active', true);
        }
        
        if ($request->getParentId()) {
            $query->where('parent_id', $request->getParentId());
        } else {
            $query->whereNull('parent_id');
        }
        
        $categories = $query->withCount([
            'categoryCatalogs' => fn(Builder $q) => $q->where('is_active', true),
            'packages' => fn(Builder $q) => $q->where('is_active', true),
        ])->orderBy('name')->get();
        
        $response = new \Weddingapp\Catalog\CategoriesResponse();
        foreach ($categories as $category) {
            $catProto = new \Weddingapp\Catalog\Category();
            $catProto->setId($category->id);
            $catProto->setName($category->name);
            $catProto->setSlug($category->slug);
            $catProto->setDescription($category->description ?? '');
            $catProto->setImageUrl($category->image_url ?? '');
            $catProto->setIsActive($category->is_active);
            $catProto->setCatalogsCount($category->catalogs_count);
            $catProto->setPackagesCount($category->packages_count);
            $catProto->setCreatedAt($category->created_at?->toISOString() ?? '');
            $response->addCategories($catProto);
        }
        
        return $call->sendResponse($response);
    }

    public function GetCategory(ServerCall $call, $request)
    {
        $category = null;
        if ($request->getId()) {
            $category = Category::withCount([
                'categoryCatalogs' => fn(Builder $q) => $q->where('is_active', true),
                'packages' => fn(Builder $q) => $q->where('is_active', true),
            ])->find($request->getId());
        } elseif ($request->getSlug()) {
            $category = Category::withCount([
                'categoryCatalogs' => fn(Builder $q) => $q->where('is_active', true),
                'packages' => fn(Builder $q) => $q->where('is_active', true),
            ])->where('slug', $request->getSlug())->first();
        }
        
        $response = new \Weddingapp\Catalog\CategoryResponse();
        if ($category) {
            $catProto = new \Weddingapp\Catalog\Category();
            $catProto->setId($category->id);
            $catProto->setName($category->name);
            $catProto->setSlug($category->slug);
            $catProto->setDescription($category->description ?? '');
            $catProto->setImageUrl($category->image_url ?? '');
            $catProto->setIsActive($category->is_active);
            $catProto->setCatalogsCount($category->catalogs_count);
            $catProto->setPackagesCount($category->packages_count);
            $catProto->setCreatedAt($category->created_at?->toISOString() ?? '');
            $response->setCategory($catProto);
        }
        
        return $call->sendResponse($response);
    }

    public function GetCatalogsByCategory(ServerCall $call, $request)
    {
        $query = Catalog::with('category')
            ->where('category_id', $request->getCategoryId())
            ->where('is_active', true)
            ->where('stock', '>', 0);
        
        $perPage = $request->getPerPage() ?: 10;
        $page = $request->getPage() ?: 1;
        
        $paginated = $query->latest()->paginate($perPage, ['*'], 'page', $page);
        
        $response = new \Weddingapp\Catalog\CatalogListResponse();
        foreach ($paginated->items() as $catalog) {
            $response->addCatalogs($this->buildCatalogProto($catalog));
        }
        
        $pagination = new \Weddingapp\Catalog\PaginationMeta();
        $pagination->setCurrentPage($paginated->currentPage());
        $pagination->setPerPage($paginated->perPage());
        $pagination->setTotal($paginated->total());
        $pagination->setLastPage($paginated->lastPage());
        $pagination->setFrom($paginated->firstItem());
        $pagination->setTo($paginated->lastItem());
        $pagination->setHasNextPage($paginated->hasMorePages());
        $pagination->setHasPrevPage($paginated->currentPage() > 1);
        $response->setPagination($pagination);
        
        return $call->sendResponse($response);
    }

    public function SearchCatalogs(ServerCall $call, $request)
    {
        $query = Catalog::with('category')
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->where(function (Builder $q) use ($request) {
                $q->where('name', 'like', "%{$request->getQuery()}%")
                  ->orWhere('description', 'like', "%{$request->getQuery()}%")
                  ->orWhere('theme', 'like', "%{$request->getQuery()}%")
                  ->orWhere('color', 'like', "%{$request->getQuery()}%");
            });
        
        if ($request->getCategoryId()) {
            $query->where('category_id', $request->getCategoryId());
        }
        
        if ($request->getMinPrice()) {
            $query->where('price', '>=', $request->getMinPrice());
        }
        
        if ($request->getMaxPrice()) {
            $query->where('price', '<=', $request->getMaxPrice());
        }
        
        $perPage = $request->getPerPage() ?: 10;
        $page = $request->getPage() ?: 1;
        
        $paginated = $query->latest()->paginate($perPage, ['*'], 'page', $page);
        
        $response = new \Weddingapp\Catalog\CatalogListResponse();
        foreach ($paginated->items() as $catalog) {
            $response->addCatalogs($this->buildCatalogProto($catalog));
        }
        
        $pagination = new \Weddingapp\Catalog\PaginationMeta();
        $pagination->setCurrentPage($paginated->currentPage());
        $pagination->setPerPage($paginated->perPage());
        $pagination->setTotal($paginated->total());
        $pagination->setLastPage($paginated->lastPage());
        $pagination->setFrom($paginated->firstItem());
        $pagination->setTo($paginated->lastItem());
        $pagination->setHasNextPage($paginated->hasMorePages());
        $pagination->setHasPrevPage($paginated->currentPage() > 1);
        $response->setPagination($pagination);
        
        return $call->sendResponse($response);
    }

    public function GetFeaturedCatalogs(ServerCall $call, $request)
    {
        $limit = $request->getLimit() ?: 10;
        
        $catalogs = Catalog::with('category')
            ->where('is_featured', true)
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->latest()
            ->take($limit)
            ->get();
        
        $response = new \Weddingapp\Catalog\CatalogListResponse();
        foreach ($catalogs as $catalog) {
            $response->addCatalogs($this->buildCatalogProto($catalog));
        }
        
        $pagination = new \Weddingapp\Catalog\PaginationMeta();
        $pagination->setCurrentPage(1);
        $pagination->setPerPage($limit);
        $pagination->setTotal($catalogs->count());
        $pagination->setLastPage(1);
        $pagination->setFrom(1);
        $pagination->setTo($catalogs->count());
        $pagination->setHasNextPage(false);
        $pagination->setHasPrevPage(false);
        $response->setPagination($pagination);
        
        return $call->sendResponse($response);
    }

    public function GetOnSaleCatalogs(ServerCall $call, $request)
    {
        $limit = $request->getLimit() ?: 10;
        
        $catalogs = Catalog::with('category')
            ->where('discount_price', '>', 0)
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->latest()
            ->take($limit)
            ->get();
        
        $response = new \Weddingapp\Catalog\CatalogListResponse();
        foreach ($catalogs as $catalog) {
            $response->addCatalogs($this->buildCatalogProto($catalog));
        }
        
        $pagination = new \Weddingapp\Catalog\PaginationMeta();
        $pagination->setCurrentPage(1);
        $pagination->setPerPage($limit);
        $pagination->setTotal($catalogs->count());
        $pagination->setLastPage(1);
        $pagination->setFrom(1);
        $pagination->setTo($catalogs->count());
        $pagination->setHasNextPage(false);
        $pagination->setHasPrevPage(false);
        $response->setPagination($pagination);
        
        return $call->sendResponse($response);
    }

    public function GetRelatedCatalogs(ServerCall $call, $request)
    {
        $limit = $request->getLimit() ?: 5;
        
        $catalog = Catalog::find($request->getCatalogId());
        if (!$catalog) {
            $response = new \Weddingapp\Catalog\CatalogListResponse();
            return $call->sendResponse($response);
        }
        
        $catalogs = Catalog::with('category')
            ->where('category_id', $catalog->category_id)
            ->where('id', '!=', $catalog->id)
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->inRandomOrder()
            ->take($limit)
            ->get();
        
        $response = new \Weddingapp\Catalog\CatalogListResponse();
        foreach ($catalogs as $catalog) {
            $response->addCatalogs($this->buildCatalogProto($catalog));
        }
        
        $pagination = new \Weddingapp\Catalog\PaginationMeta();
        $pagination->setCurrentPage(1);
        $pagination->setPerPage($limit);
        $pagination->setTotal($catalogs->count());
        $pagination->setLastPage(1);
        $pagination->setFrom(1);
        $pagination->setTo($catalogs->count());
        $pagination->setHasNextPage(false);
        $pagination->setHasPrevPage(false);
        $response->setPagination($pagination);
        
        return $call->sendResponse($response);
    }

    protected function buildCatalogProto(Catalog $catalog): \Weddingapp\Catalog\Catalog
    {
        $proto = new \Weddingapp\Catalog\Catalog();
        $proto->setId($catalog->id);
        $proto->setName($catalog->name);
        $proto->setSlug($catalog->slug);
        $proto->setDescription($catalog->description ?? '');
        $proto->setPrice($catalog->price);
        $proto->setDiscountPrice($catalog->discount_price ?? 0);
        $proto->setFinalPrice($catalog->final_price);
        $proto->setStock($catalog->stock);
        $proto->setIsActive($catalog->is_active);
        $proto->setIsFeatured($catalog->is_featured);
        $proto->setIsOutOfStock($catalog->is_out_of_stock);
        $proto->setImageUrl($catalog->image_url ?? '');
        $proto->setVideoUrl($catalog->video_url ?? '');
        $proto->setFeatures($catalog->features ?? []);
        $proto->setTheme($catalog->theme ?? '');
        $proto->setColor($catalog->color ?? '');
        $proto->setMinCapacity($catalog->min_capacity ?? 0);
        $proto->setMaxCapacity($catalog->max_capacity ?? 0);
        $proto->setAverageRating($catalog->average_rating ?? 0);
        $proto->setReviewCount($catalog->reviews_count ?? 0);
        $proto->setCreatedAt($catalog->created_at?->toISOString() ?? '');
        $proto->setUpdatedAt($catalog->updated_at?->toISOString() ?? '');
        
        if ($catalog->category) {
            $catProto = new \Weddingapp\Catalog\Category();
            $catProto->setId($catalog->category->id);
            $catProto->setName($catalog->category->name);
            $catProto->setSlug($catalog->category->slug);
            $catProto->setDescription($catalog->category->description ?? '');
            $catProto->setImageUrl($catalog->category->image_url ?? '');
            $catProto->setIsActive($catalog->category->is_active);
            $catProto->setCatalogsCount($catalog->category->catalogs_count ?? 0);
            $catProto->setPackagesCount($catalog->category->packages_count ?? 0);
            $catProto->setCreatedAt($catalog->category->created_at?->toISOString() ?? '');
            $proto->setCategory($catProto);
        }
        
        return $proto;
    }
}