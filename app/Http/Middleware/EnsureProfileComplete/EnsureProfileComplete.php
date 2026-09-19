<?php

namespace App\Http\Middleware\EnsureProfileComplete;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return redirect()->route('filament.user.auth.login');
        }

        $user = Auth::user();

        if ($user->hasRole('super_admin')) {
            return $next($request);
        }

        if (method_exists($user, 'isProfileComplete') && !$user->isProfileComplete()) {
            if ($request->routeIs('filament.user.pages.complete-profile')) {
                return $next($request);
            }

            if ($request->routeIs('filament.user.auth.*')) {
                return $next($request);
            }

            if ($request->is('user/complete-profile')) {
                return $next($request);
            }

            return redirect()->route('filament.user.pages.complete-profile');
        }

        return $next($request);
    }
}
