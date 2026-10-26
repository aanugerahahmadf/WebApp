<?php

namespace App\Http\Middleware\EmergencyLockdown;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EmergencyLockdown
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('security.emergency_lockdown') || $request->isMethodSafe()) {
            return $next($request);
        }

        $configuredToken = config('security.emergency_bypass_token');
        $providedToken = (string) $request->header('X-Emergency-Bypass');

        if ($configuredToken && $providedToken && hash_equals((string) $configuredToken, $providedToken)) {
            return $next($request);
        }

        return response()->json([
            'status' => 'emergency_lockdown',
            'message' => 'Server sedang dalam mode pemulihan keamanan. Silakan coba lagi nanti.',
        ], 503)->header('Retry-After', '300');
    }
}
