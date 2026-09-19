<?php

namespace App\Http\Middleware\ClerkFilamentAuth;

use App\Models\User\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class ClerkFilamentAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->query('clerk_token') ?? $request->cookie('clerk_token');

        if ($token && ! Auth::check()) {
            $accessToken = PersonalAccessToken::findToken($token);
            if ($accessToken) {
                $user = $accessToken->tokenable;
                if ($user && $user instanceof User) {
                    Auth::login($user);
                }
            }
        }

        return $next($request);
    }
}
