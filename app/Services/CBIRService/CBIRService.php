<?php

namespace App\Services\CBIRService;

use App\Models\Package\Package;
use App\Models\Catalog\Catalog;
use App\Services\AiCoreGrpc\AiCoreGrpcClient;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CBIRService
{
    protected string $baseUrl;

    protected int $timeoutSeconds;

    /** Klien gRPC dibuat lazy oleh grpcClient(). */
    protected ?AiCoreGrpcClient $grpcClient = null;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.ai_core_url', 'http://127.0.0.1:5000'), '/');
        $this->timeoutSeconds = (int) config('services.ai_core_timeout', 15);
    }

    public function searchByImage($imageFile, int $topK = 10): array
    {
        try {
            $fileHash = md5_file($imageFile->getRealPath());
            $cacheVersion = Cache::get('cbir_cache_version', 0);
            $cacheKey = "cbir_search_v{$cacheVersion}_{$topK}_{$fileHash}";

            Log::info("CBIR Search initiated for file hash: {$fileHash}, topK: {$topK}");

            return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($imageFile, $topK) {
                // gRPC lebih dulu: AI Core sekarang dijalankan sebagai server
                // gRPC (`python -m src.grpc.server`, :50052), dan Flask :5000
                // tidak lagi mendengarkan. Kalau gRPC gagal (server mati,
                // extension tidak ada), jalur HTTP lama tetap dipakai supaya
                // perilaku sebelumnya tidak hilang.
                $viaGrpc = $this->searchViaGrpc($imageFile, $topK);

                if ($viaGrpc !== null) {
                    return $viaGrpc;
                }

                Log::info('CBIR Cache miss - calling AI Core HTTP API (gRPC tidak tersedia)');

                return $this->searchViaHttp($imageFile, $topK);
            });
        } catch (\Exception $e) {
            Log::error('AI Core connection error: '.$e->getMessage());

            return $this->errorResponse(__('Layanan AI Scanner sedang offline. Silakan coba beberapa saat lagi.'));
        }
    }

    /**
     * Pencarian lewat gRPC. `null` berarti "coba jalur HTTP".
     *
     * Kegagalan sengaja tidak dilempar ke pemanggil: `searchByImage()` sudah
     * punya kontrak "selalu kembalikan array", dan HTTP masih bisa menjawab
     * kalau AI Core dijalankan dalam mode Flask.
     */
    protected function searchViaGrpc($imageFile, int $topK): ?array
    {
        if (! (bool) config('services.ai_core_grpc_enabled', true) || ! AiCoreGrpcClient::isSupported()) {
            return null;
        }

        try {
            $response = $this->grpcClient()->search(
                (string) file_get_contents($imageFile->getRealPath()),
                $topK,
            );

            Log::info('AI Core gRPC responded with '.count($response['results']).' results.');

            $response['success'] = true;

            return $response;
        } catch (\Throwable $e) {
            Log::warning('AI Core gRPC failed, falling back to HTTP: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Jalur HTTP lama (Flask `POST /api/search`).
     */
    protected function searchViaHttp($imageFile, int $topK): array
    {
        /** @var Response $response */
        $response = Http::timeout($this->timeoutSeconds)
            ->retry(2, 300, throw: false)
            ->attach(
                'file', // Flask app.py expects 'file'
                file_get_contents($imageFile->getRealPath()),
                method_exists($imageFile, 'getClientOriginalName') ? $imageFile->getClientOriginalName() : $imageFile->getFilename()
            )->post("{$this->baseUrl}/api/search", [
                'top_k' => $topK,
            ]);

        if ($response->successful()) {
            $data = $response->json();
            if (! is_array($data)) {
                Log::warning('AI Core returned non-array JSON payload.');

                return $this->errorResponse(__('Format respons AI tidak valid.'));
            }

            $normalizedResults = collect($data['results'] ?? [])
                ->filter(fn ($row) => is_array($row) && isset($row['owner_id']))
                ->map(function (array $row): array {
                    $score = (float) ($row['score'] ?? 0);
                    $similarity = (float) ($row['similarity'] ?? ($score * 100));

                    return [
                        'owner_id' => (int) $row['owner_id'],
                        'type' => (string) ($row['type'] ?? 'catalog'),
                        'score' => $score,
                        'similarity' => $similarity,
                        'image_url' => $row['image_url'] ?? null,
                    ];
                })
                ->values()
                ->all();

            Log::info('AI Core responded successfully with '.count($normalizedResults).' normalized results.');

            return [
                'success' => true,
                'results' => $normalizedResults,
                'query_time_seconds' => (float) ($data['query_time_seconds'] ?? 0),
            ];
        }

        Log::error('AI Core search error: '.$response->body());

        return $this->errorResponse(__('Pencarian visual sedang gangguan. Coba lagi nanti.'));
    }

    /**
     * Klien gRPC dibuat lazy: kalau jalur gRPC dimatikan lewat env, tidak ada
     * channel yang dibuka sama sekali.
     */
    protected function grpcClient(): AiCoreGrpcClient
    {
        return $this->grpcClient ??= app(AiCoreGrpcClient::class);
    }

    public function indexMedia($media): bool
    {
        try {
            // `model_type` yang tersimpan di tabel media adalah ALIAS morph map
            // ("App\Models\Catalog"), bukan FQCN ("App\Models\Catalog\Catalog").
            // getMorphedModel() mengembalikan null kalau nilainya memang FQCN
            // (tidak di-alias), jadi fallback ke nilai aslinya aman.
            $modelClass = Relation::getMorphedModel($media->model_type) ?? $media->model_type;

            $type = match ($modelClass) {
                Package::class => 'package',
                Catalog::class => 'catalog',
                default => 'unknown',
            };

            // Capai model terkait (catalog/package) untuk mengambil nama item.
            // Pakai $modelClass hasil resolusi morph map, bukan $media->model_type
            // langsung — yang latter berisi alias dan bukan nama kelas, sehingga
            // pemanggilan statisnya gagal dan nama selalu kosong.
            $itemName = '';
            try {
                if (class_exists($modelClass)) {
                    $model = $modelClass::find($media->model_id);
                    $itemName = (string) ($model?->name ?? '');
                }
            } catch (\Throwable $e) {
                // abaikan bila model tidak tersedia
            }

            // ─── gRPC terlebih dahulu ─────────────────────────────────────────
            // Index media baru tidak boleh gagal hanya karena Flask :5000 mati.
            // Kalau server gRPC menjawab sukses, HTTP tidak dipanggil lagi.
            if (AiCoreGrpcClient::isSupported() && (bool) config('services.ai_core_grpc_enabled', true)) {
                try {
                    $grpcResult = $this->grpcClient()->addToIndex($media->getPath(), [
                        'id' => $media->id,
                        'type' => $type,
                        'owner_id' => $media->model_id,
                        'image_url' => $media->getUrl(),
                        'name' => $itemName,
                    ]);

                    if ($grpcResult['success']) {
                        Cache::increment('cbir_cache_version');

                        return true;
                    }

                    Log::warning('AI Core gRPC addToIndex returned failure: '.($grpcResult['message'] ?? ''));
                } catch (\Throwable $e) {
                    Log::warning('AI Core gRPC addToIndex failed, falling back to HTTP: '.$e->getMessage());
                }
            }

            $response = Http::timeout($this->timeoutSeconds)
                ->retry(2, 300, throw: false)
                ->post("{$this->baseUrl}/api/index/add", [
                    'image_path' => $media->getPath(),
                    'metadata' => [
                        'id' => $media->id,
                        'type' => $type,
                        'owner_id' => $media->model_id,
                        'image_url' => $media->getUrl(),
                        'name' => $itemName,
                    ],
                ]);

            if ($response->successful()) {
                Cache::increment('cbir_cache_version');

                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('AI Core indexing error: '.$e->getMessage());

            return false;
        }
    }

    public function removeFromIndex($mediaId): bool
    {
        // gRPC terlebih dahulu, HTTP sebagai cadangan.
        if (AiCoreGrpcClient::isSupported() && (bool) config('services.ai_core_grpc_enabled', true)) {
            try {
                $result = $this->grpcClient()->removeFromIndex((int) $mediaId);

                if ($result['success'] || $result['removed'] > 0) {
                    return true;
                }
            } catch (\Throwable $e) {
                Log::warning('AI Core gRPC removeFromIndex failed, falling back to HTTP: '.$e->getMessage());
            }
        }

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->retry(2, 300, throw: false)
                ->post("{$this->baseUrl}/api/index/remove", [
                    'metadata_id' => $mediaId,
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('AI Core de-indexing error: '.$e->getMessage());

            return false;
        }
    }

    public function arithmeticSearch($image1, $image2, string $operation, ?array $weights = null): array
    {
        try {
            Log::info("CBIR Arithmetic search: operation={$operation}");

            $request = Http::timeout($this->timeoutSeconds)
                ->retry(1, 300, throw: false);

            // Attach files (bisa UploadedFile atau SplFileInfo)
            $request = $request->attach(
                'image_1',
                file_get_contents($image1->getRealPath()),
                method_exists($image1, 'getClientOriginalName') ? $image1->getClientOriginalName() : $image1->getFilename()
            );
            $request = $request->attach(
                'image_2',
                file_get_contents($image2->getRealPath()),
                method_exists($image2, 'getClientOriginalName') ? $image2->getClientOriginalName() : $image2->getFilename()
            );

            $payload = [
                'operation' => $operation,
                'method' => 'combined',
                'metric' => 'euclidean',
            ];
            if ($weights !== null) {
                $payload['weights'] = $weights;
            }

            $response = $request->post("{$this->baseUrl}/api/arithmetic", $payload);

            if ($response->successful()) {
                $data = $response->json();
                if (! is_array($data)) {
                    return $this->errorResponse('Format respons AI tidak valid.');
                }

                $normalizedResults = collect($data['results'] ?? [])
                    ->filter(fn ($row) => is_array($row) && isset($row['owner_id']))
                    ->map(function (array $row): array {
                        return [
                            'owner_id' => (int) $row['owner_id'],
                            'type' => (string) ($row['type'] ?? 'catalog'),
                            'score' => (float) ($row['distance'] ?? 0),
                            'similarity' => (float) ($row['similarity'] ?? 0),
                            'image_url' => $row['image_url'] ?? null,
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'success' => true,
                    'results' => $normalizedResults,
                    'query_time_seconds' => (float) ($data['query_time_seconds'] ?? 0),
                    'operation' => $data['operation'] ?? null,
                    'source_images' => $data['source_images'] ?? [],
                ];
            }

            Log::error('AI Core arithmetic error: ' . $response->body());
            return $this->errorResponse('Pencarian aritmetika sedang gangguan.');
        } catch (\Exception $e) {
            Log::error('AI Core arithmetic connection error: ' . $e->getMessage());
            return $this->errorResponse('Layanan AI Scanner sedang offline.');
        }
    }

    public function getArithmeticOps(): array
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/api/arithmetic/ops");
            if ($response->successful()) {
                return $response->json()['operations'] ?? [];
            }
        } catch (\Exception $e) {
            Log::error('AI Core arithmetic ops error: ' . $e->getMessage());
        }
        return [];
    }

    protected function errorResponse(string $message): array
    {
        return [
            'success' => false,
            'error' => true,
            'message' => $message,
            'results' => [],
            'query_time_seconds' => 0,
        ];
    }
}
