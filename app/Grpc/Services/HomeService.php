<?php

namespace App\Grpc\Services;

use App\Models\Catalog\Catalog;
use App\Models\Package\Package;
use App\Models\Category\Category;
use App\Models\Order\Order;
use Grpc\ServerCall;
use Laravel\Sanctum\PersonalAccessToken;

class HomeService
{
    public function GetHomeData(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $featuredCatalogs = Catalog::with('category')
            ->where('is_featured', true)
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->latest()
            ->take(8)
            ->get();
        
        $featuredPackages = Package::with('category')
            ->where('is_featured', true)
            ->where('is_active', true)
            ->latest()
            ->take(8)
            ->get();
        
        $onSaleCatalogs = Catalog::with('category')
            ->where('discount_price', '>', 0)
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->latest()
            ->take(8)
            ->get();
        
        $onSalePackages = Package::with('category')
            ->where('discount_price', '>', 0)
            ->where('is_active', true)
            ->latest()
            ->take(8)
            ->get();
        
        $categories = Category::where('is_active', true)
            ->whereNull('parent_id')
            ->withCount(['categoryCatalogs' => fn($q) => $q->where('is_active', true)->where('stock', '>', 0)])
            ->orderBy('name')
            ->take(10)
            ->get();
        
        $heroBanners = \App\Models\Home\HeroBanner::where('is_active', true)
            ->orderBy('position')
            ->get();
        
        $promotions = \App\Models\Home\Promotion::where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>=', now())
            ->orderBy('valid_from')
            ->take(5)
            ->get();
        
        $stats = [
            'totalCatalogs' => Catalog::where('is_active', true)->count(),
            'totalPackages' => Package::where('is_active', true)->count(),
            'totalCategories' => Category::where('is_active', true)->count(),
            'happyCustomers' => Order::where('payment_status', 'paid')
                ->distinct('user_id')
                ->count('user_id'),
        ];
        
        $homeData = new \Weddingapp\Home\HomeData();
        
        foreach ($featuredCatalogs as $catalog) {
            $homeData->addFeaturedCatalogs($this->buildCatalogProto($catalog));
        }
        
        foreach ($featuredPackages as $package) {
            $homeData->addFeaturedPackages($this->buildPackageProto($package));
        }
        
        foreach ($onSaleCatalogs as $catalog) {
            $homeData->addOnSaleCatalogs($this->buildCatalogProto($catalog));
        }
        
        foreach ($onSalePackages as $package) {
            $homeData->addOnSalePackages($this->buildPackageProto($package));
        }
        
        foreach ($categories as $category) {
            $catProto = new \Weddingapp\Catalog\Category();
            $catProto->setId($category->id);
            $catProto->setName($category->name);
            $catProto->setSlug($category->slug);
            $catProto->setDescription($category->description ?? '');
            $catProto->setImageUrl($category->image_url ?? '');
            $catProto->setIsActive($category->is_active);
            $catProto->setCatalogsCount($category->catalogs_count);
            $catProto->setCreatedAt($category->created_at?->toISOString() ?? '');
            $homeData->addCategories($catProto);
        }
        
        foreach ($heroBanners as $banner) {
            $bannerProto = new \Weddingapp\Home\HeroBanner();
            $bannerProto->setId($banner->id);
            $bannerProto->setTitle($banner->title);
            $bannerProto->setSubtitle($banner->subtitle ?? '');
            $bannerProto->setImageUrl($banner->image_url);
            $bannerProto->setLinkUrl($banner->link_url ?? '');
            $bannerProto->setLinkType($banner->link_type ?? '');
            $bannerProto->setLinkId($banner->link_id ?? 0);
            $bannerProto->setPosition($banner->position);
            $bannerProto->setIsActive($banner->is_active);
            $homeData->addHeroBanners($bannerProto);
        }
        
        foreach ($promotions as $promo) {
            $promoProto = new \Weddingapp\Home\Promotion();
            $promoProto->setId($promo->id);
            $promoProto->setTitle($promo->title);
            $promoProto->setDescription($promo->description ?? '');
            $promoProto->setImageUrl($promo->image_url ?? '');
            $promoProto->setDiscountPercent($promo->discount_percent ?? 0);
            $promoProto->setCode($promo->code ?? '');
            $promoProto->setValidFrom($promo->valid_from?->toISOString() ?? '');
            $promoProto->setValidUntil($promo->valid_until?->toISOString() ?? '');
            $promoProto->setIsActive($promo->is_active);
            $homeData->addPromotions($promoProto);
        }
        
        $statsProto = new \Weddingapp\Home\HomeStats();
        $statsProto->setTotalCatalogs($stats['totalCatalogs']);
        $statsProto->setTotalPackages($stats['totalPackages']);
        $statsProto->setTotalCategories($stats['totalCategories']);
        $statsProto->setHappyCustomers($stats['happyCustomers']);
        $homeData->setStats($statsProto);
        
        $response = new \Weddingapp\Home\HomeDataResponse();
        $response->setData($homeData);
        
        return $call->sendResponse($response);
    }

    public function GetHeroBanners(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $banners = \App\Models\Home\HeroBanner::where('is_active', true)
            ->orderBy('position')
            ->get();
        
        $response = new \Weddingapp\Home\HeroBannersResponse();
        foreach ($banners as $banner) {
            $bannerProto = new \Weddingapp\Home\HeroBanner();
            $bannerProto->setId($banner->id);
            $bannerProto->setTitle($banner->title);
            $bannerProto->setSubtitle($banner->subtitle ?? '');
            $bannerProto->setImageUrl($banner->image_url);
            $bannerProto->setLinkUrl($banner->link_url ?? '');
            $bannerProto->setLinkType($banner->link_type ?? '');
            $bannerProto->setLinkId($banner->link_id ?? 0);
            $bannerProto->setPosition($banner->position);
            $bannerProto->setIsActive($banner->is_active);
            $response->addBanners($bannerProto);
        }
        
        return $call->sendResponse($response);
    }

    public function GetPromotions(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $limit = $request->getLimit() ?: 5;
        
        $promotions = \App\Models\Home\Promotion::where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>=', now())
            ->orderBy('valid_from')
            ->take($limit)
            ->get();
        
        $response = new \Weddingapp\Home\PromotionsResponse();
        foreach ($promotions as $promo) {
            $promoProto = new \Weddingapp\Home\Promotion();
            $promoProto->setId($promo->id);
            $promoProto->setTitle($promo->title);
            $promoProto->setDescription($promo->description ?? '');
            $promoProto->setImageUrl($promo->image_url ?? '');
            $promoProto->setDiscountPercent($promo->discount_percent ?? 0);
            $promoProto->setCode($promo->code ?? '');
            $promoProto->setValidFrom($promo->valid_from?->toISOString() ?? '');
            $promoProto->setValidUntil($promo->valid_until?->toISOString() ?? '');
            $promoProto->setIsActive($promo->is_active);
            $response->addPromotions($promoProto);
        }
        
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