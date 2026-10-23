<?php

namespace App\Http\GraphQL\Resolvers;

use App\Models\Catalog\Catalog;
use App\Models\Package\Package;
use App\Models\Category\Category;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class HomeResolver
{
    /**
     * Get all home page data
     */
    public function index($root, array $args, GraphQLContext $context)
    {
        return [
            'featuredCatalogs' => $this->featuredCatalogs($root, ['first' => 8], $context),
            'featuredPackages' => $this->featuredPackages($root, ['first' => 8], $context),
            'onSaleCatalogs' => $this->onSaleCatalogs($root, ['first' => 8], $context),
            'onSalePackages' => $this->onSalePackages($root, ['first' => 8], $context),
            'categories' => $this->categories($root, ['first' => 10], $context),
            'heroBanners' => $this->heroBanners($root, $args, $context),
            'promotions' => $this->promotions($root, ['first' => 5], $context),
            'stats' => $this->stats($root, $args, $context),
        ];
    }

    /**
     * Get featured catalogs
     */
    public function featuredCatalogs($root, array $args, GraphQLContext $context)
    {
        $first = $args['first'] ?? 8;
        
        return Catalog::where('is_featured', true)
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->with('category')
            ->latest()
            ->take($first)
            ->get();
    }

    /**
     * Get featured packages
     */
    public function featuredPackages($root, array $args, GraphQLContext $context)
    {
        $first = $args['first'] ?? 8;
        
        return Package::where('is_featured', true)
            ->where('is_active', true)
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
        $first = $args['first'] ?? 8;
        
        return Catalog::where('discount_price', '>', 0)
            ->where('is_active', true)
            ->where('stock', '>', 0)
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
        $first = $args['first'] ?? 8;
        
        return Package::where('discount_price', '>', 0)
            ->where('is_active', true)
            ->with('category')
            ->latest()
            ->take($first)
            ->get();
    }

    /**
     * Get categories for home
     */
    public function categories($root, array $args, GraphQLContext $context)
    {
        $first = $args['first'] ?? 10;
        
        return Category::where('is_active', true)
            ->whereNull('parent_id')
            ->withCount(['categoryCatalogs' => fn($q) => $q->where('is_active', true)->where('stock', '>', 0)])
            ->orderBy('name')
            ->take($first)
            ->get();
    }

    /**
     * Get hero banners
     */
    public function heroBanners($root, array $args, GraphQLContext $context)
    {
        return \App\Models\Home\HeroBanner::visible()
            ->get()
            ->map(function ($banner) {
                return [
                    'id' => $banner->id,
                    'title' => $banner->title,
                    'subtitle' => $banner->subtitle,
                    'imageUrl' => $banner->publicImageUrl(),
                    'linkUrl' => $banner->link_url,
                    'linkType' => $banner->link_type,
                    'linkId' => $banner->link_id,
                    'position' => $banner->position,
                    'isActive' => $banner->is_active,
                ];
            });
    }

    /**
     * Get promotions
     */
    public function promotions($root, array $args, GraphQLContext $context)
    {
        $first = $args['first'] ?? 5;
        
        return \App\Models\Home\Promotion::where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>=', now())
            ->orderBy('valid_from')
            ->take($first)
            ->get()
            ->map(function ($promo) {
                return [
                    'id' => $promo->id,
                    'title' => $promo->title,
                    'description' => $promo->description,
                    'imageUrl' => $promo->image_url,
                    'discountPercent' => $promo->discount_percent,
                    'code' => $promo->code,
                    'validFrom' => $promo->valid_from,
                    'validUntil' => $promo->valid_until,
                    'isActive' => $promo->is_active,
                ];
            });
    }

    /**
     * Get home stats
     */
    public function stats($root, array $args, GraphQLContext $context)
    {
        return [
            'totalCatalogs' => Catalog::where('is_active', true)->count(),
            'totalPackages' => Package::where('is_active', true)->count(),
            'totalCategories' => Category::where('is_active', true)->count(),
            'happyCustomers' => \App\Models\Order\Order::where('payment_status', 'paid')
                ->distinct('user_id')
                ->count('user_id'),
        ];
    }
}
