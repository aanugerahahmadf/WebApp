<?php

namespace App\Http\Middleware\FilamentUserContext;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FilamentUserContext
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            Filament::setCurrentPanel(Filament::getPanel('user'));
        } catch (\Exception $e) {
            // Panel not found or other issue
        }

        return $next($request);
    }
}
