<?php

namespace App\Grpc\Services;

use App\Models\Package\Package;
use App\Models\Category\Category;
use Grpc\ServerCall;
use Illuminate\Database\Eloquent\Builder;

class PackageService
{
    public function GetPackage(ServerCall $call, $request)
    {
        $package = Package::with('category')->find($request->getId());
        
        $response = new \Weddingapp\Package\PackageResponse();
        if ($package) {
            $response->setPackage($this->buildPackageProto($package));
        }
        return $call->sendResponse($response);
    }

    public function GetPackages(ServerCall $call, $request)
    {
        $query = Package::with('category');
        
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
        
        $sortBy = $request->getSortBy() ?: 'created_at';
        $sortOrder = $request->getSortOrder() ?: 'desc';
        $query->orderBy($sortBy, $sortOrder);
        
        $perPage = $request->getPerPage() ?: 10;
        $page = $request->getPage() ?: 1;
        
        $paginated = $query->paginate($perPage, ['*'], 'page', $page);
        
        $response = new \Weddingapp\Package\PackagesResponse();
        foreach ($paginated->items() as $package) {
            $response->addPackages($this->buildPackageProto($package));
        }
        
        $pagination = new \Weddingapp\Package\PaginationMeta();
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

    public function GetPackageBySlug(ServerCall $call, $request)
    {
        $package = Package::with('category')->where('slug', $request->getSlug())->first();
        
        $response = new \Weddingapp\Package\PackageResponse();
        if ($package) {
            $response->setPackage($this->buildPackageProto($package));
        }
        return $call->sendResponse($response);
    }

    public function GetFeaturedPackages(ServerCall $call, $request)
    {
        $limit = $request->getLimit() ?: 10;
        
        $packages = Package::with('category')
            ->where('is_featured', true)
            ->where('is_active', true)
            ->latest()
            ->take($limit)
            ->get();
        
        $response = new \Weddingapp\Package\PackagesResponse();
        foreach ($packages as $package) {
            $response->addPackages($this->buildPackageProto($package));
        }
        
        $pagination = new \Weddingapp\Package\PaginationMeta();
        $pagination->setCurrentPage(1);
        $pagination->setPerPage($limit);
        $pagination->setTotal($packages->count());
        $pagination->setLastPage(1);
        $pagination->setFrom(1);
        $pagination->setTo($packages->count());
        $pagination->setHasNextPage(false);
        $pagination->setHasPrevPage(false);
        $response->setPagination($pagination);
        
        return $call->sendResponse($response);
    }

    public function GetOnSalePackages(ServerCall $call, $request)
    {
        $limit = $request->getLimit() ?: 10;
        
        $packages = Package::with('category')
            ->where('discount_price', '>', 0)
            ->where('is_active', true)
            ->latest()
            ->take($limit)
            ->get();
        
        $response = new \Weddingapp\Package\PackagesResponse();
        foreach ($packages as $package) {
            $response->addPackages($this->buildPackageProto($package));
        }
        
        $pagination = new \Weddingapp\Package\PaginationMeta();
        $pagination->setCurrentPage(1);
        $pagination->setPerPage($limit);
        $pagination->setTotal($packages->count());
        $pagination->setLastPage(1);
        $pagination->setFrom(1);
        $pagination->setTo($packages->count());
        $pagination->setHasNextPage(false);
        $pagination->setHasPrevPage(false);
        $response->setPagination($pagination);
        
        return $call->sendResponse($response);
    }

    public function GetPackagesByCategory(ServerCall $call, $request)
    {
        $query = Package::with('category')
            ->where('category_id', $request->getCategoryId())
            ->where('is_active', true);
        
        $perPage = $request->getPerPage() ?: 10;
        $page = $request->getPage() ?: 1;
        
        $paginated = $query->latest()->paginate($perPage, ['*'], 'page', $page);
        
        $response = new \Weddingapp\Package\PackagesResponse();
        foreach ($paginated->items() as $package) {
            $response->addPackages($this->buildPackageProto($package));
        }
        
        $pagination = new \Weddingapp\Package\PaginationMeta();
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

    public function GetPackagesByCapacity(ServerCall $call, $request)
    {
        $query = Package::with('category')
            ->where('is_active', true)
            ->where('min_capacity', '<=', $request->getMaxCapacity())
            ->where('max_capacity', '>=', $request->getMinCapacity());
        
        $perPage = $request->getPerPage() ?: 10;
        $page = $request->getPage() ?: 1;
        
        $paginated = $query->latest()->paginate($perPage, ['*'], 'page', $page);
        
        $response = new \Weddingapp\Package\PackagesResponse();
        foreach ($paginated->items() as $package) {
            $response->addPackages($this->buildPackageProto($package));
        }
        
        $pagination = new \Weddingapp\Package\PaginationMeta();
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

    public function GetRelatedPackages(ServerCall $call, $request)
    {
        $limit = $request->getLimit() ?: 5;
        
        $package = Package::find($request->getPackageId());
        if (!$package) {
            $response = new \Weddingapp\Package\PackagesResponse();
            return $call->sendResponse($response);
        }
        
        $packages = Package::with('category')
            ->where('category_id', $package->category_id)
            ->where('id', '!=', $package->id)
            ->where('is_active', true)
            ->inRandomOrder()
            ->take($limit)
            ->get();
        
        $response = new \Weddingapp\Package\PackagesResponse();
        foreach ($packages as $package) {
            $response->addPackages($this->buildPackageProto($package));
        }
        
        $pagination = new \Weddingapp\Package\PaginationMeta();
        $pagination->setCurrentPage(1);
        $pagination->setPerPage($limit);
        $pagination->setTotal($packages->count());
        $pagination->setLastPage(1);
        $pagination->setFrom(1);
        $pagination->setTo($packages->count());
        $pagination->setHasNextPage(false);
        $pagination->setHasPrevPage(false);
        $response->setPagination($pagination);
        
        return $call->sendResponse($response);
    }

    public function SearchPackages(ServerCall $call, $request)
    {
        $query = Package::with('category')
            ->where('is_active', true)
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
        
        $response = new \Weddingapp\Package\PackagesResponse();
        foreach ($paginated->items() as $package) {
            $response->addPackages($this->buildPackageProto($package));
        }
        
        $pagination = new \Weddingapp\Package\PaginationMeta();
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

    protected function buildPackageProto(Package $package): \Weddingapp\Package\Package
    {
        $proto = new \Weddingapp\Package\Package();
        $proto->setId($package->id);
        $proto->setName($package->name);
        $proto->setSlug($package->slug);
        $proto->setDescription($package->description ?? '');
        $proto->setPrice($package->price);
        $proto->setDiscountPrice($package->discount_price ?? 0);
        $proto->setFinalPrice($package->final_price);
        $proto->setIsActive($package->is_active);
        $proto->setIsFeatured($package->is_featured);
        $proto->setImageUrl($package->image_url ?? '');
        $proto->setFeatures($package->features ?? []);
        $proto->setTheme($package->theme ?? '');
        $proto->setColor($package->color ?? '');
        $proto->setMinCapacity($package->min_capacity ?? 0);
        $proto->setMaxCapacity($package->max_capacity ?? 0);
        $proto->setAverageRating($package->average_rating ?? 0);
        $proto->setReviewCount($package->reviews_count ?? 0);
        $proto->setCreatedAt($package->created_at?->toISOString() ?? '');
        $proto->setUpdatedAt($package->updated_at?->toISOString() ?? '');
        
        if ($package->category) {
            $catProto = new \Weddingapp\Catalog\Category();
            $catProto->setId($package->category->id);
            $catProto->setName($package->category->name);
            $catProto->setSlug($package->category->slug);
            $catProto->setDescription($package->category->description ?? '');
            $catProto->setImageUrl($package->category->image_url ?? '');
            $catProto->setIsActive($package->category->is_active);
            $catProto->setCatalogsCount($package->category->catalogs_count ?? 0);
            $catProto->setPackagesCount($package->category->packages_count ?? 0);
            $catProto->setCreatedAt($package->category->created_at?->toISOString() ?? '');
            $proto->setCategory($catProto);
        }
        
        return $proto;
    }
}