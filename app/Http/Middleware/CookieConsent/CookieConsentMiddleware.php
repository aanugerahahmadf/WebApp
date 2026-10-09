<?php

namespace App\Http\Middleware\CookieConsent;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware untuk menambahkan cookie consent ke response.
 * Cookie 'cookie_consent' menyimpan preferensi user: 'accepted', 'rejected', atau 'essential_only'.
 */
class CookieConsentMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Jika cookie consent sudah ada, tidak perlu do anything
        if ($request->hasCookie('cookie_consent')) {
            return $response;
        }

        // Untuk request AJAX/API, tambahkan header info
        if ($request->expectsJson() || $request->is('api/*')) {
            $response->headers->set('X-Cookie-Consent', 'not_set');
        }

        return $response;
    }
}