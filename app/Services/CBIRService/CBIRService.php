<?php

namespace App\Services\CBIRService;

use App\Models\Package\Package;
use App\Models\Product\Product;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CBIRService
{
    protected string $baseUrl;

    protected int $timeoutSeconds;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.ai_core_url', 'http://127.0.0.1:5000'), '/');
        $this->timeoutSeconds = (int) config('services.ai_core_timeout', 15);
    }

    public function searchByImage($imageFile): array
    {
        try {
            $fileHash = md5_file($imageFile->getRealPath());
            $cacheKey = "cbir_search_v{$fileHash}";

            Log::info("CBIR Search initiated for file hash: {$fileHash}");

            return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($imageFile) {
                Log::info('CBIR Cache miss - calling AI Core API');
                /** @var Response $response */
                $response = Http::timeout($this->timeoutSeconds)
                    ->retry(2, 300, throw: false)
                    ->attach(
                        'file', // Flask app.py expects 'file'
                        file_get_contents($imageFile->getRealPath()),
                        method_exists($imageFile, 'getClientOriginalName') ? $imageFile->getClientOriginalName() : $imageFile->getFilename()
                    )->post("{$this->baseUrl}/api/search");

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
                                'type' => (string) ($row['type'] ?? 'product'),
                                'score' => $score,
                                'similarity' => $similarity,
                                'image_url' => $row['image_url'] ?? null,
                                'vendor' => $row['vendor'] ?? null,
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
            });
        } catch (\Exception $e) {
            Log::error('AI Core connection error: '.$e->getMessage());

            return $this->errorResponse(__('Layanan AI Scanner sedang offline. Silakan coba beberapa saat lagi.'));
        }
    }

    public function indexMedia($media): bool
    {
        try {
            // `model_type` yang tersimpan di tabel media adalah ALIAS morph map
            // ("App\Models\Product"), bukan FQCN ("App\Models\Product\Product").
            // Kalau dicocokkan langsung ke Product::class, tidak akan pernah
            // cocok dan semua media jatuh ke default -- akibatnya gambar paket
            // terindeks sebagai tipe yang tidak dikenal, lalu `byImage()`
            // mencarinya di tabel products dan membuangnya diam-diam.
            //
            // getMorphedModel() mengembalikan null kalau nilainya memang FQCN
            // (tidak di-alias), jadi fallback ke nilai aslinya aman.
            $modelClass = Relation::getMorphedModel($media->model_type) ?? $media->model_type;

            $type = match ($modelClass) {
                Package::class => 'package',
                Product::class => 'product',
                default => 'unknown',
            };

            // Capai model terkait (product/package) untuk mengambil vendor.
            // Pakai $modelClass hasil resolusi morph map, bukan $media->model_type
            // langsung — yang latter berisi alias dan bukan nama kelas, sehingga
            // pemanggilan statisnya gagal dan vendor/nama selalu kosong.
            $vendorName = '';
            $itemName = '';
            try {
                if (class_exists($modelClass)) {
                    $model = $modelClass::with('vendor')->find($media->model_id);
                    $vendorName = $model?->vendor?->store_name ?? '';
                    $itemName = (string) ($model?->name ?? '');
                }
            } catch (\Throwable $e) {
                // abaikan bila relasi vendor tidak tersedia
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
                        'vendor' => $vendorName,
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
                            'type' => (string) ($row['type'] ?? 'product'),
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
