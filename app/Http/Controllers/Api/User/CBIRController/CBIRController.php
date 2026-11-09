<?php

namespace App\Http\Controllers\Api\User\CBIRController;

use App\Http\Controllers\Controller;
use App\Models\Package\Package;
use App\Models\Catalog\Catalog;
use App\Services\CBIRService\CBIRService;
use App\Support\CbirSearchLogger\CbirSearchLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CBIRController extends Controller
{
    /**
     * Search for similar wedding catalog items using image
     *
     * @return JsonResponse
     */
    public function searchSimilar(Request $request, CBIRService $cbirService)
    {
        $request->validate([
            'image' => 'required|file|mimes:jpeg,png,jpg,bmp,webp,mp4,mov,m4v,webm,3gp|max:51200',
        ]);

        $apiResponse = $cbirService->searchByImage($request->file('image'));

        if (isset($apiResponse['error']) || ! ($apiResponse['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $apiResponse['message'] ?? 'Error',
                'results' => [],
            ]);
        }

        $results = $apiResponse['results'] ?? [];

        $idsByType = collect($results)->groupBy('type');

        // AI Core keeps `package` as its storage/protobuf compatibility value;
        // the public Laravel API exposes the same rows as catalog tiers.
        $packageIds = $idsByType->get('package', collect())
            ->merge($idsByType->get('tier', collect()))
            ->pluck('owner_id')
            ->all();
        $catalogIds = $idsByType->get('catalog', collect())->pluck('owner_id')->all();

        $packages = Package::with(['category'])
            ->whereIn('id', $packageIds)
            ->get()
            ->keyBy('id');

        $catalogs = Catalog::with(['category'])
            ->whereIn('id', $catalogIds)
            ->get()
            ->keyBy('id');

        $locale = app()->getLocale();
        $enrichedResults = collect($results)->map(function (array $res) use ($packages, $catalogs, $locale): ?array {
            $legacyType = $res['type'] ?? 'unknown';
            $type = $legacyType === 'package' ? 'tier' : $legacyType;
            $id = (int) ($res['owner_id'] ?? 0);

            $model = ($type === 'tier') ? $packages->get($id) : (($type === 'catalog') ? $catalogs->get($id) : null);

            if (! $model) {
                return null;
            }

            $data = [
                'id' => $model->id,
                'name' => $model->trans('name', $locale),
                'slug' => $model->slug,
                'description' => strip_tags($model->trans('description', $locale)),
                'image_url' => $model->image_url,
                'category' => $model->category?->name,
            ];
            if ($type === 'tier') {
                $data['catalog_id'] = $model->catalog_id;
                $data['price'] = $model->price;
                $data['discount_price'] = $model->discount_price > 0 ? $model->discount_price : null;
            }

            return [
                'type' => $type,
                'legacy_type' => $legacyType,
                'similarity' => $res['similarity'] ?? 0,
                'score' => $res['score'] ?? 0,
                'data' => $data,
            ];
        })->filter()->values();

        session([
            'cbir_mixed_results' => $enrichedResults->toArray(),
            'cbir_search_time' => $apiResponse['query_time_seconds'] ?? 0,
        ]);

        // Riwayat pencarian visual (tabel `cbir_searches`). Tanpa ini tidak ada
        // satu pun bukti di database bahwa fitur CBIR pernah dipakai.
        CbirSearchLogger::recordUpload($request->user()?->id, $request->file('image'), $enrichedResults);

        return response()->json([
            'success' => true,
            'results' => $enrichedResults,
            'total_results' => $enrichedResults->count(),
            'query_time_seconds' => $apiResponse['query_time_seconds'] ?? 0,
        ]);
    }

    /**
     * Index a catalog image into CBIR database
     *
     * @return JsonResponse
     */
    public function indexItem(Request $request, CBIRService $cbirService)
    {
        $request->validate([
            'catalog_id' => 'required|exists:catalogs,id',
        ]);

        $catalog = Catalog::findOrFail($request->catalog_id);
        $media = $catalog->getFirstMedia('catalog_image');

        if (! $media) {
            return response()->json([
                'success' => false,
                'message' => 'No image found for this catalog',
            ], 400);
        }

        $success = $cbirService->indexMedia($media);

        return response()->json([
            'success' => $success,
            'message' => $success ? __('Catalog indexed successfully') : __('Failed to index catalog'),
            'data' => [
                'catalog_id' => $catalog->id,
            ],
        ]);
    }

    public function buildIndex(CBIRService $cbirService)
    {
        $packages = Package::all();
        $catalogs = Catalog::all();

        $pCount = 0;
        $cCount = 0;
        $errors = [];

        foreach ($packages as $package) {
            $media = $package->getFirstMedia('package_image');
            if ($media) {
                if ($cbirService->indexMedia($media)) {
                    $pCount++;
                } else {
                    $errors[] = "Failed to index package ID {$package->id}";
                }
            }
        }

        foreach ($catalogs as $catalog) {
            // SEMUA foto, bukan `getFirstMedia()`: satu katalog punya lima foto
            // galeri. Dengan hanya foto pertama, membangun indeks lewat endpoint
            // ini membuang empat dari lima foto, dan pencarian dengan foto
            // ke-2..5 tidak akan pernah menemukan katalognya.
            foreach ($catalog->getMedia('catalog_image') as $media) {
                if ($cbirService->indexMedia($media)) {
                    $cCount++;
                } else {
                    $errors[] = "Failed to index catalog ID {$catalog->id} (media {$media->id})";
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => __('CBIR Index built with :pCount packages and :cCount catalogs', ['pCount' => $pCount, 'cCount' => $cCount]),
            'indexed_packages' => $pCount,
            'indexed_catalogs' => $cCount,
            'total_items' => $packages->count() + $catalogs->count(),
            'errors' => $errors,
        ]);
    }

    public function getStats(CBIRService $cbirService)
    {
        try {
            $baseUrl = config('services.ai_core_url', 'http://127.0.0.1:5000');
            $response = Http::timeout(10)->get("{$baseUrl}/status");

            if ($response->successful()) {
                $status = $response->json();

                return response()->json([
                    'success' => true,
                    'data' => [
                        'mode' => 'local',
                        'server_status' => 'online',
                        // Tiga angka, tiga arti (lihat AGENTS.md):
                        //   indexed_catalogs -> PRODUK unik (type + owner_id)
                        //   indexed_entries  -> baris indeks (= jumlah FOTO)
                        //   total_catalogs (lama) -> jumlah foto, dipertahankan
                        //     supaya klien versi lama tidak membaca 0.
                        'indexed_catalogs' => $status['total_items'] ?? $status['total_catalogs'] ?? 0,
                        'indexed_entries' => $status['total_entries'] ?? $status['total_catalogs'] ?? 0,
                        'indexed_items_by_type' => $status['items_by_type'] ?? [],
                        'total_database_items' => Catalog::query()->count(),
                    ],
                ]);
            }
        } catch (\Exception $e) {
        }

        return response()->json([
            'success' => true,
            'data' => [
                'mode' => 'local',
                'server_status' => 'offline',
                'total_database_items' => Catalog::query()->count(),
            ],
        ]);
    }

    public function arithmeticSearch(Request $request, CBIRService $cbirService): JsonResponse
    {
        $request->validate([
            'image_1' => 'required|image|max:10240',
            'image_2' => 'required|image|max:10240',
            'operation' => 'required|string|in:add,average,subtract,multiply,divide',
            'weights' => 'nullable|array',
            'weights.*' => 'numeric|min:0|max:1',
        ]);

        $apiResponse = $cbirService->arithmeticSearch(
            $request->file('image_1'),
            $request->file('image_2'),
            $request->input('operation'),
            $request->input('weights'),
        );

        if (isset($apiResponse['error']) || ! ($apiResponse['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $apiResponse['message'] ?? 'Error',
                'results' => [],
            ]);
        }

        $results = $apiResponse['results'] ?? [];
        $idsByType = collect($results)->groupBy('type');
        $packageIds = $idsByType->get('package', collect())
            ->merge($idsByType->get('tier', collect()))
            ->pluck('owner_id')
            ->all();
        $catalogIds = $idsByType->get('catalog', collect())->pluck('owner_id')->all();

        $packages = Package::with(['category'])->whereIn('id', $packageIds)->get()->keyBy('id');
        $catalogs = Catalog::with(['category'])->whereIn('id', $catalogIds)->get()->keyBy('id');
        $locale = app()->getLocale();

        $enrichedResults = collect($results)->map(function (array $res) use ($packages, $catalogs, $locale): ?array {
            $legacyType = $res['type'] ?? 'unknown';
            $type = $legacyType === 'package' ? 'tier' : $legacyType;
            $id = (int) ($res['owner_id'] ?? 0);
            $model = ($type === 'tier') ? $packages->get($id) : (($type === 'catalog') ? $catalogs->get($id) : null);
            if (! $model) return null;
            $data = [
                'id' => $model->id,
                'name' => $model->trans('name', $locale),
                'slug' => $model->slug,
                'description' => strip_tags($model->trans('description', $locale)),
                'image_url' => $model->image_url,
                'category' => $model->category?->name,
            ];
            if ($type === 'tier') {
                $data['catalog_id'] = $model->catalog_id;
                $data['price'] = $model->price;
                $data['discount_price'] = $model->discount_price > 0 ? $model->discount_price : null;
            }

            return [
                'type' => $type,
                'legacy_type' => $legacyType,
                'similarity' => $res['similarity'] ?? 0,
                'score' => $res['score'] ?? 0,
                'data' => $data,
            ];
        })->filter()->values();

        return response()->json([
            'success' => true,
            'results' => $enrichedResults,
            'total_results' => $enrichedResults->count(),
            'query_time_seconds' => $apiResponse['query_time_seconds'] ?? 0,
            'operation' => $apiResponse['operation'] ?? null,
            'source_images' => $apiResponse['source_images'] ?? [],
        ]);
    }

    public function arithmeticOps(CBIRService $cbirService): JsonResponse
    {
        $ops = $cbirService->getArithmeticOps();
        return response()->json([
            'success' => true,
            'operations' => $ops,
        ]);
    }

    public function healthCheck()
    {
        try {
            $baseUrl = config('services.ai_core_url', 'http://127.0.0.1:5000');
            $response = Http::timeout(5)->get("{$baseUrl}/health");

            if ($response->successful()) {
                $health = $response->json();

                return response()->json([
                    'success' => true,
                    'message' => __('AI Core lokal aktif dan sehat'),
                    'data' => [
                        'mode' => 'local',
                        'server_status' => $health['status'] ?? 'healthy',
                        'version' => $health['version'] ?? null,
                        'method' => $health['method'] ?? null,
                        'metric' => $health['metric'] ?? null,
                        'capabilities' => $health['capabilities'] ?? [],
                    ],
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('AI Core health check error: '.$e->getMessage());
        }

        return response()->json([
            'success' => false,
            'message' => __('AI Core lokal tidak merespons'),
            'data' => [
                'mode' => 'local',
                'server_status' => 'offline',
            ],
        ]);
    }

    public function evaluate(Request $request, CBIRService $cbirService)
    {
        // Evaluasi menjalankan SELURUH indeks sebagai query. AI Core menyimpan
        // hasilnya dan memakai ulang selama masih segar (default 300 detik),
        // jadi request biasa selesai dalam milidetik. Permintaan hitung ulang
        // (`?refresh=1`) masih bisa memakan ±15-30 detik, dan batas PHP untuk
        // request web adalah 30 detik -> tanpa ini hasilnya HTTP 500
        // "Maximum execution time of 30 seconds exceeded".
        //
        // `set_time_limit()` bersifat PROSES-WIDE dan tidak kembali sendiri,
        // jadi nilainya disimpan dan dikembalikan di `finally`.
        $previousLimit = (int) ini_get('max_execution_time');
        @set_time_limit(120);

        try {
            $baseUrl = config('services.ai_core_url', 'http://127.0.0.1:5000');
            $query = $request->boolean('refresh') ? '?refresh=1' : '';
            $response = Http::timeout(110)->get("{$baseUrl}/api/evaluate{$query}");

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'success' => false,
                'message' => __('Evaluasi CBIR gagal: AI Core tidak merespon'),
                'metrics' => [],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('Layanan AI Core sedang offline'),
                'metrics' => [],
            ]);
        } finally {
            @set_time_limit($previousLimit);
        }
    }
}
