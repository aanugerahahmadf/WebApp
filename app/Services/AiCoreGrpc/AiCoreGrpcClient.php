<?php

namespace App\Services\AiCoreGrpc;

use App\Grpc\AiCore\AddToIndexRequest;
use App\Grpc\AiCore\CbirSearchRequest;
use App\Grpc\AiCore\CbirServiceClient;
use App\Grpc\AiCore\CbirStatsRequest;
use App\Grpc\AiCore\HealthRequest;
use App\Grpc\AiCore\RemoveFromIndexRequest;
use Google\Protobuf\Internal\Message;
use Grpc\ChannelCredentials;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Transport gRPC ke AI Core (Python).
 *
 * Kenapa ini ada: `CBIRService` dulu HANYA bicara HTTP ke
 * `services.ai_core_url` (default `http://127.0.0.1:5000`, yaitu Flask).
 * Saat AI Core dijalankan sebagai server gRPC saja (`:50052`), semua
 * pencarian visual jadi gagal karena tidak ada yang mendengarkan di `:5000`.
 *
 * Kelas ini membuat jalur gRPC tersedia dengan kontrak yang sama seperti
 * respons HTTP: `results[]` berisi `owner_id`, `type`, `score`, `similarity`,
 * `image_url`. Pemetaan itu penting -- server Python mengirim `data.id`
 * (id baris di tabel `catalogs`/`packages`), sedangkan pemanggil di Laravel
 * mengharapkan `owner_id`.
 *
 * Semua kegagalan DILEMPAR sebagai RuntimeException, bukan dikembalikan
 * sebagai array error: `CBIRService` memakai exception itu untuk memutuskan
 * jatuh ke jalur HTTP.
 */
class AiCoreGrpcClient
{
    private string $host;

    private int $timeoutMs;

    private ?CbirServiceClient $client = null;

    public function __construct(?string $host = null, ?int $timeoutMs = null)
    {
        $this->host = $host ?: (string) config('services.ai_core_grpc_host', '127.0.0.1:50052');
        $this->timeoutMs = $timeoutMs ?: (int) config('services.ai_core_grpc_timeout_ms', 15000);
    }

    /**
     * Apakah jalur gRPC bisa dipakai di proses ini.
     *
     * `extension_loaded('grpc')` menentukan segalanya: tanpa extension itu
     * `Grpc\BaseStub` tidak ada, dan seluruh kelas stub hasil generate protoc
     * tidak berguna.
     */
    public static function isSupported(): bool
    {
        return extension_loaded('grpc') && class_exists(CbirServiceClient::class);
    }

    /**
     * Pencarian CBIR berbasis gambar.
     *
     * @param  string  $imageBytes  isi file gambar (bukan path)
     * @return array{success: bool, results: array<int, array{owner_id:int,type:string,score:float,similarity:float,image_url:?string}>, query_time_seconds: float, transport: string}
     */
    public function search(
        string $imageBytes,
        int $limit = 10,
        ?float $minSimilarity = null,
        bool $rerank = true,
    ): array {
        $request = (new CbirSearchRequest())
            ->setImageData($imageBytes)
            ->setLimit($limit)
            ->setMinSimilarity($minSimilarity ?? (float) config('services.cbir_min_similarity', 30.0))
            ->setRerank($rerank);

        /** @var \App\Grpc\AiCore\CbirSearchResponse $response */
        $response = $this->call('Search', $request);

        if ($response->getError() !== '') {
            throw new RuntimeException('AI Core gRPC error: '.$response->getError());
        }

        $results = [];

        foreach ($response->getResults() as $item) {
            $data = $item->getData();

            // `id` kosong berarti server tidak menemukan baris yang cocok;
            // hasil seperti itu tidak bisa dipakai untuk memuat model.
            if (! $data || $data->getId() <= 0) {
                continue;
            }

            $similarity = (float) $item->getSimilarity();

            $results[] = [
                'owner_id' => (int) $data->getId(),
                'type' => (string) ($item->getType() ?: 'catalog'),
                'score' => (float) $item->getScore(),
                'similarity' => $similarity,
                'image_url' => $data->getImageUrl() ?: null,
            ];
        }

        return [
            'success' => true,
            'results' => $results,
            'query_time_seconds' => (float) $response->getSearchTime(),
            'transport' => 'grpc',
        ];
    }

