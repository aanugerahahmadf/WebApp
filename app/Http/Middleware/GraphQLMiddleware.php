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

        // Add CORS headers for GraphQL endpoint
        $response = $next($request);

        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'POST, GET, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization');

        return $response;
    }
}