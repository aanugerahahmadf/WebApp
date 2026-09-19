<?php

use Illuminate\Support\Facades\Route;

Route::get('/debug-google', function () {
    return [
        'app_url' => config('app.url'),
        'google_redirect' => config('services.google.redirect'),
        'current_url' => url()->current(),
        'scheme' => request()->getScheme(),
        'is_secure' => request()->isSecure(),
        'all_headers' => request()->headers->all(),
    ];
});
