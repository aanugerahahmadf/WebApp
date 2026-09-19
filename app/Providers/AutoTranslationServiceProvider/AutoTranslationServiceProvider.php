<?php

namespace App\Providers\AutoTranslationServiceProvider;

use App\Services\AutoTranslationService\AutoTranslationService;
use App\Translators\AutoTranslator\AutoTranslator;
use Illuminate\Support\ServiceProvider;

class AutoTranslationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Force application to use root /lang directory
        $this->app->useLangPath(base_path('lang'));

        // Daftarkan AutoTranslationService sebagai singleton
        $this->app->singleton(AutoTranslationService::class);

        // Override translator Laravel dengan AutoTranslator kita
        $this->app->extend('translator', function ($original, $app) {
            $loader = $app['translation.loader'];
            $locale = $app['config']['app.locale'];

            $translator = new AutoTranslator($loader, $locale);
            $translator->setFallback($app['config']['app.fallback_locale']);
            $translator->setAutoTranslationService($app->make(AutoTranslationService::class));

            return $translator;
        });
    }

    public function boot(): void
    {
        //
    }
}
