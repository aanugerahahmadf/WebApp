<?php

namespace App\Grpc\User\V1;

use App\Http\GraphQL\Resolvers\CatalogResolver;
use App\Models\Catalog\Catalog as CatalogModel;
use App\Models\Category\Category;
use Illuminate\Support\Facades\Log;
use Spiral\RoadRunner\GRPC\ContextInterface;

/**
 * Catalog gRPC Service Implementation
 */
class CatalogService extends BaseGrpcService
{
    public const NAME = 'wedding.user.v1.CatalogService';
    private CatalogResolver $catalogResolver;
    
    public function __construct(CatalogResolver $catalogResolver)
    {
        $this->catalogResolver = $catalogResolver;
    }
    
    /**
     * Get catalog by ID
     */
    public function GetCatalog(ContextInterface $context, GetCatalogRequest $request): Catalog
    {
        try {
            $catalog = CatalogModel::with(['category', 'reviews', 'media', 'tiers.media'])->findOrFail($request->id);
            return $this->transformCatalog($catalog);
        } catch (\Throwable $e) {
            $error = $this->handleError($e, 'GetCatalog');
            throw new \Exception($error['message'], $e->getCode() ?: 404);
        }
    }
    
    /**
     * Get catalog by slug
     */
    public function GetCatalogBySlug(ContextInterface $context, GetCatalogBySlugRequest $request): Catalog
    {
        try {
            $catalog = CatalogModel::with(['category', 'reviews', 'media', 'tiers.media'])
                ->where('slug', $request->slug)
                ->firstOrFail();
            return $this->transformCatalog($catalog);
        } catch (\Throwable $e) {
            $error = $this->handleError($e, 'GetCatalogBySlug');
            throw new \Exception($error['message'], $e->getCode() ?: 404);
        }
    }
    
    /**
     * List catalogs with pagination and filters
     */
    public function ListCatalogs(ContextInterface $context, ListCatalogsRequest $request): CatalogListResponse
    {
        try {
            $query = CatalogModel::with(['category', 'reviews', 'media']);
            
            if ($request->category_id) {
                $query->where('category_id', $request->category_id);
            }
            
            if ($request->theme) {
                $query->where('theme', 'like', '%' . $request->theme . '%');
            }
            
            if ($request->min_price > 0) {
                $query->where('price', '>=', $request->min_price);
            }
            
            if ($request->max_price > 0) {
                $query->where('price', '<=', $request->max_price);
            }
            
            if ($request->search) {
                $searchTerm = $request->search;
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('name', 'like', '%' . $searchTerm . '%')
                      ->orWhere('description', 'like', '%' . $searchTerm . '%');
                });
            }
            
            if ($request->active) {
                $query->where('is_active', true);
            }
            
            if ($request->with_tier_count) {
                $query->withCount('tiers');
            }
            
            $sortBy = $request->sort_by ?: 'created_at';
            $allowedSortFields = ['name', 'price', 'created_at', 'discount_price'];
            if (!in_array($sortBy, $allowedSortFields)) {
                $sortBy = 'created_at';
            }
            
            $sortDirection = $request->sort_direction === \App\Grpc\User\V1\SortDirection::SORT_DIRECTION_ASC ? 'asc' : 'desc';
            
            $query->orderBy($sortBy, $sortDirection);
            
            $pagination = $request->pagination;
            $perPage = $pagination && $pagination->per_page > 0 ? min($pagination->per_page, 200) : 20;
            $page = $pagination && $pagination->page > 0 ? $pagination->page : 1;
            
            $paginator = $query->paginate($perPage, ['*'], 'page', $page);
            
