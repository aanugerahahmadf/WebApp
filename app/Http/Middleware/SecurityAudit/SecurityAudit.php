<?php

namespace App\Http\Middleware\SecurityAudit;

use App\Models\SecurityEvent\SecurityEvent;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SecurityAudit
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $status = $response->getStatusCode();
        $mutating = ! $request->isMethodSafe();
        $securityResponse = in_array($status, [401, 403, 419, 429], true);

        if (! $mutating && ! $securityResponse) {
            return $response;
        }

        try {
            $user = Auth::guard('sanctum')->user() ?? Auth::guard('web')->user();
            $admin = Auth::guard('admin')->user();

            SecurityEvent::create([
                'event' => $securityResponse ? 'blocked_request' : 'mutating_request',
                'method' => $request->method(),
                'path' => $request->path(),
                'status' => $status,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'user_id' => $user?->getKey(),
                'admin_id' => $admin?->getKey(),
                'metadata' => ['route' => $request->route()?->getName()],
            ]);
        } catch (\Throwable) {
            // Security telemetry must never turn a successful request into a 500.
        }

        return $response;
    }
}