    /**
     * Statistik indeks CBIR (jumlah gambar, ukuran database, status).
     *
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        /** @var \App\Grpc\AiCore\CbirStatsResponse $response */
        $response = $this->call('GetStats', new CbirStatsRequest());

        return [
            'total_images' => $response->getTotalImages(),
            'total_features' => $response->getTotalFeatures(),
            'database_size' => $response->getDatabaseSize(),
            'last_updated' => $response->getLastUpdated(),
            'status' => $response->getStatus(),
            'message' => $response->getMessage(),
        ];
    }

    /**
     * Health check ringan untuk memastikan server gRPC hidup.
     *
     * @return array{status: string, version: string, cbir_enabled: bool}
     */
    public function health(): array
    {
        /** @var \App\Grpc\AiCore\HealthResponse $response */
        $response = $this->call('GetHealth', new HealthRequest());

        return [
            'status' => $response->getStatus(),
            'version' => $response->getVersion(),
            'cbir_enabled' => $response->getCbirEnabled(),
        ];
    }

    /**
     * Tambah gambar ke indeks CBIR via path server.
     *
     * @param  string  $imagePath  path absolut di mesin AI Core (bukan upload bytes)
     * @param  array<string, string>  $metadata  id/type/owner_id/image_url/name
     */
    public function addToIndex(string $imagePath, array $metadata = []): array
    {
        $request = (new AddToIndexRequest())
            ->setImagePath($imagePath);

        foreach ($metadata as $key => $value) {
            $request->getMetadata()[$key] = (string) $value;
        }

        /** @var \App\Grpc\AiCore\AddToIndexResponse $response */
        $response = $this->call('AddToIndex', $request);

        return [
            'success' => $response->getSuccess(),
            'message' => $response->getMessage(),
            'entry_id' => $response->getEntryId(),
        ];
    }

    /**
     * Hapus gambar dari indeks berdasarkan metadata_id (= media_id Laravel).
     */
    public function removeFromIndex(int $mediaId): array
    {
        $request = (new RemoveFromIndexRequest())->setMetadataId($mediaId);

        /** @var \App\Grpc\AiCore\RemoveFromIndexResponse $response */
        $response = $this->call('RemoveFromIndex', $request);

        return [
            'success' => $response->getSuccess(),
            'message' => $response->getMessage(),
            'removed' => $response->getRemoved(),
        ];
    }

    private function call(string $method, Message $request): Message
    {
        if (! self::isSupported()) {
            throw new RuntimeException('Extension PHP "grpc" tidak tersedia.');
        }

        try {
            /** @var array{0: Message, 1: object} $result */
            $metadata = [];
            if ($key = config('services.ai_core_api_key')) {
                $metadata['x-ai-core-key'] = [$key];
            }

            $result = $this->client()
                // `timeout` milik php-grpc dihitung dalam MIKRODETIK, bukan
                // milidetik dan bukan detik. Terukur di extension 1.81:
                // 30 -> gagal ~14 ms, 30000 -> gagal ~32 ms, 120000 -> gagal
                // ~128 ms, sedangkan 5000000 -> sukses. Karena itu nilai
                // konfigurasi (ms) dikali 1000 di sini, satu-satunya tempat.
                ->{$method}($request, $metadata, ['timeout' => $this->timeoutMs * 1000])
                ->wait();

            [$response, $status] = $result;

            if (($status->code ?? -1) !== \Grpc\STATUS_OK) {
                throw new RuntimeException(sprintf(
                    'AI Core gRPC %s gagal (code %s): %s',
                    $method,
                    $status->code ?? '?',
                    $status->details ?? 'tanpa detail'
                ));
            }

            if (! $response instanceof Message) {
                throw new RuntimeException("AI Core gRPC {$method}: respons kosong.");
            }

            return $response;
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::warning('AI Core gRPC call failed', [
                'method' => $method,
                'host' => $this->host,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('AI Core gRPC tidak dapat dihubungi: '.$e->getMessage(), 0, $e);
        }
    }

    private function client(): CbirServiceClient
    {
        if ($this->client === null) {
            $credentials = config('services.ai_core_grpc_tls', false)
                ? ChannelCredentials::createSsl()
                : ChannelCredentials::createInsecure();

            $this->client = new CbirServiceClient($this->host, ['credentials' => $credentials]);
        }

        return $this->client;
    }
}
