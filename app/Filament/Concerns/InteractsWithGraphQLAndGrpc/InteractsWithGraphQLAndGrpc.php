<?php

namespace App\Filament\Concerns\InteractsWithGraphQLAndGrpc;

use App\Services\GraphQLExecutor\GraphQLExecutor;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * Trait InteractsWithGraphQLAndGrpc
 *
 * Memberikan kemampuan kepada komponen, Resource, atau Page Filament untuk
 * mengeksekusi kueri GraphQL dan panggilan layanan gRPC secara konsisten dan tangguh.
 */
trait InteractsWithGraphQLAndGrpc
{
    /**
     * Eksekusi GraphQL Query atau Mutation secara langsung dari konteks Filament.
     *
     * @param string $query String GraphQL Query atau Mutation
     * @param array $variables Variabel pendukung query
     * @param string|null $operationName Nama operasi (jika query memiliki banyak operasi)
     * @param array $contextData Konteks tambahan (mis. ['user' => ...])
     * @return array Hasil eksekusi ['data' => ..., 'errors' => ...]
     */
    public function executeGraphQL(
        string $query,
        array $variables = [],
        ?string $operationName = null,
        array $contextData = []
    ): array {
        try {
            /** @var GraphQLExecutor $executor */
            $executor = App::make(GraphQLExecutor::class);
            return $executor->execute($query, $variables, $operationName, $contextData);
        } catch (\Throwable $e) {
            Log::error('Filament InteractsWithGraphQLAndGrpc error: ' . $e->getMessage(), [
                'query' => $query,
                'variables' => $variables,
            ]);

            return [
                'data' => null,
                'errors' => [
                    [
                        'message' => $e->getMessage(),
                    ],
                ],
            ];
        }
    }

    /**
     * Eksekusi GraphQL Query dan ambil hanya blok data-nya.
     */
    public function executeGraphQLData(string $query, array $variables = [], array $contextData = []): mixed
    {
        $result = $this->executeGraphQL($query, $variables, null, $contextData);

        if (! empty($result['errors'])) {
            throw new \RuntimeException($result['errors'][0]['message'] ?? 'GraphQL execution failed in Filament');
        }

        return $result['data'] ?? null;
    }

    /**
     * Eksekusi gRPC Service Method secara in-app atau RPC socket.
     *
     * @param string $serviceClass FQCN dari gRPC Service (mis. \App\Grpc\User\V1\CatalogService::class atau \App\Grpc\Services\CatalogService::class)
     * @param string $method Nama method pada service gRPC
     * @param mixed $request Objek request gRPC atau payload array
     * @return mixed Respon yang dikembalikan service
     */
    public function executeGrpc(string $serviceClass, string $method, mixed $request = null): mixed
    {
        try {
            if (! class_exists($serviceClass)) {
                throw new \InvalidArgumentException("gRPC Service class [{$serviceClass}] tidak ditemukan.");
            }

            $serviceInstance = App::make($serviceClass);

            if (! method_exists($serviceInstance, $method)) {
                throw new \BadMethodCallException("Method [{$method}] tidak ditemukan pada service gRPC [{$serviceClass}].");
            }

            // Panggil method service gRPC
            if ($request !== null) {
                return $serviceInstance->{$method}($request);
            }

            return $serviceInstance->{$method}();
        } catch (\Throwable $e) {
            Log::error("gRPC execution failed: {$serviceClass}@{$method} - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Cek status ketersediaan koneksi dan modul gRPC & GraphQL.
     */
    public static function checkApiEnginesHealth(): array
    {
        $graphqlOk = false;
        $graphqlError = null;
        try {
            /** @var GraphQLExecutor $executor */
            $executor = App::make(GraphQLExecutor::class);
            $res = $executor->execute('{ __typename }');
            $graphqlOk = isset($res['data']['__typename']);
        } catch (\Throwable $e) {
            $graphqlError = $e->getMessage();
        }

        $grpcProtoCount = 0;
        $protoPath = base_path('proto');
        if (\Illuminate\Support\Facades\File::isDirectory($protoPath)) {
            $grpcProtoCount = count(\Illuminate\Support\Facades\File::glob($protoPath . '/*.proto'));
        }

        return [
            'graphql' => [
                'status' => $graphqlOk ? 'healthy' : 'degraded',
                'endpoint' => url('/graphql'),
                'error' => $graphqlError,
            ],
            'grpc' => [
                'status' => 'ready',
                'server_config' => base_path('.rr.yaml'),
                'proto_count' => $grpcProtoCount,
                'services_namespace' => 'App\\Grpc',
            ],
            'overall' => ($graphqlOk && $grpcProtoCount > 0) ? 'healthy' : 'degraded',
        ];
    }
}
