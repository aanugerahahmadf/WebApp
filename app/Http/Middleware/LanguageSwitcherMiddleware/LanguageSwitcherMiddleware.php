<?php

namespace App\Http\Middleware\LanguageSwitcherMiddleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LanguageSwitcherMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response|RedirectResponse)  $next
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()) {
            if (! empty($request->user()->lang)) {
                app()->setLocale($request->user()->lang);
            } else {
                app()->setLocale('en');
            }
        }

        return $next($request);
    }
}
