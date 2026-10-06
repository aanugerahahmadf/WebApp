<?php

use App\Http\Middleware\SetLocale\SetLocale;
use App\Http\Middleware\VerifyCsrfToken\VerifyCsrfToken;
use App\Providers\AutoTranslationServiceProvider\AutoTranslationServiceProvider;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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

        // Define the mobile middleware group, used by the Capacitor shells
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
        /*
         * Hilangkan 419 "Page Expired" untuk request halaman biasa.
         *
         * ⚠️ KHUSUS request Livewire TIDAK boleh di-redirect di sini.
         *
         * Livewire punya mekanisme redirect sendiri: pada request update,
         * redirect dikirim sebagai efek di payload JSON
         * (Features\SupportRedirects::dehydrate()), bukan sebagai HTTP 302.
         * Kalau di sini mengembalikan 302, browser akan transparan
         * mengikutiLocation itu dengan GET, padahal /livewire/update hanya
         * menerima POST -- hasilnya 405 Method Not Allowed. Itu bukan
         * perbaikan, itu bug baru.
         *
         * Request Livewire yang kena 419 dibiarkan apa adanya: livewire.js
         * sudah menangani-nya dengan dialog "refresh halaman?" yang graceful.
         *
         * 419 di app ini datang dari Livewire, bukan dari sesi --
         * AppServiceProvider sudah memaksa session.lifetime 525600 (1 tahun)
         * dengan lottery [0,100] sehingga sesi tidak pernah kedaluwarsa dan
         * tidak pernah di-gc. Penyebabnya: release token snapshot lama
         * tidak cocok (Livewire\Features\SupportReleaseTokens\ReleaseToken),
         * yangMisalnya terjadi kalau class component dipindah/berubah nama
         * sementara tab masih terbuka.
         */
        $exceptions->respond(function ($response, Throwable $exception) {
            if (! app()->bound('livewire')) {
                return $response;
            }

            $status = $exception instanceof HttpExceptionInterface
                ? $exception->getStatusCode()
                : null;

            // Request Livewire: JANGAN di-redirect (menghasilkan 405).
            // Tapi catat dulu, karena 419 tidak pernah di-report Laravel
            // (HttpException 4xx tidak di-report) sehingga tanpa ini
            // penyebabnya mustahil diketahui dari log.
            if (app('livewire')->isLivewireRequest()) {
                if ($status === 419) {
                    Log::warning('Livewire 419 (tidak di-report Laravel, dicatat manual)', [
                        'exception' => $exception::class,
                        'message' => $exception->getMessage(),
                        'path' => request()->path(),
                        'referer' => request()->header('referer'),
                    ]);
                }

                return $response;
            }

            if ($status !== 419) {
                return $response;
            }

            $target = request()->fullUrl();

            // Jangan redirect ke root ke dirinya sendiri, dan jangan biarkan
            // query string ikut terbawa.
            if ($target === url('/') || str_contains($target, '?')) {
                $target = url('/');
            }

            return redirect()->to($target);
        });
    })->create();

return $app;
