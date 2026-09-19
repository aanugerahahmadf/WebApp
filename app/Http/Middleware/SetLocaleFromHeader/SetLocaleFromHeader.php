<?php

namespace App\Http\Middleware\SetLocaleFromHeader;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->header('Accept-Language', 'id');

        // Support both 'en-US' and 'en' formats
        if (strlen($locale) > 2) {
            $locale = substr($locale, 0, 2);
        }

        $supportedLocales = ['id', 'en'];
        if (in_array($locale, $supportedLocales)) {
            App::setLocale($locale);
        } else {
            App::setLocale('id');
        }

        return $next($request);
    }
}
