<?php

namespace App\Grpc\User\V1;

use App\Models\Category\Category;
use App\Models\Package\Package as PackageModel;
use Spiral\RoadRunner\GRPC\ContextInterface;

/**
 * Package gRPC service.
 *
 * Packages are not browsable standalone in the mobile app — they surface as
 * tiers inside a catalog and are read through `/api/catalog/{id}/tiers`. The
 * read endpoints here still exist for the admin panel and for GraphQL parity.
 */
class PackageService extends BaseGrpcService
{
    public const NAME = 'wedding.user.v1.PackageService';
    public function GetPackage(ContextInterface $context, GetPackageRequest $request): Package
    {
        try {
            return $this->transform(
                PackageModel::with(['category', 'reviews'])
                    ->findOrFail($request->id)
            );
        } catch (\Throwable $e) {
            throw new \Exception($e->getMessage(), $e->getCode() ?: 404);
        }
    }

    public function GetPackageBySlug(ContextInterface $context, GetPackageBySlugRequest $request): Package
    {
        try {
            return $this->transform(
                PackageModel::with(['category', 'reviews'])
                    ->where('slug', $request->slug)
                    ->firstOrFail()
            );
        } catch (\Throwable $e) {
            throw new \Exception($e->getMessage(), $e->getCode() ?: 404);
        }
    }

