<?php

namespace App\Http\Controllers\DatabaseProxyController;

use App\Http\Controllers\Controller;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * DatabaseProxyController
 *
 * Acts as a secure SQL relay between NativePHP mobile apps and the MySQL
 * database running on the developer's machine.
 *
 * Security:
 *   - Requires X-DB-PROXY-SECRET header matching NATIVE_DB_PROXY_SECRET
 *   - Only accepts POST requests (enforced by route definition)
 *   - Should NEVER be exposed on a production server
 *
 * Route: POST /api/db-proxy
 */
class DatabaseProxyController extends Controller
{
    public function proxy(Request $request): JsonResponse
    {
        // ── 1. SECURITY CHECK ─────────────────────────────────────────────
        $secret = $request->header('X-DB-PROXY-SECRET');
        $validSecret = env('NATIVE_DB_PROXY_SECRET', 'nativephp-db-proxy-secret-2024');

        if (! $secret || $secret !== $validSecret) {
            Log::warning('[DB Proxy] Unauthorized access attempt from '.$request->ip());

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // ── 2. VALIDATE INPUT ─────────────────────────────────────────────
        $method = $request->input('method');
        $query = $request->input('query');
        $bindings = $request->input('bindings', []);

        $allowedMethods = ['select', 'insert', 'update', 'delete', 'statement', 'selectOne'];

        if (! in_array($method, $allowedMethods, true)) {
            return response()->json(['error' => "Unsupported method: {$method}"], 400);
        }

        if (empty($query)) {
            return response()->json(['error' => 'Missing query'], 400);
        }

        // ── 3. EXECUTE ────────────────────────────────────────────────────
        try {
            $result = match ($method) {
                'select' => DB::select($query, $bindings),
                'selectOne' => DB::selectOne($query, $bindings),
                'insert' => $this->runInsert($query, $bindings),
                'update' => DB::update($query, $bindings),
                'delete' => DB::delete($query, $bindings),
                'statement' => DB::statement($query, $bindings),
            };

            return response()->json(['result' => $result]);

        } catch (\Throwable $e) {
            Log::error('[DB Proxy] Query failed: '.$e->getMessage(), [
                'method' => $method,
                'query' => $query,
            ]);

            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Run INSERT and return last insert ID so mobile can get the new record's ID.
     */
    private function runInsert(string $query, array $bindings): array
    {
        DB::insert($query, $bindings);

        return [
            'success' => true,
            'last_insert_id' => (int) DB::getPdo()->lastInsertId(),
        ];
    }
}
