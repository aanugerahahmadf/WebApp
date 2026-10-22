<?php

namespace App\Grpc\User\V1;

use App\Models\Cbir\CbirFeedback;
use App\Models\Cbir\CbirSearch;
use App\Models\Cbir\CbirSearchResult;
use App\Models\Catalog\Catalog;
use App\Models\Category\Category;
use App\Models\Package\Package;
use App\Services\CBIRService\CBIRService as CbirEngine;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Spiral\RoadRunner\GRPC\ContextInterface;

/**
 * CBIR gRPC Service Implementation
 *
 * Bridges the Flutter mobile client to the Python AI Core over gRPC instead of
 * REST. Binary image payloads are first class here, so there is no multipart
 * encoding overhead compared to the REST `/api/cbir/*` endpoints.
 */
class CbirService extends BaseGrpcService
{
    public function __construct(private CbirEngine $cbirService) {}

    /**
     * Search similar items using an uploaded image.
     */
    public function SearchSimilar(SearchSimilarRequest $request, ContextInterface $context): CbirSearchResponse
    {
        try {
            if ($request->image === '') {
                throw new \InvalidArgumentException('Image payload is required', 400);
            }

            $file = $this->toUploadedFile($request->image, $request->mime_type ?: 'image/jpeg');

            $apiResponse = $this->cbirService->searchByImage($file);

            return $this->buildSearchResponse($apiResponse);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e, 'SearchSimilar');
        }
    }

    /**
     * Client-streaming variant: chunks are concatenated before extraction.
     *
     * Useful for mobile clients on slow links that cannot buffer the whole
     * image in a single unary request.
     */
    public function SearchSimilarStream(iterable $requests, ContextInterface $context): CbirSearchResponse
    {
        try {
            $chunks = [];
            $mimeType = 'image/jpeg';

            foreach ($requests as $request) {
                /** @var SearchSimilarChunkRequest $request */
                $chunks[] = $request->chunk;
                if ($request->mime_type !== '') {
                    $mimeType = $request->mime_type;
                }
            }

            if ($chunks === []) {
                throw new \InvalidArgumentException('No image chunks received', 400);
            }

            $file = $this->toUploadedFile(implode('', $chunks), $mimeType);

            $apiResponse = $this->cbirService->searchByImage($file);

            return $this->buildSearchResponse($apiResponse);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e, 'SearchSimilarStream');
        }
    }

    /**
     * Arithmetic (image math) search combining two query images.
     */
    public function ArithmeticSearch(ArithmeticSearchRequest $request, ContextInterface $context): CbirSearchResponse
    {
        try {
            if ($request->image_1 === '' || $request->image_2 === '') {
                throw new \InvalidArgumentException('Both image_1 and image_2 are required', 400);
            }

            $allowedOps = ['add', 'average', 'subtract', 'multiply', 'divide'];
            if (!in_array($request->operation, $allowedOps, true)) {
                throw new \InvalidArgumentException(
                    "Invalid operation '{$request->operation}'. Expected one of: " . implode(', ', $allowedOps),
                    400
                );
            }

            $file1 = $this->toUploadedFile($request->image_1, $request->mime_type_1 ?: 'image/jpeg');
            $file2 = $this->toUploadedFile($request->image_2, $request->mime_type_2 ?: 'image/jpeg');

            $weights = $request->weights ?: null;

            $apiResponse = $this->cbirService->arithmeticSearch(
                $file1,
                $file2,
                $request->operation,
                $weights
            );

            return $this->buildSearchResponse($apiResponse);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e, 'ArithmeticSearch');
        }
    }

    /**
     * List the arithmetic operations the AI Core supports.
     */
    public function GetArithmeticOps(GetArithmeticOpsRequest $request, ContextInterface $context): ArithmeticOpsResponse
    {
        try {
            $ops = collect($this->cbirService->getArithmeticOps())
                ->map(fn ($op) => new ArithmeticOp([
                    'name' => (string) ($op['name'] ?? $op['key'] ?? ''),
                    'description' => (string) ($op['description'] ?? $op['label'] ?? ''),
                    'min_images' => (int) ($op['min_images'] ?? 2),
                    'max_images' => (int) ($op['max_images'] ?? 2),
                    'supports_weights' => (bool) ($op['supports_weights'] ?? false),
                ]))
                ->values()
                ->all();

            return new ArithmeticOpsResponse(['ops' => $ops]);
        } catch (\Throwable $e) {
            Log::error('gRPC GetArithmeticOps error: ' . $e->getMessage());

            return new ArithmeticOpsResponse(['ops' => []]);
        }
    }

    /**
     * CBIR index statistics.
     */
    public function GetStats(GetCbirStatsRequest $request, ContextInterface $context): CbirStats
    {
        $baseUrl = config('services.ai_core_url', 'http://127.0.0.1:5000');

        try {
            $response = Http::timeout(10)->get("{$baseUrl}/status");

            if ($response->successful()) {
                $data = $response->json();

                return new CbirStats([
                    'total_images' => (int) ($data['total_images'] ?? 0),
                    'total_features' => (int) ($data['total_features'] ?? 0),
                    'database_size' => $this->formatBytes((int) ($data['database_size'] ?? 0)),
                    'last_updated' => (string) ($data['last_updated'] ?? now()->toIso8601String()),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('CBIR stats unavailable: ' . $e->getMessage());
        }

        // AI Core offline — still report the Laravel-side truth.
        return new CbirStats([
            'total_images' => Catalog::query()->count(),
            'total_features' => 0,
            'database_size' => $this->formatBytes(0),
            'last_updated' => now()->toIso8601String(),
        ]);
    }

    /**
     * Paginated CBIR search history for the authenticated user.
     */
    public function GetHistory(GetCbirHistoryRequest $request, ContextInterface $context): CbirHistoryResponse
    {
        try {
            $user = $this->requireAuth($context);

            $query = CbirSearch::where('user_id', $user->id);

            $pagination = $request->pagination;
            $perPage = $pagination && $pagination->per_page > 0 ? $pagination->per_page : 20;
            $page = $pagination && $pagination->page > 0 ? $pagination->page : 1;

            $paginator = $query->latest()->paginate($perPage, ['*'], 'page', $page);

            return new CbirHistoryResponse([
                'history' => array_map([$this, 'transformHistory'], $paginator->items()),
                'pagination' => $this->createPagination($paginator),
            ]);
        } catch (\Throwable $e) {
            Log::error('gRPC GetHistory error: ' . $e->getMessage());

            return new CbirHistoryResponse(['history' => [], 'pagination' => null]);
        }
    }

    /**
     * Replay stored results for a previous search.
     */
    public function GetSavedResults(GetSavedCbirResultsRequest $request, ContextInterface $context): CbirSavedResultsResponse
    {
        try {
            $user = $this->requireAuth($context);

            $search = CbirSearch::where('user_id', $user->id)
                ->where('id', $request->search_id)
                ->firstOrFail();

            $results = $search->results->map(function ($result) {
                return new CbirResult([
                    'type' => $result->type,
                    'similarity' => (float) $result->similarity,
                    'score' => (float) $result->score,
                    'data' => $this->transformResultData([
                        'id' => $result->item_id,
                        'type' => $result->type,
                        'name' => $result->item?->name ?? '',
                        'slug' => $result->item?->slug ?? '',
                        'description' => $result->item?->description ?? '',
                        'price' => (float) ($result->item?->price ?? 0),
                        'discount_price' => (float) ($result->item?->discount_price ?? 0),
                        'image_url' => $result->item?->image_url ?? '',
                        'category' => $result->item?->category?->name ?? '',
                    ]),
                ]);
            })->values()->all();

            return new CbirSavedResultsResponse(['results' => $results]);
        } catch (\Throwable $e) {
            Log::error('gRPC GetSavedResults error: ' . $e->getMessage());

            return new CbirSavedResultsResponse(['results' => []]);
        }
    }

    /**
     * Persist a search (and its results) so the user can revisit it.
     */
    public function SaveSearch(SaveCbirSearchRequest $request, ContextInterface $context): CbirHistory
    {
        try {
            $user = $this->requireAuth($context);

            if ($request->query_image_url === '') {
                throw new \InvalidArgumentException('query_image_url is required', 400);
            }

            $results = collect($request->results);

            $search = CbirSearch::create([
                'user_id' => $user->id,
                'query_image_url' => $request->query_image_url,
                'results_count' => $results->count(),
                'top_similarity' => (float) ($results->max('similarity') ?? 0),
            ]);

            foreach ($results as $result) {
                CbirSearchResult::create([
                    'search_id' => $search->id,
                    'type' => $result->type,
                    'item_id' => $result->item_id,
                    'similarity' => $result->similarity,
                    'score' => $result->score,
                ]);
            }

            return $this->transformHistory($search->fresh(['results']));
        } catch (\Throwable $e) {
            Log::error('gRPC SaveSearch error: ' . $e->getMessage());

            return new CbirHistory([
                'id' => '',
                'user_id' => '',
                'query_image_url' => $request->query_image_url,
                'results_count' => 0,
                'top_similarity' => 0,
                'created_at' => now()->toIso8601String(),
            ]);
        }
    }

    /**
     * Delete a single search from the user's history.
     */
    public function DeleteSearch(DeleteCbirSearchRequest $request, ContextInterface $context): DeleteCbirSearchResponse
    {
        try {
            $user = $this->requireAuth($context);

            $search = CbirSearch::where('user_id', $user->id)
                ->where('id', $request->search_id)
                ->firstOrFail();

            $search->delete();

            return new DeleteCbirSearchResponse([
                'success' => true,
                'message' => __('Search deleted'),
            ]);
        } catch (\Throwable $e) {
            return new DeleteCbirSearchResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clear the whole CBIR history for the authenticated user.
     */
    public function ClearHistory(ClearCbirHistoryRequest $request, ContextInterface $context): ClearCbirHistoryResponse
    {
        try {
            $user = $this->requireAuth($context);

            $deleted = CbirSearch::where('user_id', $user->id)->delete();

            return new ClearCbirHistoryResponse([
                'success' => true,
                'message' => __('History cleared'),
            ]);
        } catch (\Throwable $e) {
            return new ClearCbirHistoryResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Store relevance feedback for a returned result.
     */
    public function SubmitFeedback(SubmitCbirFeedbackRequest $request, ContextInterface $context): SubmitCbirFeedbackResponse
    {
        try {
            $user = $this->requireAuth($context);

            CbirFeedback::create([
                'user_id' => $user->id,
                'search_id' => $request->search_id,
                'item_id' => $request->item_id,
                'item_type' => $request->item_type,
                'is_relevant' => $request->is_relevant,
                'feedback' => $request->feedback !== '' ? $request->feedback : null,
            ]);

            return new SubmitCbirFeedbackResponse([
                'success' => true,
                'message' => __('Feedback recorded'),
            ]);
        } catch (\Throwable $e) {
            return new SubmitCbirFeedbackResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Admin: reindex every package and catalog image.
     */
    public function RebuildIndex(RebuildCbirIndexRequest $request, ContextInterface $context): CbirIndexResult
    {
        $startedAt = microtime(true);

        try {
            $this->requireAdmin($context);

            $packages = Package::all();
            $catalogs = Catalog::all();

            $packageCount = 0;
            $catalogCount = 0;

            foreach ($packages as $package) {
                $media = $package->getFirstMedia('package_image');
                if ($media && $this->cbirService->indexMedia($media)) {
                    $packageCount++;
                }
            }

            foreach ($catalogs as $catalog) {
                $media = $catalog->getFirstMedia('catalog_image');
                if ($media && $this->cbirService->indexMedia($media)) {
                    $catalogCount++;
                }
            }

            return new CbirIndexResult([
                'success' => true,
                'message' => __('CBIR Index built with :p packages and :c catalogs', [
                    'p' => $packageCount,
                    'c' => $catalogCount,
                ]),
                'affected_count' => $packageCount + $catalogCount,
                'processing_time' => round(microtime(true) - $startedAt, 2),
            ]);
        } catch (\Throwable $e) {
            return new CbirIndexResult([
                'success' => false,
                'message' => $e->getMessage(),
                'affected_count' => 0,
                'processing_time' => round(microtime(true) - $startedAt, 2),
            ]);
        }
    }

    /**
     * Admin: index a single media item.
     */
    public function AddToIndex(AddToCbirIndexRequest $request, ContextInterface $context): CbirIndexResult
    {
        $startedAt = microtime(true);

        try {
            $this->requireAdmin($context);

            $catalog = Catalog::findOrFail($request->media_id);
            $media = $catalog->getFirstMedia('catalog_image');

            if (! $media) {
                throw new \RuntimeException('Catalog has no image to index', 422);
            }

            $success = $this->cbirService->indexMedia($media);

            return new CbirIndexResult([
                'success' => $success,
                'message' => $success ? __('Catalog indexed successfully') : __('Failed to index catalog'),
                'affected_count' => $success ? 1 : 0,
                'processing_time' => round(microtime(true) - $startedAt, 2),
            ]);
        } catch (\Throwable $e) {
            return new CbirIndexResult([
                'success' => false,
                'message' => $e->getMessage(),
                'affected_count' => 0,
                'processing_time' => round(microtime(true) - $startedAt, 2),
            ]);
        }
    }

    /**
     * Admin: remove a media item from the CBIR index.
     */
    public function RemoveFromIndex(RemoveFromCbirIndexRequest $request, ContextInterface $context): CbirIndexResult
    {
        $startedAt = microtime(true);

        try {
            $this->requireAdmin($context);

            $success = $this->cbirService->removeFromIndex($request->media_id);

            return new CbirIndexResult([
                'success' => $success,
                'message' => $success ? __('Removed from index') : __('Failed to remove from index'),
                'affected_count' => $success ? 1 : 0,
                'processing_time' => round(microtime(true) - $startedAt, 2),
            ]);
        } catch (\Throwable $e) {
            return new CbirIndexResult([
                'success' => false,
                'message' => $e->getMessage(),
                'affected_count' => 0,
                'processing_time' => round(microtime(true) - $startedAt, 2),
            ]);
        }
    }

    /**
     * Probe the AI Core health endpoint.
     */
    public function HealthCheck(HealthCheckRequest $request, ContextInterface $context): HealthCheckResponse
    {
        $baseUrl = config('services.ai_core_url', 'http://127.0.0.1:5000');

        try {
            $response = Http::timeout(5)->get("{$baseUrl}/health");

            if ($response->successful()) {
                $health = $response->json();

                return new HealthCheckResponse([
                    'success' => true,
                    'message' => __('AI Core aktif dan sehat'),
                    'data' => new CbirHealthData([
                        'mode' => 'local',
                        'server_status' => (string) ($health['status'] ?? 'healthy'),
                        'version' => (string) ($health['version'] ?? ''),
                        'method' => (string) ($health['method'] ?? ''),
                        'metric' => (string) ($health['metric'] ?? ''),
                        'capabilities' => $health['capabilities'] ?? [],
                    ]),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('AI Core health check error: ' . $e->getMessage());
        }

        return new HealthCheckResponse([
            'success' => false,
            'message' => __('AI Core tidak merespons'),
            'data' => new CbirHealthData([
                'mode' => 'local',
                'server_status' => 'offline',
            ]),
        ]);
    }

    /**
     * Fetch CBIR retrieval metrics from the AI Core.
     */
    public function Evaluate(EvaluateRequest $request, ContextInterface $context): EvaluateResponse
    {
        $baseUrl = config('services.ai_core_url', 'http://127.0.0.1:5000');

        try {
            $response = Http::timeout(120)->get("{$baseUrl}/api/evaluate");

            if ($response->successful()) {
                $payload = $response->json();

                $metrics = [];
                foreach ($payload['metrics'] ?? [] as $name => $value) {
                    $metrics[] = new EvaluateMetric([
                        'name' => is_string($name) ? $name : 'metric',
                        'value' => (float) $value,
                        'description' => '',
                    ]);
                }

                return new EvaluateResponse([
                    'success' => (bool) ($payload['success'] ?? true),
                    'message' => (string) ($payload['message'] ?? ''),
                    'metrics' => $metrics,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('CBIR evaluate error: ' . $e->getMessage());
        }

        return new EvaluateResponse([
            'success' => false,
            'message' => __('Layanan AI Core sedang offline'),
            'metrics' => [],
        ]);
    }

    // ---------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------

    /**
     * Wrap raw protobuf bytes in a temporary UploadedFile the AI Core client
     * can forward. The temp file is removed by PHP once the request ends.
     */
    private function toUploadedFile(string $binary, string $mimeType): UploadedFile
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'cbir_');

        if ($tempPath === false) {
            throw new \RuntimeException('Unable to allocate temp file for image', 500);
        }

        file_put_contents($tempPath, $binary);

        return new UploadedFile(
            $tempPath,
            'query.' . ($this->extensionFor($mimeType) ?: 'jpg'),
            $mimeType,
            null,
            true, // test mode: skips is_uploaded_file() which fails for temp files
        );
    }

    private function extensionFor(string $mimeType): string
    {
        return match (strtolower($mimeType)) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/bmp' => 'bmp',
            'image/jpeg', 'image/jpg' => 'jpg',
            default => '',
        };
    }

    /**
     * Normalize the AI Core payload into the protobuf response shape, loading
     * the owning Package/Catalog rows so the client gets display-ready data.
     */
    private function buildSearchResponse(array $apiResponse): CbirSearchResponse
    {
        if (isset($apiResponse['error']) || ! ($apiResponse['success'] ?? false)) {
            return new CbirSearchResponse([
                'success' => false,
                'message' => (string) ($apiResponse['message'] ?? __('Pencarian CBIR gagal')),
                'results' => [],
                'total_results' => 0,
                'query_time_seconds' => (float) ($apiResponse['query_time_seconds'] ?? 0),
            ]);
        }

        $results = collect($apiResponse['results'] ?? []);

        $idsByType = $results->groupBy('type');

        $packages = Package::with('category')
            ->whereIn('id', $idsByType->get('package', collect())->pluck('owner_id')->all())
            ->get()
            ->keyBy('id');

        $catalogs = Catalog::with('category')
            ->whereIn('id', $idsByType->get('catalog', collect())->pluck('owner_id')->all())
            ->get()
            ->keyBy('id');

        $locale = app()->getLocale();

        $enriched = $results->map(function (array $res) use ($packages, $catalogs, $locale): ?CbirResult {
            $type = (string) ($res['type'] ?? 'unknown');
            $id = (int) ($res['owner_id'] ?? 0);

            $model = match ($type) {
                'package' => $packages->get($id),
                'catalog' => $catalogs->get($id),
                default => null,
            };

            if (! $model) {
                return null;
            }

            return new CbirResult([
                'type' => $type,
                'similarity' => (float) ($res['similarity'] ?? 0),
                'score' => (float) ($res['score'] ?? 0),
                'data' => new CbirResultData([
                    'id' => (string) $model->id,
                    'name' => (string) $model->trans('name', $locale),
                    'slug' => (string) $model->slug,
                    'description' => strip_tags((string) $model->trans('description', $locale)),
                    'price' => (float) $model->price,
                    'discount_price' => $model->discount_price > 0 ? (float) $model->discount_price : 0,
                    'final_price' => $model->discount_price > 0
                        ? (float) $model->discount_price
                        : (float) $model->price,
                    'image_url' => (string) ($model->image_url ?? ''),
                    'category' => (string) ($model->category?->name ?? ''),
                    'category_obj' => $model->category
                        ? $this->transformCategory($model->category)
                        : null,
                    'is_available' => (bool) ($model->is_active ?? true),
                ]),
            ]);
        })->filter()->values()->all();

        return new CbirSearchResponse([
            'success' => true,
            'message' => '',
            'results' => $enriched,
            'total_results' => count($enriched),
            'query_time_seconds' => (float) ($apiResponse['query_time_seconds'] ?? 0),
        ]);
    }

    private function buildErrorResponse(\Throwable $e, string $operation): CbirSearchResponse
    {
        $error = $this->handleError($e, $operation);

        return new CbirSearchResponse([
            'success' => false,
            'message' => $error['message'],
            'results' => [],
            'total_results' => 0,
            'query_time_seconds' => 0,
        ]);
    }

    private function transformResultData(array $data): CbirResultData
    {
        $price = (float) ($data['price'] ?? 0);
        $discount = (float) ($data['discount_price'] ?? 0);

        return new CbirResultData([
            'id' => (string) $data['id'],
            'name' => (string) ($data['name'] ?? ''),
            'slug' => (string) ($data['slug'] ?? ''),
            'description' => (string) ($data['description'] ?? ''),
            'price' => $price,
            'discount_price' => $discount,
            'final_price' => $discount > 0 ? $discount : $price,
            'image_url' => (string) ($data['image_url'] ?? ''),
            'category' => (string) ($data['category'] ?? ''),
            'category_obj' => null,
            'is_available' => (bool) ($data['is_available'] ?? true),
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

    private function transformHistory(CbirSearch $search): CbirHistory
    {
        $results = collect($search->relationLoaded('results') ? $search->results : [])
            ->map(fn ($r) => new CbirHistoryResult([
                'type' => (string) $r->type,
                'item_id' => (string) $r->item_id,
                'similarity' => (float) $r->similarity,
                'score' => (float) $r->score,
                'item' => $this->transformResultData([
                    'id' => $r->item_id,
                    'name' => $r->item?->name ?? '',
                    'slug' => $r->item?->slug ?? '',
                    'description' => $r->item?->description ?? '',
                    'price' => $r->item?->price ?? 0,
                    'discount_price' => $r->item?->discount_price ?? 0,
                    'image_url' => $r->item?->image_url ?? '',
                    'category' => $r->item?->category?->name ?? '',
                ]),
            ]))
            ->values()
            ->all();

        return new CbirHistory([
            'id' => (string) $search->id,
            'user_id' => (string) $search->user_id,
            'query_image_url' => (string) $search->query_image_url,
            'results' => $results,
            'results_count' => (int) $search->results_count,
            'top_similarity' => (float) $search->top_similarity,
            'created_at' => $this->formatTimestamp($search->created_at),
        ]);
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $i = 0;
        while ($bytes > 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}