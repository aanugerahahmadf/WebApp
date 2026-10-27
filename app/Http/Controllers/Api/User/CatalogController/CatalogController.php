<?php

namespace App\Http\Controllers\Api\User\CatalogController;

use App\Http\Controllers\Api\User\Controller\GraphQLDelegatingController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CatalogController extends GraphQLDelegatingController
{
    /**
     * List catalogs with filtering, sorting, and pagination.
     * Delegates to GraphQL `catalogs` query.
     */
    public function index(Request $request): JsonResponse
    {
        $query = '
            query GetCatalogs(
                $first: Int
                $filter: CatalogFilter
                $sort: CatalogSort
                $after: String
            ) {
                catalogs(first: $first, filter: $filter, sort: $sort, after: $after) {
                    data {
                        id
                        name
                        slug
                        description
                        price
                        discountPrice
                        finalPrice
                        stock
                        isActive
                        isFeatured
                        isOutOfStock
                        image_url: imageUrl
                        thumbnail_url: thumbnailUrl
                        gallery_urls: galleryUrls
                        videoUrl
                        features
                        theme
                        color
                        minCapacity
                        maxCapacity
                        averageRating
                        final_price
                         is_wishlisted
                         tiers {
                             id
                             catalogId
                             name
                             description
                             price
                             discountPrice
                             finalPrice
                             stock
                             features
                             sortOrder
                             imageUrl
                         }
                         category {
                            id
                            name
                            slug
                            description
                        }
                        createdAt
                        updatedAt
                    }
                    paginatorInfo {
                        currentPage
                        lastPage
                        perPage
                        total
                        hasMorePages
                    }
                }
            }
        ';
        
        $variables = [
            'first' => $request->get('per_page', 20) === 'all' ? 200 : (int) $request->get('per_page', 20),
            'filter' => $this->buildFilter($request),
            'sort' => $this->buildSort($request),
            'after' => $request->get('after'),
        ];
        
        // Remove null values
        $variables = array_filter($variables, fn($v) => $v !== null && $v !== []);
        if (isset($variables['filter']) && empty($variables['filter'])) {
            unset($variables['filter']);
        }
        if (isset($variables['sort']) && empty($variables['sort'])) {
            unset($variables['sort']);
        }
        
        return $this->graphqlRaw($query, $variables);
    }
    
    /**
     * Show single catalog by ID.
     * Delegates to GraphQL `catalog` query.
     */
    public function show($id): JsonResponse
    {
        $query = '
            query GetCatalog($id: ID!) {
                catalog(id: $id) {
                    id
                    name
                    slug
                    description
                    price
                    discountPrice
                    finalPrice
                    stock
                    isActive
                    isFeatured
                    isOutOfStock
                    image_url: imageUrl
                    thumbnail_url: thumbnailUrl
                    gallery_urls: galleryUrls
                    videoUrl
                    features
                    theme
                    color
                    minCapacity
                    maxCapacity
                    averageRating
                    final_price
                    is_wishlisted
                    category {
                        id
                        name
                        slug
                        description
                    }
                    reviews(first: 5) {
                        data {
                            id
                            userId
                            rating
                            title
                            comment
                            photos
                            helpfulCount
                            createdAt
                            user {
                                id
                                fullName: full_name
                                avatarUrl: avatar_url
                            }
                        }
                    }
                    tiers {
                        id
                        catalogId
                        name
                        description
                        price
                        discountPrice
                        finalPrice
                        stock
                        features
                        sortOrder
                        imageUrl
                    }
                    createdAt
                    updatedAt
                }
            }
        ';
        
        return $this->graphqlRaw($query, ['id' => $id]);
    }
    
    /**
     * Get tiers for a catalog.
     * Delegates to GraphQL `catalog` query with tiers.
     */
    public function tiers($id): JsonResponse
    {
        $query = '
            query GetCatalogTiers($id: ID!) {
                catalog(id: $id) {
                    id
                    name
                    tiers {
                        id
                        catalogId
                        name
                        description
                        price
                        discountPrice
                        finalPrice
                        stock
                        features
                        sortOrder
                        imageUrl
                    }
                }
            }
        ';
        
        return $this->graphqlRaw($query, ['id' => $id]);
    }
    
    /**
     * Get featured catalogs.
     * Delegates to GraphQL `featuredCatalogs` query.
     */
    public function featured(Request $request): JsonResponse
    {
        $query = '
            query GetFeaturedCatalogs($first: Int) {
                featuredCatalogs(first: $first) {
                    id
                    name
                    slug
                    description
                    price
                    discountPrice
                    finalPrice
                    stock
                    isActive
                    isFeatured
                    isOutOfStock
                    image_url: imageUrl
                    thumbnail_url: thumbnailUrl
                    gallery_urls: galleryUrls
                    videoUrl
                    features
                    theme
                    color
                    minCapacity
                    maxCapacity
                    averageRating
                    final_price
                    is_wishlisted
                    category {
                        id
                        name
                        slug
                        description
                    }
                    createdAt
                    updatedAt
                }
            }
        ';
        
        $perPage = min((int) $request->get('per_page', 10), 200);
        
        return $this->graphqlRaw($query, ['first' => $perPage]);
    }
    
    /**
     * Get on-sale catalogs.
     * Delegates to GraphQL `onSaleCatalogs` query.
     */
    public function onSale(Request $request): JsonResponse
    {
        $query = '
            query GetOnSaleCatalogs($first: Int) {
                onSaleCatalogs(first: $first) {
                    id
                    name
                    slug
                    description
                    price
                    discountPrice
                    finalPrice
                    stock
                    isActive
                    isFeatured
                    isOutOfStock
                    image_url: imageUrl
                    thumbnail_url: thumbnailUrl
                    gallery_urls: galleryUrls
                    videoUrl
                    features
                    theme
                    color
                    minCapacity
                    maxCapacity
                    averageRating
                    final_price
                    is_wishlisted
                    category {
                        id
                        name
                        slug
                        description
                    }
                    createdAt
                    updatedAt
                }
            }
        ';
        
        $perPage = min((int) $request->get('per_page', 10), 200);
        
        return $this->graphqlRaw($query, ['first' => $perPage]);
    }
    
    /**
     * Build filter variables from request.
     */
    private function buildFilter(Request $request): array
    {
        $filter = [];
        
        if ($request->filled('category_id')) {
            $filter['categoryId'] = $request->category_id;
        }
        
        if ($request->boolean('featured')) {
            $filter['featured'] = true;
        }
        
        if ($request->boolean('on_sale')) {
            $filter['onSale'] = true;
        }
        
        if ($request->filled('min_price')) {
            $filter['minPrice'] = (float) $request->min_price;
        }
        
        if ($request->filled('max_price')) {
            $filter['maxPrice'] = (float) $request->max_price;
        }
        
        if ($request->filled('active')) {
            $filter['active'] = $request->boolean('active');
        }
        
        if ($request->filled('theme')) {
            $filter['theme'] = $request->theme;
        }
        
        if ($request->filled('color')) {
            $filter['color'] = $request->color;
        }
        
        if ($request->filled('search')) {
            $filter['search'] = $request->search;
        }
        
        return $filter;
    }
    
    /**
     * Build sort variables from request.
     */
    private function buildSort(Request $request): array
    {
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        
        $allowedSortFields = ['name', 'price', 'created_at', 'discount_price', 'rating', 'popularity'];
        if (!in_array($sortBy, $allowedSortFields, true)) {
            $sortBy = 'created_at';
        }
        
        $direction = strtoupper($sortDirection);
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            $direction = 'DESC';
        }
        
        // Map REST sort fields to GraphQL enum values
        $fieldMap = [
            'name' => 'NAME',
            'price' => 'PRICE',
            'created_at' => 'CREATED_AT',
            'discount_price' => 'PRICE', // GraphQL doesn't have separate DISCOUNT_PRICE
            'rating' => 'RATING',
            'popularity' => 'POPULARITY',
        ];
        
        return [
            'field' => $fieldMap[$sortBy] ?? 'CREATED_AT',
            'direction' => $direction,
        ];
    }
}