    public function ListPackages(ContextInterface $context, ListPackagesRequest $request): PackageListResponse
    {
        try {
            $query = PackageModel::with(['category', 'reviews'])->query();

            if ($request->category_id !== '') {
                $query->where('category_id', $request->category_id);
            }

            if ($request->featured) {
                $query->where('is_featured', true);
            }

            if ($request->min_price > 0) {
                $query->where('price', '>=', $request->min_price);
            }

            if ($request->max_price > 0) {
                $query->where('price', '<=', $request->max_price);
            }

            if ($request->active) {
                $query->where('is_active', true);
            }

            $sortBy = in_array($request->sort_by, ['name', 'price', 'created_at'], true)
                ? $request->sort_by
                : 'created_at';

            $direction = $request->sort_direction === SortDirection::SORT_DIRECTION_ASC
                ? 'asc'
                : 'desc';

            $query->orderBy($sortBy, $direction);

            $paginator = $query->paginate(
                $this->perPage($request->pagination, 10),
                ['*'],
                'page',
                $this->page($request->pagination)
            );

            return new PackageListResponse([
                'packages' => array_map([$this, 'transform'], $paginator->items()),
                'pagination' => $this->createPagination($paginator),
            ]);
        } catch (\Throwable $e) {
            throw new \Exception($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function GetFeaturedPackages(ContextInterface $context, GetFeaturedPackagesRequest $request): PackageListResponse
    {
        try {
            $packages = PackageModel::with(['category', 'reviews'])
                ->where('is_featured', true)
                ->where('is_active', true)
                ->limit($request->limit > 0 ? $request->limit : 10)
                ->get();

            return new PackageListResponse([
                'packages' => array_map([$this, 'transform'], $packages->toArray()),
                'pagination' => null,
            ]);
        } catch (\Throwable $e) {
            throw new \Exception($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function GetOnSalePackages(ContextInterface $context, GetOnSalePackagesRequest $request): PackageListResponse
    {
        try {
            $packages = PackageModel::with(['category', 'reviews'])
                ->whereNotNull('discount_price')
                ->where('discount_price', '<', 'price')
                ->where('is_active', true)
                ->limit($request->limit > 0 ? $request->limit : 10)
                ->get();

            return new PackageListResponse([
                'packages' => array_map([$this, 'transform'], $packages->toArray()),
                'pagination' => null,
            ]);
        } catch (\Throwable $e) {
            throw new \Exception($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function GetPackagesByCategory(ContextInterface $context, GetPackagesByCategoryRequest $request): PackageListResponse
    {
        try {
            $packages = PackageModel::with(['category', 'reviews'])
                ->where('category_id', $request->category_id)
                ->where('is_active', true)
                ->limit($request->limit > 0 ? $request->limit : 10)
                ->get();

            return new PackageListResponse([
                'packages' => array_map([$this, 'transform'], $packages->toArray()),
                'pagination' => null,
            ]);
        } catch (\Throwable $e) {
            throw new \Exception($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Filter packages whose guest-capacity window overlaps the requested one.
     */
    public function GetPackagesByCapacity(ContextInterface $context, GetPackagesByCapacityRequest $request): PackageListResponse
    {
        try {
            $query = PackageModel::with(['category', 'reviews'])->where('is_active', true);

            $query->where('max_capacity', '>=', $request->min_capacity);

            if ($request->max_capacity > 0) {
                $query->where('min_capacity', '<=', $request->max_capacity);
            }

            $packages = $query->limit($request->limit > 0 ? $request->limit : 10)->get();

            return new PackageListResponse([
                'packages' => array_map([$this, 'transform'], $packages->toArray()),
                'pagination' => null,
            ]);
        } catch (\Throwable $e) {
            throw new \Exception($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function GetRelatedPackages(ContextInterface $context, GetRelatedPackagesRequest $request): PackageListResponse
    {
        try {
            $package = PackageModel::findOrFail($request->package_id);

            $packages = PackageModel::with(['category', 'reviews'])
                ->where('category_id', $package->category_id)
                ->where('id', '!=', $package->id)
                ->where('is_active', true)
                ->limit($request->limit > 0 ? $request->limit : 10)
                ->get();

            return new PackageListResponse([
                'packages' => array_map([$this, 'transform'], $packages->toArray()),
                'pagination' => null,
            ]);
        } catch (\Throwable $e) {
            throw new \Exception($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function SearchPackages(ContextInterface $context, SearchPackagesRequest $request): PackageListResponse
    {
        try {
            $packages = PackageModel::with(['category', 'reviews'])
                ->where('is_active', true)
                ->where(function ($q) use ($request) {
                    $q->where('name', 'like', '%'.$request->query.'%')
                        ->orWhere('description', 'like', '%'.$request->query.'%');
                })
                ->limit($request->limit > 0 ? $request->limit : 10)
                ->get();

            return new PackageListResponse([
                'packages' => array_map([$this, 'transform'], $packages->toArray()),
                'pagination' => null,
            ]);
        } catch (\Throwable $e) {
            throw new \Exception($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function GetPackageStats(ContextInterface $context, GetPackageStatsRequest $request): PackageStats
    {
        try {
            $base = PackageModel::query();

            $byCategory = Category::query()
                ->get()
                ->map(function (Category $category) use ($base) {
                    $count = (clone $base)->where('category_id', $category->id)->count();

                    return new CategoryPackageCount([
                        'category' => new Category([
                            'id' => (string) $category->id,
                            'name' => $category->name,
                            'slug' => $category->slug,
                            'is_active' => (bool) $category->is_active,
                            'created_at' => $this->formatTimestamp($category->created_at),
                            'updated_at' => $this->formatTimestamp($category->updated_at),
                        ]),
                        'count' => $count,
                    ]);
                })
                ->filter(fn (CategoryPackageCount $item) => $item->getCount() > 0)
                ->values()
                ->all();

            $byCapacity = collect([
                ['min' => 0, 'max' => 100, 'count' => (clone $base)->where('max_capacity', '<=', 100)->count()],
                ['min' => 101, 'max' => 300, 'count' => (clone $base)->whereBetween('max_capacity', [101, 300])->count()],
                ['min' => 301, 'max' => 500, 'count' => (clone $base)->whereBetween('max_capacity', [301, 500])->count()],
                ['min' => 501, 'max' => 0, 'count' => (clone $base)->where('max_capacity', '>', 501)->count()],
            ])->map(fn (array $bucket) => new CapacityPackageCount($bucket))->all();

            return new PackageStats([
                'total_packages' => (clone $base)->count(),
                'active_packages' => (clone $base)->where('is_active', true)->count(),
                'featured_packages' => (clone $base)->where('is_featured', true)->count(),
                'on_sale_packages' => (clone $base)->whereNotNull('discount_price')->whereColumn('discount_price', '<', 'price')->count(),
                'total_value' => (float) (clone $base)->sum('price'),
                'by_category' => $byCategory,
                'by_capacity' => $byCapacity,
            ]);
        } catch (\Throwable $e) {
            throw new \Exception($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    // -----------------------------------------------------------------

    private function transform(Package $package): Package
    {
        $locale = app()->getLocale();

        return new Package([
            'id' => (string) $package->id,
            'name' => (string) $package->trans('name', $locale),
            'slug' => (string) $package->slug,
            'description' => (string) $package->trans('description', $locale),
            'price' => (float) $package->price,
            'discount_price' => (float) $package->discount_price,
            'final_price' => (float) $package->final_price,
            'is_active' => (bool) $package->is_active,
            'is_featured' => (bool) $package->is_featured,
            'image_url' => (string) ($package->image_url ?? ''),
            'features' => $package->features ?? [],
            'theme' => (string) ($package->theme ?? ''),
            'color' => (string) ($package->color ?? ''),
            'min_capacity' => (int) ($package->min_capacity ?? 0),
            'max_capacity' => (int) ($package->max_capacity ?? 0),
            'category' => $package->category ? $this->transformCategory($package->category) : null,
            'average_rating' => (float) $package->average_rating,
            'created_at' => $this->formatTimestamp($package->created_at),
            'updated_at' => $this->formatTimestamp($package->updated_at),
        ]);
    }

    private function transformCategory(Category $category): Category
    {
        return new Category([
            'id' => (string) $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description ?? '',
            'image_url' => $category->image_url ?? '',
            'is_active' => (bool) $category->is_active,
            'created_at' => $this->formatTimestamp($category->created_at),
            'updated_at' => $this->formatTimestamp($category->updated_at),
        ]);
    }

    private function perPage(?PaginationRequest $pagination, int $default): int
    {
        $perPage = $pagination?->getPerPage() ?: 0;

        return $perPage > 0 ? min($perPage, 200) : $default;
    }

    private function page(?PaginationRequest $pagination): int
    {
        return ($pagination?->getPage() ?: 0) > 0 ? $pagination->getPage() : 1;
    }
}
