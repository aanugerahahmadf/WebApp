<?php

namespace App\Http\GraphQL\Resolvers;

use App\Models\Category\Category;
use Illuminate\Database\Eloquent\Builder;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class CategoryResolver
{
    /**
     * Get category by slug
     */
    public function bySlug($root, array $args, GraphQLContext $context)
    {
        return Category::where('slug', $args['slug'])->firstOrFail();
    }

    /**
     * Get all categories as flat list
     */
    public function all($root, array $args, GraphQLContext $context)
    {
        $query = Category::query();

        // The discriminator is `categories.type` (catalog|package). There is no
        // `is_active` column, so the old filter always threw 1054.
        if (isset($args['type'])) {
            $query->where('type', $args['type']);
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Get categories with catalog/package counts
     */
    public function withCounts($root, array $args, GraphQLContext $context)
    {
        $query = Category::query()
            ->withCount([
                'categoryCatalogs' => fn(Builder $q) => $q->where('is_active', true),
                'categoryPackages' => fn(Builder $q) => $q->where('is_active', true),
            ]);

        if (isset($args['type'])) {
            $query->where('type', $args['type']);
        }

        return $query->orderBy('name')
            ->get()
            ->map(function ($category) {
                return [
                    'category' => $category,
                    'catalogCount' => (int) $category->category_catalogs_count,
                    'packageCount' => (int) $category->category_packages_count,
                    'totalItems' => (int) $category->category_catalogs_count + (int) $category->category_packages_count,
                ];
            });
    }
}