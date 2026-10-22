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

        if (isset($args['active'])) {
            $query->where('is_active', $args['active']);
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Get category tree (hierarchical)
     */
    public function tree($root, array $args, GraphQLContext $context)
    {
        $categories = Category::where('is_active', true)
            ->with('children')
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();

        return $categories->map(function ($category) {
            return $this->buildTree($category);
        });
    }

    /**
     * Recursively build category tree
     */
    private function buildTree(Category $category): array
    {
        $children = $category->children
            ->where('is_active', true)
            ->sortBy('name')
            ->map(function ($child) {
                return $this->buildTree($child);
            })
            ->values()
            ->toArray();

        return [
            'category' => $category,
            'children' => $children,
        ];
    }

    /**
     * Get categories with catalog/package counts
     */
    public function withCounts($root, array $args, GraphQLContext $context)
    {
        $active = $args['active'] ?? true;

        return Category::query()
            ->when($active, fn(Builder $q) => $q->where('is_active', true))
            ->withCount([
                'categoryCatalogs' => fn(Builder $q) => $q->where('is_active', true),
                'categoryPackages' => fn(Builder $q) => $q->where('is_active', true),
            ])
            ->orderBy('name')
            ->get()
            ->map(function ($category) {
                return [
                    'category' => $category,
                    'catalogCount' => $category->category_catalogs_count,
                    'packageCount' => $category->packages_count,
                    'totalItems' => $category->category_catalogs_count + $category->packages_count,
                ];
            });
    }
}