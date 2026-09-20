<?php

use App\Enums\PlatformMode\PlatformMode;
use App\Http\Middleware\SetLocale\SetLocale;
use App\Http\Middleware\VerifyCsrfToken\VerifyCsrfToken;
use App\Providers\AutoTranslationServiceProvider\AutoTranslationServiceProvider;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

// Symfony uses temporary files for `php artisan serve` on Windows. Keep them
// inside the project so Artisan works even when the system temp path is locked.
if (PHP_OS_FAMILY === 'Windows') {
    $tempDirectory = dirname(__DIR__).DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'temp';

    if (! is_dir($tempDirectory) && ! mkdir($tempDirectory, 0777, true) && ! is_dir($tempDirectory)) {
        fwrite(STDERR, "Unable to create Laravel temporary directory: {$tempDirectory}".PHP_EOL);
        exit(1);
    }

    putenv("TMP={$tempDirectory}");
    putenv("TEMP={$tempDirectory}");
    putenv("TMPDIR={$tempDirectory}");
}

$app = Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        AutoTranslationServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web/web.php',
        api: __DIR__.'/../routes/api/api.php',
        commands: __DIR__.'/../routes/console/console.php',
        channels: __DIR__.'/../routes/channels/channels.php',
        health: '/up',
        then: function (): void {
            // ── Platform-Specific Route Registration (Requirements 9.1–9.4, 9.6) ──────
            // Load mobile-specific routes only when running in Mobile Native Mode.
            // These routes are prefixed with "api/mobile" and use the "api" middleware group.
            $mode = app('platform.mode');

            if ($mode === PlatformMode::Mobile && file_exists(base_path('routes/mobile/mobile.php'))) {
                Route::middleware('api')
                    ->prefix('api/mobile')
                    ->group(base_path('routes/mobile/mobile.php'));
            }

            // Load desktop-specific routes only when running in Desktop App Mode.
            // These routes are prefixed with "api/desktop" and use the "api" middleware group.
            if ($mode === PlatformMode::Desktop && file_exists(base_path('routes/desktop/desktop.php'))) {
                Route::middleware('api')
                    ->prefix('api/desktop')
                    ->group(base_path('routes/desktop/desktop.php'));
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Mendaftarkan middleware SetLocale ke group web agar session dan auth tersedia
        $middleware->web(append: [
            SetLocale::class,
        ]);

        // Mendaftarkan middleware SetLocale ke group api untuk sinkronisasi bahasa aplikasi mobile
        $middleware->api(append: [
            SetLocale::class,
        ]);

        // Define mobile group for NativePHP
        $middleware->group('mobile', [
            EncryptCookies::class,
            StartSession::class,
            SetLocale::class,
        ]);

        $middleware->replace(ValidateCsrfToken::class, VerifyCsrfToken::class);

        $middleware->redirectGuestsTo(fn () => route('filament.user.auth.login'));

        // Trust proxies for production deployments or ngrok development.
        if (env('APP_ENV') === 'production' ||
            str_contains((string) env('APP_URL'), 'ngrok-free.dev') ||
            (isset($_SERVER['HTTP_X_FORWARDED_HOST']) && str_contains($_SERVER['HTTP_X_FORWARDED_HOST'], 'ngrok')) ||
            (isset($_SERVER['HTTP_HOST']) && str_contains($_SERVER['HTTP_HOST'], 'ngrok'))
        ) {
            $middleware->trustProxies('*');
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

return $app;
