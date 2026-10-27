<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GraphQLMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Do not allow queries in URLs: GET puts credentials, personal data,
        // and query variables into browser/proxy history and access logs.
        if ($request->isMethod('get')) {
            return response()->json(['errors' => [['message' => 'GraphQL GET is disabled.']]], 405)
                ->header('Allow', 'POST');
        }

        if ($request->isMethod('options')) {
            return response()->noContent(204);
        }

        // Ensure content type is application/json for POST requests
        if ($request->isMethod('post') && !$request->isJson()) {
            // Allow GraphQL queries with application/json content type
            if ($request->header('Content-Type') !== 'application/json') {
                return response()->json([
                    'errors' => [
                        [
                            'message' => 'Content-Type must be application/json',
                        ],
                    ],
                ], 415);
            }
        }

        $response = $next($request);

        $origin = (string) $request->headers->get('Origin');
        $allowedOrigins = array_values(array_filter((array) config('cors.allowed_origins', [])));
        if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Methods', 'POST, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-CSRF-TOKEN');
            $response->headers->set('Vary', 'Origin');
        }

        return $response;
    }
}
