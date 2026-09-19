<?php

namespace App\Database\MySqlProxyConnection;

use Illuminate\Database\MySqlConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MySqlProxyConnection
 *
 * Intercepts ALL database queries and forwards them via HTTP to the
 * /api/db-proxy endpoint running on the developer's PC.
 *
 * This is needed because NativePHP's bundled PHP (Android/iOS) does NOT
 * include the pdo_mysql extension, so we can't connect to MySQL directly
 * from the device.
 *
 * Architecture:
 *   Device (NativePHP)
 *     └─► POST http://10.0.2.2/api/db-proxy   (Android emulator)
 *     └─► POST http://127.0.0.1/api/db-proxy  (iOS simulator / LAN)
 *           └─► DatabaseProxyController → DB::select/insert/update/delete
 *                 └─► MySQL on dev machine
 *                       └─► JSON response back to device
 */
class MySqlProxyConnection extends MySqlConnection
{
    protected ?string $proxyUrl;

    protected ?string $proxySecret;

    protected $lastInsertId = 0;

    public function __construct($pdo, $database = '', $tablePrefix = '', array $config = [])
    {
        parent::__construct($pdo, $database, $tablePrefix, $config);
        $this->proxyUrl = $config['proxy_url'] ?? null;
        $this->proxySecret = $config['proxy_secret'] ?? null;
    }

    /**
     * Request-level cache to avoid repeating identical SELECTs in the same request.
     */
    protected static array $queryCache = [];

    public function select($query, $bindings = [], $useReadPdo = true): array
    {
        // Jangan cache query yang berkaitan dengan auth/session — data harus selalu fresh
        $skipCachePatterns = ['users', 'sessions', 'personal_access_tokens', 'model_has_roles', 'roles'];
        $shouldCache = true;
        foreach ($skipCachePatterns as $pattern) {
            if (stripos($query, $pattern) !== false) {
                $shouldCache = false;
                break;
            }
        }

        $cacheKey = md5($query.serialize($bindings));

        if ($shouldCache && isset(static::$queryCache[$cacheKey])) {
            return static::$queryCache[$cacheKey];
        }

        $result = $this->runProxy('select', $query, $bindings);

        if (! is_array($result)) {
            Log::warning('[MySqlProxy] Select returned non-array result for query: '.$query);

            return [];
        }

        $rows = array_map(fn ($row) => (object) (array) $row, $result);

        if ($shouldCache) {
            static::$queryCache[$cacheKey] = $rows;
        }

        return $rows;
    }

    public function selectOne($query, $bindings = [], $useReadPdo = true): mixed
    {
        $rows = $this->select($query, $bindings, $useReadPdo);

        return $rows[0] ?? null;
    }

    public function insert($query, $bindings = [], $sequence = null): bool
    {
        $this->clearQueryCache();

        $result = $this->runProxy('insert', $query, $bindings);

        // Store last insert ID jika proxy mengembalikannya
        if (is_array($result) && isset($result['last_insert_id'])) {
            $this->lastInsertId = (int) $result['last_insert_id'];
        }

        return is_array($result) ? ($result['success'] ?? true) : (bool) $result;
    }

    public function update($query, $bindings = []): int
    {
        $this->clearQueryCache();

        return (int) $this->runProxy('update', $query, $bindings);
    }

    public function delete($query, $bindings = []): int
    {
        $this->clearQueryCache();

        return (int) $this->runProxy('delete', $query, $bindings);
    }

    public function statement($query, $bindings = []): bool
    {
        $this->clearQueryCache();

        return (bool) $this->runProxy('statement', $query, $bindings);
    }

    public function affectingStatement($query, $bindings = []): int
    {
        $this->clearQueryCache();

        return (int) $this->runProxy('statement', $query, $bindings);
    }

    protected function clearQueryCache(): void
    {
        static::$queryCache = [];
    }

    // ── PDO Compatibility Stubs ───────────────────────────────────────────
    // NativePHP doesn't have PDO, so these return safe fake values.

    public function getPdo(): \PDO
    {
        // Attempt to verify the proxy is alive, then return a stub
        // (PDO is never actually used by this class)
        return new class extends \PDO
        {
            public function __construct() {} // skip real PDO constructor
        };
    }

    public function getReadPdo(): \PDO
    {
        return $this->getPdo();
    }

    /**
     * Return the last insert ID dari INSERT yang terakhir dijalankan via proxy.
     */
    public function lastInsertId(): int
    {
        return $this->lastInsertId;
    }

    // ── Core Proxy Method ─────────────────────────────────────────────────

    /**
     * Send a SQL query to the remote dev-machine proxy over HTTP.
     *
     * @throws \RuntimeException on proxy failure
     */
    protected function runProxy(string $method, string $query, array $bindings): mixed
    {
        // ── Fallback: if no proxy URL, let the parent try (will fail on device,
        //             but works fine on Windows dev machine running as website)
        if (empty($this->proxyUrl)) {
            // Log::warning('[MySqlProxy] proxy_url is empty — falling back to direct MySQL. Method: ' . $method);
            return parent::{$method}($query, $bindings);
        }

        try {
            // Optimized: shorter timeout, single retry untuk mobile responsiveness
            $response = Http::timeout(8)
                ->retry(1, 50)
                ->withHeaders([
                    'X-DB-PROXY-SECRET' => $this->proxySecret,
                    'Accept' => 'application/json',
                    'Connection' => 'keep-alive',
                ])
                ->post($this->proxyUrl, [
                    'method' => $method,
                    'query' => $query,
                    'bindings' => $bindings,
                ]);

            if ($response->failed()) {
                $errorBody = $response->json('error') ?? $response->body();
                $statusCode = $response->status();
                throw new \RuntimeException(
                    "[MySqlProxy] HTTP {$statusCode} from proxy: {$errorBody}"
                );
            }

            return $response->json('result');

        } catch (ConnectionException $e) {
            $msg = "[MySqlProxy] Cannot reach proxy at {$this->proxyUrl}: ".$e->getMessage();
            Log::error($msg);
            throw new \RuntimeException($msg, 0, $e);
        } catch (\RuntimeException $e) {
            Log::error('[MySqlProxy] Proxy error: '.$e->getMessage());
            throw $e;
        } catch (\Throwable $e) {
            Log::error('[MySqlProxy] Unexpected error: '.$e->getMessage());
            throw new \RuntimeException('[MySqlProxy] '.$e->getMessage(), 0, $e);
        }
    }
}
