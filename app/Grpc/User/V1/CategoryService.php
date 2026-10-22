<?php

namespace App\Grpc\User\V1;

use App\Http\GraphQL\Resolvers\CategoryResolver;
use App\Models\Category\Category;
use Illuminate\Support\Facades\Log;
use Spiral\RoadRunner\GRPC\ContextInterface;

/**
 * Category gRPC Service Implementation
 */
class CategoryService extends BaseGrpcService
{
    private CategoryResolver $categoryResolver;
    
    public function __construct(CategoryResolver $categoryResolver)
    {
        $this->categoryResolver = $categoryResolver;
    }
    
    /**
     * Get category by ID
     */
    public function GetCategory(GetCategoryRequest $request, ContextInterface $context): Category
    {
        try {
            $category = Category::findOrFail($request->id);
            return $this->transformCategory($category);
        } catch (\Throwable $e) {
            $error = $this->handleError($e, 'GetCategory');
            throw new \Exception($error['message'], $e->getCode() ?: 404);
        }
    }
    
    /**
     * Get category by slug
     */
    public function GetCategoryBySlug(GetCategoryBySlugRequest $request, ContextInterface $context): Category
    {
        try {
            $category = Category::where('slug', $request->slug)->firstOrFail();
            return $this->transformCategory($category);
        } catch (\Throwable $e) {
            $error = $this->handleError($e, 'GetCategoryBySlug');
            throw new \Exception($error['message'], $e->getCode() ?: 404);
        }
    }
    
    /**
     * List categories with pagination
     */
    public function ListCategories(ListCategoriesRequest $request, ContextInterface $context): CategoryListResponse
    {
        try {
            $query = Category::query();
            
            if ($request->active) {
                $query->where('is_active', true);
            }
            
            if ($request->parent_id) {
                $query->where('parent_id', $request->parent_id);
            } else {
                $query->whereNull('parent_id');
            }
            
            $pagination = $request->pagination;
            $perPage = $pagination && $pagination->per_page > 0 ? $pagination->per_page : 20;
            $page = $pagination && $pagination->page > 0 ? $pagination->page : 1;
            
            $paginator = $query->with('children')->paginate($perPage, ['*'], 'page', $page);
            
            return new CategoryListResponse([
                'categories' => array_map([$this, 'transformCategory'], $paginator->items()),
                'pagination' => $this->createPagination($paginator),
            ]);
        } catch (\Throwable $e) {
            $error = $this->handleError($e, 'ListCategories');
            throw new \Exception($error['message'], $e->getCode() ?: 500);
        }
    }
    
    /**
     * Get all categories (no pagination)
     */
    public function GetAllCategories(GetAllCategoriesRequest $request, ContextInterface $context): CategoryListResponse
    {
        try {
            $query = Category::query();
            
            if ($request->active) {
                $query->where('is_active', true);
            }
            
            $categories = $query->with('children')->get();
            
            return new CategoryListResponse([
                'categories' => array_map([$this, 'transformCategory'], $categories->toArray()),
                'pagination' => null,
            ]);
        } catch (\Throwable $e) {
            $error = $this->handleError($e, 'GetAllCategories');
            throw new \Exception($error['message'], $e->getCode() ?: 500);
        }
    }
    
    /**
     * Get category tree
     */
    public function GetCategoryTree(GetCategoryTreeRequest $request, ContextInterface $context): CategoryTreeResponse
    {
        try {
            $tree = $this->categoryResolver->tree(null, [], $context);
            
            return new CategoryTreeResponse([
                'categories' => array_map([$this, 'transformCategoryTree'], $tree),
            ]);
        } catch (\Throwable $e) {
            $error = $this->handleError($e, 'GetCategoryTree');
            throw new \Exception($error['message'], $e->getCode() ?: 500);
        }
    }
    
    /**
     * Get categories with counts
     */
    public function GetCategoriesWithCounts(GetCategoriesWithCountsRequest $request, ContextInterface $context): CategoryWithCountsResponse
    {
        try {
            $categories = $this->categoryResolver->withCounts(null, ['active' => $request->active], $context);
            
            return new CategoryWithCountsResponse([
                'categories' => array_map([$this, 'transformCategoryWithCounts'], $categories),
            ]);
        } catch (\Throwable $e) {
            $error = $this->handleError($e, 'GetCategoriesWithCounts');
            throw new \Exception($error['message'], $e->getCode() ?: 500);
        }
    }
    
    /**
     * Transform Category model to protobuf Category message
     */
    private function transformCategory(Category $category): Category
    {
        $parent = $category->parent ? $this->transformCategory($category->parent) : null;
        $children = $category->children->map([$this, 'transformCategory'])->toArray();
        
        return new Category([
            'id' => (string) $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description ?? '',
            'image_url' => $category->image_url ?? '',
            'is_active' => $category->is_active,
            'parent' => $parent,
            'children' => $children,
            'created_at' => $this->formatTimestamp($category->created_at),
            'updated_at' => $this->formatTimestamp($category->updated_at),
        ]);
    }
    
    /**
     * Transform category tree node
     */
    private function transformCategoryTree(array $node): CategoryTree
    {
        $category = Category::find($node['id']);
        $children = isset($node['children']) ? array_map([$this, 'transformCategoryTree'], $node['children']) : [];
        
        return new CategoryTree([
            'category' => $category ? $this->transformCategory($category) : new Category(['id' => (string) $node['id']]),
            'children' => $children,
        ]);
    }
    
    /**
     * Transform category with counts
     */
    private function transformCategoryWithCounts(array $data): CategoryWithCounts
    {
        $category = Category::find($data['category_id'] ?? $data['id']);
        
        return new CategoryWithCounts([
            'category' => $category ? $this->transformCategory($category) : new Category(['id' => (string) ($data['category_id'] ?? $data['id'])]),
            'catalog_count' => $data['catalog_count'] ?? 0,
            'package_count' => $data['package_count'] ?? 0,
            'total_items' => $data['total_items'] ?? 0,
        ]);
    }
}