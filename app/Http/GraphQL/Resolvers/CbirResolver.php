<?php

namespace App\Http\GraphQL\Resolvers;

use App\Models\Catalog\Catalog;
use App\Models\Package\Package;
use App\Models\Category\Category;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class CbirResolver
{
    /**
     * Get CBIR index statistics
     */
    public function stats($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        if (! $user || ! $user->hasRole('admin')) {
            throw new \Exception('Unauthorized');
        }

        // ─── gRPC terlebih dahulu ─────────────────────────────────────
        // `AiCoreGrpcClient::stats()` memanggil `GetStats` di server gRPC.
        // HTTP `/status` (Flask) dipakai sebagai cadangan kalau server gRPC
        // tidak jalan atau extension "grpc" tidak ada.
        if (class_exists(\App\Services\AiCoreGrpc\AiCoreGrpcClient::class)
            && \App\Services\AiCoreGrpc\AiCoreGrpcClient::isSupported()
            && (bool) config('services.ai_core_grpc_enabled', true)) {
            try {
                $stats = app(\App\Services\AiCoreGrpc\AiCoreGrpcClient::class)->stats();

                if (($stats['status'] ?? '') !== 'error') {
                    return [
                        'totalImages' => (int) ($stats['total_images'] ?? 0),
                        'totalFeatures' => (int) ($stats['total_features'] ?? 0),
                        'databaseSize' => (string) ($stats['database_size'] ?? '0 B'),
                        'lastUpdated' => (string) ($stats['last_updated'] ?? now()->toISOString()),
                    ];
                }
            } catch (\Throwable $e) {
                \Log::warning('CBIR stats gRPC failed, falling back to HTTP: '.$e->getMessage());
            }
        }

        $aiCoreUrl = config('services.ai_core_url', 'http://ai_core:5000');

        try {
            $response = Http::timeout(10)->get("{$aiCoreUrl}/status");

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'totalImages' => $data['total_images'] ?? 0,
                    'totalFeatures' => $data['total_features'] ?? 0,
                    'databaseSize' => $this->formatBytes($data['database_size'] ?? 0),
                    'lastUpdated' => $data['last_updated'] ?? now()->toISOString(),
                ];
            }
        } catch (\Exception $e) {
            \Log::error('CBIR stats error: ' . $e->getMessage());
        }

        return [
            'totalImages' => 0,
            'totalFeatures' => 0,
            'databaseSize' => '0 B',
            'lastUpdated' => now()->toISOString(),
        ];
    }

    /**
     * Get CBIR search history for current user
     */
    public function history($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            return [];
        }
        
        return \App\Models\Cbir\CbirSearch::where('user_id', $user->id)
            ->with(['results.item'])
            ->latest()
            ->paginate($args['first'] ?? 20);
    }

    /**
     * Get saved CBIR results
     */
    public function savedResults($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            return [];
        }
        
        $search = \App\Models\Cbir\CbirSearch::where('id', $args['searchId'])
            ->where('user_id', $user->id)
            ->with(['results.item'])
            ->firstOrFail();
        
        return $search->results;
    }

    /**
     * Save CBIR search to history
     */
    public function saveSearch($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            throw new \Exception('Unauthorized: Please login');
        }
        
        $input = $args['input'];
        
        $search = \App\Models\Cbir\CbirSearch::create([
            'user_id' => $user->id,
            'query_image_url' => $input['queryImageUrl'],
            'results_count' => count($input['results']),
            'top_similarity' => max(array_column($input['results'], 'similarity')),
        ]);
        
        foreach ($input['results'] as $result) {
            \App\Models\Cbir\CbirSearchResult::create([
                'search_id' => $search->id,
                'type' => $result['type'],
                'item_id' => $result['itemId'],
                'similarity' => $result['similarity'],
                'score' => $result['score'],
            ]);
        }
        
        return $search->load('results');
    }

    /**
     * Delete CBIR search from history
     */
    public function deleteSearch($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            throw new \Exception('Unauthorized: Please login');
        }
        
        $search = \App\Models\Cbir\CbirSearch::where('id', $args['searchId'])
            ->where('user_id', $user->id)
            ->firstOrFail();
        
        $search->delete();
        
        return true;
    }

    /**
     * Clear CBIR history
     */
    public function clearHistory($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            throw new \Exception('Unauthorized: Please login');
        }
        
        \App\Models\Cbir\CbirSearch::where('user_id', $user->id)->delete();
        
        return true;
    }

    /**
     * Submit feedback on CBIR results
     */
    public function submitFeedback($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            throw new \Exception('Unauthorized: Please login');
        }
        
        $input = $args['input'];
        
        \App\Models\Cbir\CbirFeedback::create([
            'user_id' => $user->id,
            'search_id' => $input['searchId'],
            'item_id' => $input['itemId'],
            'item_type' => $input['itemType'],
            'is_relevant' => $input['isRelevant'],
            'feedback' => $input['feedback'] ?? null,
        ]);
        
        return true;
    }

    /**
     * Admin: Rebuild CBIR index
     */
    public function rebuildIndex($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user || !$user->hasRole('admin')) {
            throw new \Exception('Unauthorized: Admin access required');
        }
        
        $aiCoreUrl = config('services.ai_core_url', 'http://ai_core:5000');
        
        try {
            $start = microtime(true);
            
            $response = Http::timeout(300)->post("{$aiCoreUrl}/api/index/rebuild-from-dataset", [
                'app_url' => config('app.url'),
            ]);
            
            $elapsed = microtime(true) - $start;
            
            if ($response->successful()) {
                $data = $response->json();
                
                return [
                    'success' => true,
                    'message' => $data['message'] ?? 'Index rebuilt successfully',
                    'affectedCount' => $data['total'] ?? 0,
                    'processingTime' => round($elapsed, 2),
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to rebuild index',
                'affectedCount' => 0,
                'processingTime' => round($elapsed, 2),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'affectedCount' => 0,
                'processingTime' => 0,
            ];
        }
    }

    /**
     * Admin: Add image to CBIR index
     */
    public function addToIndex($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user || !$user->hasRole('admin')) {
            throw new \Exception('Unauthorized: Admin access required');
        }
        
        $aiCoreUrl = config('services.ai_core_url', 'http://ai_core:5000');
        
        try {
            $start = microtime(true);
            
            $response = Http::timeout(60)->post("{$aiCoreUrl}/api/index/add", [
                'media_id' => $args['mediaId'],
                'image_path' => $args['imageUrl'],
                'metadata' => [
                    'type' => $args['type'],
                    ...($args['metadata'] ?? []),
                ],
            ]);
            
            $elapsed = microtime(true) - $start;
            
            if ($response->successful()) {
                $data = $response->json();
                
                return [
                    'success' => true,
                    'message' => $data['message'] ?? 'Image added to index',
                    'affectedCount' => 1,
                    'processingTime' => round($elapsed, 2),
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to add image to index',
                'affectedCount' => 0,
                'processingTime' => round($elapsed, 2),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'affectedCount' => 0,
                'processingTime' => 0,
            ];
        }
    }

    /**
     * Admin: Remove image from CBIR index
     */
    public function removeFromIndex($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user || !$user->hasRole('admin')) {
            throw new \Exception('Unauthorized: Admin access required');
        }
        
        $aiCoreUrl = config('services.ai_core_url', 'http://ai_core:5000');
        
        try {
            $start = microtime(true);
            
            $response = Http::timeout(30)->post("{$aiCoreUrl}/api/index/remove", [
                'metadata_id' => $args['mediaId'],
            ]);
            
            $elapsed = microtime(true) - $start;
            
            if ($response->successful()) {
                $data = $response->json();
                
                return [
                    'success' => true,
                    'message' => $data['message'] ?? 'Image removed from index',
                    'affectedCount' => $data['removed'] ?? 0,
                    'processingTime' => round($elapsed, 2),
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to remove image from index',
                'affectedCount' => 0,
                'processingTime' => round($elapsed, 2),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'affectedCount' => 0,
                'processingTime' => 0,
            ];
        }
    }

    /**
     * Get final price for CBIR result
     */
    public function finalPrice($root, array $args, GraphQLContext $context)
    {
        $data = $root;
        return $data['discountPrice'] ?? $data['price'] ?? 0;
    }

    /**
     * Get category for CBIR result
     */
    public function category($root, array $args, GraphQLContext $context)
    {
        $data = $root;
        
        if (isset($data['categoryId'])) {
            return Category::find($data['categoryId']);
        }
        
        return Category::where('name', $data['category'])->first();
    }

    /**
     * Check if CBIR result item is available
     */
    public function isAvailable($root, array $args, GraphQLContext $context)
    {
        $data = $root;
        
        if ($data['type'] === 'catalog') {
            $catalog = Catalog::find($data['id']);
            return $catalog && $catalog->is_active && $catalog->stock > 0;
        } elseif ($data['type'] === 'package') {
            $package = Package::find($data['id']);
            return $package && $package->is_active;
        }
        
        return false;
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