            return new CatalogListResponse([
                'catalogs' => array_map([$this, 'transformCatalog'], $paginator->items()),
                'pagination' => $this->createPagination($paginator),
            ]);
        } catch (\Throwable $e) {
            $error = $this->handleError($e, 'ListCatalogs');
            throw new \Exception($error['message'], $e->getCode() ?: 500);
        }
    }
    
    /**
     * Get featured catalogs
     */
    public function GetFeaturedCatalogs(ContextInterface $context, GetFeaturedCatalogsRequest $request): CatalogListResponse
    {
        try {
            $limit = $request->limit > 0 ? $request->limit : 10;
            $catalogs = CatalogModel::with(['category', 'reviews', 'media'])
                ->where('is_featured', true)
                ->where('is_active', true)
                ->limit($limit)
                ->get();
            
            return new CatalogListResponse([
                'catalogs' => array_map([$this, 'transformCatalog'], $catalogs->toArray()),
                'pagination' => null,
            ]);
        } catch (\Throwable $e) {
            $error = $this->handleError($e, 'GetFeaturedCatalogs');
            throw new \Exception($error['message'], $e->getCode() ?: 500);
        }
    }
    
    /**
     * Get on-sale catalogs
     */
    public function GetOnSaleCatalogs(ContextInterface $context, GetOnSaleCatalogsRequest $request): CatalogListResponse
    {
        try {
            $limit = $request->limit > 0 ? $request->limit : 10;
            $catalogs = CatalogModel::with(['category', 'reviews', 'media'])
                ->whereNotNull('discount_price')
                ->where('discount_price', '<', 'price')
                ->where('is_active', true)
                ->limit($limit)
                ->get();
            
            return new CatalogListResponse([
                'catalogs' => array_map([$this, 'transformCatalog'], $catalogs->toArray()),
                'pagination' => null,
            ]);
        } catch (\Throwable $e) {
            $error = $this->handleError($e, 'GetOnSaleCatalogs');
            throw new \Exception($error['message'], $e->getCode() ?: 500);
        }
    }
    
    /**
     * Get catalogs by category
     */
    public function GetCatalogsByCategory(ContextInterface $context, GetCatalogsByCategoryRequest $request): CatalogListResponse
    {
        try {
            $limit = $request->limit > 0 ? $request->limit : 10;
            $catalogs = CatalogModel::with(['category', 'reviews', 'media'])
                ->where('category_id', $request->category_id)
                ->where('is_active', true)
                ->limit($limit)
                ->get();
            
            return new CatalogListResponse([
                'catalogs' => array_map([$this, 'transformCatalog'], $catalogs->toArray()),
                'pagination' => null,
            ]);
        } catch (\Throwable $e) {
            $error = $this->handleError($e, 'GetCatalogsByCategory');
            throw new \Exception($error['message'], $e->getCode() ?: 500);
        }
    }
    
    /**
     * Get related catalogs
     */
    public function GetRelatedCatalogs(ContextInterface $context, GetRelatedCatalogsRequest $request): CatalogListResponse
    {
        try {
            $limit = $request->limit > 0 ? $request->limit : 10;
            $catalog = CatalogModel::findOrFail($request->catalog_id);
            
            $catalogs = CatalogModel::with(['category', 'reviews', 'media'])
                ->where('category_id', $catalog->category_id)
                ->where('id', '!=', $catalog->id)
                ->where('is_active', true)
                ->limit($limit)
                ->get();
            
            return new CatalogListResponse([
                'catalogs' => array_map([$this, 'transformCatalog'], $catalogs->toArray()),
                'pagination' => null,
            ]);
        } catch (\Throwable $e) {
            $error = $this->handleError($e, 'GetRelatedCatalogs');
            throw new \Exception($error['message'], $e->getCode() ?: 500);
        }
    }
    
    /**
     * Search catalogs
     */
    public function SearchCatalogs(ContextInterface $context, SearchCatalogsRequest $request): CatalogListResponse
    {
        try {
            $limit = $request->limit > 0 ? $request->limit : 10;
            $catalogs = CatalogModel::with(['category', 'reviews', 'media'])
                ->where('is_active', true)
                ->where(function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->query . '%')
                      ->orWhere('description', 'like', '%' . $request->query . '%');
                })
                ->limit($limit)
                ->get();
            
            return new CatalogListResponse([
                'catalogs' => array_map([$this, 'transformCatalog'], $catalogs->toArray()),
                'pagination' => null,
            ]);
        } catch (\Throwable $e) {
            $error = $this->handleError($e, 'SearchCatalogs');
            throw new \Exception($error['message'], $e->getCode() ?: 500);
        }
    }
    
    /**
     * Get catalog tiers
     */
    public function GetCatalogTiers(ContextInterface $context, GetCatalogTiersRequest $request): CatalogTiersResponse
    {
        try {
            $catalog = CatalogModel::with('tiers.media')->findOrFail($request->catalog_id);
            
            $tiers = $catalog->tiers->map(function ($tier) use ($catalog) {
                $tierMedia = $tier->getRelationValue('media');
                $imageUrl = ($tierMedia instanceof \Illuminate\Support\Collection && $tierMedia->isNotEmpty())
                    ? $tier->image_url
                    : $catalog->thumbnail_url;
                
                return new CatalogTier([
                    'id' => (string) $tier->id,
                    'catalog_id' => (string) $tier->catalog_id,
                    'name' => $tier->trans('name', app()->getLocale()) ?? $tier->name,
                    'description' => $tier->trans('description', app()->getLocale()) ?? $tier->description,
                    'price' => (float) $tier->price,
                    'discount_price' => $tier->discount_price !== null ? (float) $tier->discount_price : 0,
                    'final_price' => $tier->final_price,
                    'stock' => (int) $tier->stock,
                    'features' => $tier->features ?? [],
                    'sort_order' => (int) ($tier->sort_order ?? 0),
                    'image_url' => $imageUrl,
                ]);
            })->values()->toArray();
            
            return new CatalogTiersResponse([
                'tiers' => $tiers,
                'meta' => new CatalogMeta([
                    'catalog_id' => (string) $catalog->id,
                    'catalog_name' => $catalog->trans('name', app()->getLocale()) ?? $catalog->name,
                    'total' => count($tiers),
                ]),
            ]);
        } catch (\Throwable $e) {
            $error = $this->handleError($e, 'GetCatalogTiers');
            throw new \Exception($error['message'], $e->getCode() ?: 500);
        }
    }
    
    /**
     * Get catalog stats
     */
    public function GetCatalogStats(ContextInterface $context, GetCatalogStatsRequest $request): CatalogStats
    {
        try {
            $stats = $this->catalogResolver->stats(null, [], $context);
            
            $byCategory = [];
            if (isset($stats['by_category'])) {
                foreach ($stats['by_category'] as $item) {
                    $category = Category::find($item['category_id']);
                    if ($category) {
                        $byCategory[] = new CategoryCatalogCount([
                            'category' => $this->transformCategory($category),
                            'count' => $item['count'],
                        ]);
                    }
                }
            }
            
            return new CatalogStats([
                'total_catalogs' => $stats['total_catalogs'] ?? 0,
                'active_catalogs' => $stats['active_catalogs'] ?? 0,
                'featured_catalogs' => $stats['featured_catalogs'] ?? 0,
                'out_of_stock_catalogs' => $stats['out_of_stock_catalogs'] ?? 0,
                'low_stock_catalogs' => $stats['low_stock_catalogs'] ?? 0,
                'total_value' => $stats['total_value'] ?? 0,
                'by_category' => $byCategory,
            ]);
        } catch (\Throwable $e) {
            $error = $this->handleError($e, 'GetCatalogStats');
            throw new \Exception($error['message'], $e->getCode() ?: 500);
        }
    }
    
    /**
     * Transform Catalog model to protobuf Catalog message
     */
    private function transformCatalog(CatalogModel $catalog): Catalog
    {
        $locale = app()->getLocale();
        
        $category = $catalog->category ? $this->transformCategory($catalog->category) : null;
        $reviews = $catalog->reviews->map(function ($review) {
            return new \App\Grpc\User\V1\Review([
                'id' => (string) $review->id,
                'user_id' => (string) $review->user_id,
                'catalog_id' => (string) $review->catalog_id,
                'package_id' => (string) $review->package_id,
                'rating' => $review->rating,
                'title' => $review->title ?? '',
                'comment' => $review->comment ?? '',
                'photos' => $review->photos ?? [],
                'helpful_count' => $review->helpful_count,
                'is_anonymous' => $review->is_anonymous,
                'is_verified_purchase' => $review->is_verified_purchase,
                'status' => $this->mapReviewStatus($review->status),
                'reply' => $review->reply ? new \App\Grpc\User\V1\ReviewReply([
                    'id' => (string) $review->reply->id,
                    'review_id' => (string) $review->reply->review_id,
                    'content' => $review->reply->content,
                    'created_by' => $this->transformUser($review->reply->createdBy),
                    'created_at' => $this->formatTimestamp($review->reply->created_at),
                ]) : null,
                'created_at' => $this->formatTimestamp($review->created_at),
                'updated_at' => $this->formatTimestamp($review->updated_at),
            ]);
        })->toArray();
        
        $tiers = $catalog->tiers->map(function ($tier) use ($catalog) {
            $tierMedia = $tier->getRelationValue('media');
            $imageUrl = ($tierMedia instanceof \Illuminate\Support\Collection && $tierMedia->isNotEmpty())
                ? $tier->image_url
                : $catalog->thumbnail_url;
            
            return new CatalogTier([
                'id' => (string) $tier->id,
                'catalog_id' => (string) $tier->catalog_id,
                'name' => $tier->trans('name', $locale) ?? $tier->name,
                'description' => $tier->trans('description', $locale) ?? $tier->description,
                'price' => (float) $tier->price,
                'discount_price' => $tier->discount_price !== null ? (float) $tier->discount_price : 0,
                'final_price' => $tier->final_price,
                'stock' => (int) $tier->stock,
                'features' => $tier->features ?? [],
                'sort_order' => (int) ($tier->sort_order ?? 0),
                'image_url' => $imageUrl,
            ]);
        })->values()->toArray();
        
        return new Catalog([
            'id' => (string) $catalog->id,
            'name' => $catalog->trans('name', $locale),
            'slug' => $catalog->slug,
            'description' => $catalog->trans('description', $locale) ?? '',
            'price' => (float) $catalog->price,
            'discount_price' => (float) $catalog->discount_price,
            'final_price' => (float) $catalog->final_price,
            'stock' => (int) $catalog->stock,
            'is_active' => $catalog->is_active,
            'is_featured' => $catalog->is_featured,
            'is_out_of_stock' => $catalog->is_out_of_stock,
            'image_url' => $catalog->image_url ?? '',
            'video_url' => $catalog->video_url ?? '',
            'features' => $catalog->features ?? [],
            'theme' => $catalog->theme ?? '',
            'color' => $catalog->color ?? '',
            'min_capacity' => $catalog->min_capacity ?? 0,
            'max_capacity' => $catalog->max_capacity ?? 0,
            'category' => $category,
            'average_rating' => (float) $catalog->average_rating,
            'reviews' => $reviews,
            'tiers' => $tiers,
            'created_at' => $this->formatTimestamp($catalog->created_at),
            'updated_at' => $this->formatTimestamp($catalog->updated_at),
        ]);
    }
    
    /**
     * Transform Category model to protobuf Category message
     */
    private function transformCategory(Category $category): Category
    {
        return new Category([
            'id' => (string) $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description ?? '',
            'image_url' => $category->image_url ?? '',
            'is_active' => $category->is_active,
            'parent' => null, // Avoid recursion
            'children' => [],
            'created_at' => $this->formatTimestamp($category->created_at),
            'updated_at' => $this->formatTimestamp($category->updated_at),
        ]);
    }
    
    /**
     * Transform User model to protobuf User message
     */
    private function transformUser($user): \App\Grpc\User\V1\User
    {
        return new \App\Grpc\User\V1\User([
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone ?? '',
            'avatar' => $user->avatar ?? '',
            'email_verified_at' => $this->formatTimestamp($user->email_verified_at),
            'address' => $user->address ?? '',
            'city' => $user->city ?? '',
            'province' => $user->province ?? '',
            'postal_code' => $user->postal_code ?? '',
            'date_of_birth' => $this->formatTimestamp($user->date_of_birth),
            'gender' => $this->mapGender($user->gender),
            'notification_email' => $user->notification_email ?? true,
            'notification_sms' => $user->notification_sms ?? true,
            'notification_push' => $user->notification_push ?? true,
            'language' => $user->language ?? 'id',
            'currency' => $user->currency ?? 'IDR',
            'timezone' => $user->timezone ?? 'Asia/Jakarta',
            'roles' => $user->getRoleNames()->toArray(),
            'created_at' => $this->formatTimestamp($user->created_at),
            'updated_at' => $this->formatTimestamp($user->updated_at),
            'profile_completion' => $user->profile_completion ?? 0.0,
        ]);
    }
    
    /**
     * Map gender string to protobuf enum
     */
    private function mapGender(?string $gender): int
    {
        return match ($gender) {
            'male' => \App\Grpc\User\V1\Gender::GENDER_MALE,
            'female' => \App\Grpc\User\V1\Gender::GENDER_FEMALE,
            'other' => \App\Grpc\User\V1\Gender::GENDER_OTHER,
            'prefer_not_to_say' => \App\Grpc\User\V1\Gender::GENDER_PREFER_NOT_TO_SAY,
            default => \App\Grpc\User\V1\Gender::GENDER_UNSPECIFIED,
        };
    }
    
    /**
     * Map review status to protobuf enum
     */
    private function mapReviewStatus(string $status): int
    {
        return match ($status) {
            'pending' => \App\Grpc\User\V1\ReviewStatus::REVIEW_STATUS_PENDING,
            'approved' => \App\Grpc\User\V1\ReviewStatus::REVIEW_STATUS_APPROVED,
            'rejected' => \App\Grpc\User\V1\ReviewStatus::REVIEW_STATUS_REJECTED,
            'hidden' => \App\Grpc\User\V1\ReviewStatus::REVIEW_STATUS_HIDDEN,
            default => \App\Grpc\User\V1\ReviewStatus::REVIEW_STATUS_UNSPECIFIED,
        };
    }
}
