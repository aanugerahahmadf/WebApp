<?php

use App\Http\Middleware\SetLocale\SetLocale;
use App\Http\Middleware\VerifyCsrfToken\VerifyCsrfToken;
use App\Providers\AutoTranslationServiceProvider\AutoTranslationServiceProvider;
use App\Http\Middleware\SecurityHeaders\SecurityHeaders;
use App\Http\Middleware\EmergencyLockdown\EmergencyLockdown;
use App\Http\Middleware\SecurityAudit\SecurityAudit;
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
        // Panel admin tanpa pagination: naikkan batas waktu HANYA untuk
        // /admin* dan /livewire/* (tabel 611 baris). Lihat class-nya untuk
        // alasan lengkapnya.
        $middleware->append(\App\Http\Middleware\AdminExecutionTime\AdminExecutionTime::class);
        $middleware->append(SecurityHeaders::class);

        // Mendaftarkan middleware SetLocale ke group web agar session dan auth tersedia
        $middleware->web(append: [
            SetLocale::class,
            \App\Http\Middleware\CookieConsent\CookieConsentMiddleware::class,
        ]);

        // Mendaftarkan middleware SetLocale ke group api untuk sinkronisasi bahasa aplikasi mobile
        $middleware->api(append: [
            SetLocale::class,
            \Illuminate\Http\Middleware\HandleCors::class,
            'throttle:api',
            EmergencyLockdown::class,
            SecurityAudit::class,
        ]);

        // Define the mobile middleware group, used by the Capacitor shells
        $middleware->group('mobile', [
            EncryptCookies::class,
            StartSession::class,
            SetLocale::class,
        ]);

        $middleware->replace(ValidateCsrfToken::class, VerifyCsrfToken::class);

        // Pengecualian CSRF yang BENAR-BENAR berlaku untuk request web.
        //
        // `replace()` di atas HANYA berlaku untuk middleware GLOBAL.
        // `ValidateCsrfToken` ada di grup `web`, bukan global, jadi
        // penggantian itu praktis tidak berpengaruh: grup `web` tetap
        // menjalankan middleware dasar, yang `$except`-nya kosong.
        //
        // Yang dipakai grup `web` adalah middleware dasar itu sendiri, jadi
        // pengecualian harus didaftarkan di sana lewat
        // `validateCsrfTokens(except: ...)` -- yang menulis ke daftar
        // `neverVerify` milik middleware dasar.
        //
        // Kenapa `livewire/*` wajib ada di sini: setiap klik pada halaman
        // Filament = POST /livewire/update, dan token sesi BERUBAH setiap
        // kali user login (OTP/2FA memanggil `regenerateToken()`). Tab lama
        // masih memegang token lama, jadi request-nya kena 419 dan
        // livewire.js memunculkan dialog "This page has expired. Would you
        // like to refresh the page?" tepat setelah unduhan data selesai.
        //
        // `logout` / `user/logout` / `welcome/logout` ikut dikecualikan
        // karena logout itu idempotent: paling buruk korban logout, dan
        // bentuknya justru session yang sudah tidak berlaku.
        //
        // SENGAJA TIDAK ada `admin/*` di sini. Wildcard itu ada di
        // `App\Http\Middleware\VerifyCsrfToken` dan sudah membebaskan
        // seluruh panel admin dari CSRF; menambahkannya di sini berarti
        // membebaskan juga endpoint non-panel di bawah /admin (mis.
        // admin.reviews.vote) yang selama ini masih dijaga middleware
        // dasar. Persempitannya keputusan tersendiri, bukan efek samping.
        $middleware->validateCsrfTokens(except: [
            'livewire/*',
            'logout',
            'user/logout',
            'welcome/logout',
            'api/*',
        ]);

        $middleware->redirectGuestsTo(fn () => route('filament.welcome.auth.index'));

        // Trust proxies for production deployments or edge/CDN hosting.
        // Domain yang dikenali: Cloudflare Quick Tunnel (trycloudflare.com),
        // localtunnel (loca.lt), dan edge Whop Hosting
        // (*.whop.site, dilayani Cloudflare).
        // Tanpa ini, asset URL / link Storage dan redirect auth akan tetap
        // menunjuk ke host lama karena Laravel cuma lihat proxy.
        $edgeHosts = ['trycloudflare.com', 'loca.lt', 'localtunnel', 'lhr.life', 'whop.site'];
        $appUrl = (string) env('APP_URL');
        $forwardedHost = (string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? '');
        $httpHost = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $isEdgeFronted = (bool) array_filter(
            $edgeHosts,
            fn (string $needle) => str_contains($appUrl, $needle)
                || str_contains($forwardedHost, $needle)
                || str_contains($httpHost, $needle)
        );

        if (env('APP_ENV') === 'production' || $isEdgeFronted) {
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

/*
 * Handler exception milik aplikasi, bukan bawaan Laravel.
 *
 * KENAPA PERLU DI-BIND DI SINI:
 *
 *   Laravel 11+ mengikat `Illuminate\Contracts\Debug\ExceptionHandler` ke
 *   `Illuminate\Foundation\Exceptions\Handler` DI DALAM
 *   `ApplicationBuilder::__construct()` -- yaitu sebelum provider apa pun
 *   berjalan. Kalau tidak di-bind ulang, `App\Exceptions\Handler\Handler`
 *   hanya kode mati: tidak pernah di-resolve, tidak pernah dipakai.
 *
 *   Akibatnya semua view error milik aplikasi
 *   (`Welcome/errors/*`, `User/errors/*`, `Admin/errors/*`) TIDAK PERNAH
 *   dirender -- yang muncul adalah halaman polos bawaan Laravel.
 *
 *   Handler ini yang memilih view per panel: request di /admin/* memakai
 *   `Admin.errors.*`, sisanya `Welcome.errors.*`.
 *
 * CATATAN SOAL `withExceptions()`:
 *
 *   Callback 419 (lihat blok di atas) TIDAK hilang. `withExceptions()`
 *   mendaftarkan `afterResolving` untuk kelas
 *   `Illuminate\Foundation\Exceptions\Handler`; kelas milik aplikasi
 *   adalah turunannya, jadi callback itu tetap terpanggil -- hanya
 *   sekarang ia menerima instance handler milik aplikasi.
 */
$app->singleton(
    \Illuminate\Contracts\Debug\ExceptionHandler::class,
    \App\Exceptions\Handler\Handler::class,
);

return $app;
