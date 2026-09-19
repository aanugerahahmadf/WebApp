<?php

namespace App\Providers\NativeServiceProvider;

use App\Models\User\User;
use App\Support\PlatformContext\PlatformContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Native\Mobile\Network;
use Native\Mobile\Providers\CameraServiceProvider;
use Native\Mobile\Providers\DeviceServiceProvider;
use Native\Mobile\Providers\FileServiceProvider;
use Native\Mobile\Providers\NetworkServiceProvider;
use Native\Mobile\Providers\SystemServiceProvider;
use Native\Mobile\System;
use SRWieZ\NativePHP\Mobile\Screen\ScreenServiceProvider;

class NativeServiceProvider extends ServiceProvider
{
    // ═══════════════════════════════════════════════════════════════════════
    // PLATFORM DETECTION HELPERS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Returns true if running inside a NativePHP Desktop (Electron) app.
     * Detection priority:
     *  1. NATIVEPHP_PLATFORM env = win32 | windows | mac | macos | darwin
     *  2. NATIVEPHP_RUNNING constant/env + Windows OS family
     *  3. Electron User-Agent in HTTP request
     */
    public static function isNativeDesktop(): bool
    {
        static $result = null;
        if ($result !== null) {
            return $result;
        }

        // Guard: never treat as desktop during CI or unit tests
        if (env('GITHUB_ACTIONS') || app()->runningUnitTests() || env('APP_ENV') === 'testing') {
            return $result = false;
        }

        // Mobile takes precedence — if it's mobile it cannot be desktop
        if (self::isNativeMobile()) {
            return $result = false;
        }

        // 1. Explicit NATIVEPHP_PLATFORM flag (most reliable — set by NativePHP Electron bootstrapper)
        $platform = strtolower((string) (
            env('NATIVEPHP_PLATFORM')
            ?: ($_SERVER['NATIVEPHP_PLATFORM'] ?? '')
            ?: config('nativephp-internal.platform', '')
        ));
        if (in_array($platform, ['win32', 'windows', 'mac', 'macos', 'darwin'], true)) {
            return $result = true;
        }

        // 2. NATIVEPHP_RUNNING constant/env + Windows OS (Electron runs on Windows/macOS)
        $nativeRunning = (defined('NATIVEPHP_RUNNING') && constant('NATIVEPHP_RUNNING'))
            || filter_var(env('NATIVEPHP_RUNNING'), FILTER_VALIDATE_BOOL);

        if ($nativeRunning && in_array(PHP_OS_FAMILY, ['Windows', 'Darwin'], true)) {
            return $result = true;
        }

        // 3. Electron User-Agent (NativePHP Desktop injects "Electron/" into the UA)
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ($userAgent && preg_match('/NativePHP(?!.*Mobile)|Electron\//i', $userAgent)) {
            // Exclude mobile UA patterns
            if (! preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $userAgent)) {
                return $result = true;
            }
        }

        return $result = false;
    }

    /**
     * Returns true if the request is from a mobile device (Native App OR Mobile Browser).
     * Delegates to PlatformContext for full cross-platform detection.
     * Falls back to UA regex when called from console (no request context).
     */
    public static function isAnyMobile(): bool
    {
        // In console context there is no HTTP request, use lightweight UA check
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return self::isNativeMobile()
                || (bool) preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $_SERVER['HTTP_USER_AGENT'] ?? '');
        }

        return PlatformContext::isAnyMobile();
    }

    /**
     * Returns true ONLY when running inside a real NativePHP mobile app
     * (Android or iOS), even without NATIVEPHP_RUNNING being set.
     *
     * Detection priority:
     *  1. NATIVEPHP_RUNNING constant (set by NativePHP bootstrapper)
     *  2. NATIVEPHP_RUNNING env var (fallback)
     *  3. No REMOTE_ADDR + non-Windows OS (CLI / embedded PHP server on device)
     */
    public static function isNativeMobile(): bool
    {
        static $result = null;
        if ($result !== null) {
            return $result;
        }

        // 0. Guard: Never treat as mobile during CI, Unit Testing, or WSL
        if (env('GITHUB_ACTIONS') || app()->runningUnitTests() || env('APP_ENV') === 'testing') {
            return $result = false;
        }
        if (PHP_OS_FAMILY === 'Linux' && is_readable('/proc/version')) {
            try {
                if (str_contains((string) file_get_contents('/proc/version'), 'microsoft')) {
                    return $result = false;
                }
            } catch (\Throwable) {
                // Cannot read /proc/version — treat WSL check as inconclusive, continue
            }
        }

        // 0b. Guard: If NATIVEPHP_PLATFORM is explicitly a desktop platform, it's NOT mobile
        $platform = strtolower((string) (
            env('NATIVEPHP_PLATFORM')
            ?: ($_SERVER['NATIVEPHP_PLATFORM'] ?? '')
            ?: config('nativephp-internal.platform', '')
        ));
        if (in_array($platform, ['win32', 'windows', 'mac', 'macos', 'darwin'], true)) {
            return $result = false;
        }

        // 1. Explicit NativePHP constant (most reliable — set by NativePHP bootstrapper)
        if (defined('NATIVEPHP_RUNNING') && constant('NATIVEPHP_RUNNING')) {
            // On Windows with NATIVEPHP_RUNNING → it's Desktop, not Mobile
            if (PHP_OS_FAMILY === 'Windows') {
                return $result = false;
            }

            return $result = true;
        }

        // 2. Explicit env flag
        if (env('IS_NATIVE_MOBILE')) {
            return $result = true;
        }
        if (env('NATIVEPHP_RUNNING')) {
            // On Windows → Desktop app
            if (PHP_OS_FAMILY === 'Windows') {
                return $result = false;
            }

            return $result = true;
        }

        // 3. NativePHP sets database.default = 'nativephp' (SQLite) on device
        //    Check this BEFORE any DB operations to avoid circular dependency
        $dbDefault = env('DB_CONNECTION', config('database.default', 'sqlite'));
        if ($dbDefault === 'nativephp' || $dbDefault === 'sqlite') {
            // Only treat as mobile if also running on Linux/Darwin (device OS)
            if (PHP_OS_FAMILY === 'Linux' || PHP_OS_FAMILY === 'Darwin') {
                // Ensure we are not on a desktop Linux/Darwin (like CI or Dev Mac)
                // Device detection usually has no REMOTE_ADDR and no SHELL_VERBOSITY
                if (! isset($_SERVER['REMOTE_ADDR']) && ! env('SHELL_VERBOSITY')) {
                    return $result = true;
                }
            }
        }

        // 3b. NATIVE_HOST_IP is set → explicitly configured for mobile (dev sets this)
        //     AND we are NOT on Windows (mobile devices run Linux/Darwin)
        //     AND we are NOT on WSL (which also reports Linux but is a dev machine)
        if (env('NATIVE_HOST_IP') && PHP_OS_FAMILY !== 'Windows') {
            $isWsl = false;
            if (PHP_OS_FAMILY === 'Linux' && is_readable('/proc/version')) {
                try {
                    $isWsl = str_contains((string) file_get_contents('/proc/version'), 'microsoft');
                } catch (\Throwable) {
                    // Cannot read /proc/version — treat WSL check as inconclusive
                }
            }
            if (! $isWsl) {
                return $result = true;
            }
        }

        // 4. Android / iOS WebView & Local embedded environment checks
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? null;
        $isCI = env('GITHUB_ACTIONS') || app()->runningUnitTests();
        $isCloud = env('LARAVEL_CLOUD') || env('DOCKER_ENV') || env('APP_ENV') === 'production';

        if (! empty($userAgent)) {
            // Electron UA → Desktop, not mobile
            if (preg_match('/NativePHP(?!.*Mobile)|Electron\//i', $userAgent)
                && ! preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $userAgent)) {
                return $result = false;
            }

            // Android WebView (sends "wv)" in UA)
            if (preg_match('/Android.*wv\)/i', $userAgent)) {
                return $result = true;
            }

            // iOS WKWebView: Only treat as Native App if:
            //  - Running on Linux or Darwin host (never Windows)
            //  - Not on standard Cloud hosting / CI environments
            //  - Accessing locally (empty Remote Address OR localhost loopback)
            if (preg_match('/iPhone|iPad.*Mobile.*Safari/i', $userAgent)
                && ! str_contains($userAgent, 'CriOS')
                && ! str_contains($userAgent, 'FxiOS')) {
                if (PHP_OS_FAMILY !== 'Windows' && ! $isCloud && ! $isCI) {
                    if (! isset($remoteAddr) || in_array($remoteAddr, ['127.0.0.1', '::1', '0:0:0:0:0:0:0:1'])) {
                        return $result = true;
                    }
                }
            }
        }

        // 5. Heuristic: non-Windows OS with no HTTP client or loopback client
        if (PHP_OS_FAMILY !== 'Windows' && ! $isCloud && ! $isCI) {
            // No REMOTE_ADDR → definitely embedded PHP server on device (no incoming network)
            if (! isset($remoteAddr)) {
                return $result = true;
            }
            // REMOTE_ADDR is loopback → local WebView calling embedded PHP server
            if (in_array($remoteAddr, ['127.0.0.1', '::1', '0:0:0:0:0:0:0:1'])) {
                return $result = true;
            }
        }

        return $result = false;
    }

    /**
     * Returns the correct "localhost" equivalent for the current environment.
     * Only meaningful for mobile — desktop always uses 127.0.0.1.
     */
    public static function mobileHostIp(): string
    {
        static $ip = null;
        if ($ip !== null) {
            return $ip;
        }

        // Allow explicit override via environment variable
        if ($override = env('NATIVE_HOST_IP')) {
            return $ip = $override;
        }

        // Ekstrak dari APP_URL jika diset ke IP LAN atau ngrok (Penting untuk HP FISIK)
        $appUrl = env('APP_URL');
        if ($appUrl && str_starts_with($appUrl, 'http')) {
            $parsedHost = parse_url($appUrl, PHP_URL_HOST);
            if ($parsedHost && ! in_array($parsedHost, ['127.0.0.1', 'localhost'])) {
                return $ip = $parsedHost;
            }
        }

        // Android emulator special loopback
        if (PHP_OS_FAMILY === 'Linux') {
            return $ip = '10.0.2.2';
        }

        // iOS simulator / macOS host / Desktop
        return $ip = '127.0.0.1';
    }

    /**
     * Normalize a URL so it works on the current platform.
     * - Mobile: replaces 127.0.0.1/localhost with the correct host IP.
     * - Desktop: rewrites to match the Electron embedded server root.
     * - Web: returns the URL unchanged (or rewritten to match request host).
     */
    public static function normalizeUrl(string $url): string
    {
        if (self::isNativeMobile()) {
            $hostIp = self::mobileHostIp();

            return str_replace(
                ['http://127.0.0.1', 'http://localhost', 'https://127.0.0.1', 'https://localhost'],
                ["http://{$hostIp}", "http://{$hostIp}", "https://{$hostIp}", "https://{$hostIp}"],
                $url
            );
        }

        // Desktop (Electron) and Web: rewrite to match the actual request root
        if (app()->runningInConsole() || ! request()) {
            return $url;
        }

        $requestRoot = request()->getSchemeAndHttpHost();
        $urlHost = parse_url($url, PHP_URL_HOST);
        $urlScheme = parse_url($url, PHP_URL_SCHEME);
        $urlPort = parse_url($url, PHP_URL_PORT);
        $appHost = parse_url((string) env('APP_URL'), PHP_URL_HOST);

        if (! $urlHost || ! $urlScheme) {
            return $url;
        }

        $localHosts = array_filter([
            '127.0.0.1',
            'localhost',
            $appHost,
            request()->getHost(),
        ]);

        if (! in_array($urlHost, $localHosts, true)) {
            return $url;
        }

        $sourceRoot = $urlScheme.'://'.$urlHost.($urlPort ? ':'.$urlPort : '');

        return preg_replace('#^'.preg_quote($sourceRoot, '#').'#', $requestRoot, $url) ?: $url;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // REGISTER
    // ═══════════════════════════════════════════════════════════════════════

    public function register(): void
    {
        // Guard: skip all NativePHP-specific code on Docker/backend/Cloud envs
        if (env('DOCKER_ENV') || env('LARAVEL_CLOUD')) {
            return;
        }

        $isMobile = self::isNativeMobile();
        $isDesktop = self::isNativeDesktop();

        // ── MOBILE: Register native singletons + switch DB to proxy ──────
        if ($isMobile) {
            if (class_exists(Network::class)) {
                $this->app->singleton(Network::class, fn () => new Network);
            }
            if (class_exists(System::class)) {
                $this->app->singleton(System::class, fn () => new System);
            }

            // CRITICAL: Switch DB to proxy HERE (register phase) so that session,
            // cache, and any middleware that access the DB use the proxy,
            // not 127.0.0.1:3306 which does not exist on the mobile device.
            $proxyUrl = env(
                'NATIVE_DB_PROXY_URL',
                rtrim(env('APP_URL', 'http://192.168.100.63:8000'), '/').'/api/db-proxy'
            );
            $proxySecret = env('NATIVE_DB_PROXY_SECRET', 'nativephp-db-proxy-secret-2024');

            config([
                'database.default' => 'mysql_proxy',
                'database.connections.mysql_proxy.proxy_url' => $proxyUrl,
                'database.connections.mysql_proxy.proxy_secret' => $proxySecret,
                'database.connections.mysql_proxy.database' => env('DB_DATABASE', 'wedding_flowers_decorasi'),
                // Switch session/cache to file to avoid DB chicken-egg on boot
                'session.driver' => 'file',
                'cache.default' => 'file',
            ]);

            error_log('[NativePHP Mobile] register() → DB switched to mysql_proxy. URL: '.$proxyUrl);
        }

        // ── DESKTOP: Switch session/cache to file (SQLite not available on Electron) ──
        if ($isDesktop) {
            config([
                'session.driver' => env('SESSION_DRIVER', 'file'),
                'cache.default' => env('CACHE_STORE', 'file'),
            ]);

            error_log('[NativePHP Desktop] register() → session/cache switched to file driver.');
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // BOOT
    // ═══════════════════════════════════════════════════════════════════════

    public function boot(): void
    {
        // Guard: skip on Docker / pure backend / Cloud
        if (env('DOCKER_ENV') || env('LARAVEL_CLOUD')) {
            return;
        }

        $isMobile = self::isNativeMobile();
        $isDesktop = self::isNativeDesktop();

        // ── 1. RESOLVE HOST IPs ───────────────────────────────────────────
        $hostIp = self::mobileHostIp();
        $serverPort = env('NATIVE_SERVER_PORT', 8000);

        $dbHost = env('DB_HOST', '127.0.0.1');
        $reverbHost = env('REVERB_HOST', 'localhost');
        $appUrl = env('APP_URL', 'http://127.0.0.1');
        $currentHost = parse_url($appUrl, PHP_URL_HOST) ?? '127.0.0.1';

        // ── 2. DYNAMIC HOST DETECTION ─────────────────────────────────────
        // For web and desktop: always follow the actual HTTP_HOST so assets
        // load correctly regardless of how the server is accessed.
        // For mobile: keep the configured host IP (don't override with 127.0.0.1).
        if (! app()->runningInConsole() && isset($_SERVER['HTTP_HOST'])) {
            $currentHost = $_SERVER['HTTP_HOST'];

            $proto = $_SERVER['HTTP_X_FORWARDED_PROTO']
                ?? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
            $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'];

            if (! $isMobile || ! in_array(parse_url('http://'.$host, PHP_URL_HOST), ['127.0.0.1', 'localhost'])) {
                $appUrl = "{$proto}://{$host}";
                $hostIp = parse_url($appUrl, PHP_URL_HOST);
                $currentHost = $host;
            }
        }

        // ── 3. PLATFORM-SPECIFIC HOST RESOLUTION ─────────────────────────
        if ($isMobile) {
            $replace = ['127.0.0.1', 'localhost'];

            if (in_array($dbHost, $replace)) {
                $dbHost = $hostIp;
            }
            if (in_array($reverbHost, $replace)) {
                $reverbHost = $hostIp;
            }

            $parsedUrl = parse_url($appUrl);
            $scheme = $parsedUrl['scheme'] ?? 'http';
            $port = $parsedUrl['port'] ?? $serverPort;
            $portSuffix = ($port == 80 || $port == 443) ? '' : ":$port";
            $hostServerUrl = "{$scheme}://{$hostIp}{$portSuffix}";
        } elseif ($isDesktop) {
            // Desktop (Electron): PHP server runs on 127.0.0.1 inside the Electron process.
            // APP_URL is typically http://localhost or http://127.0.0.1 — keep it as-is.
            $hostServerUrl = $appUrl;
        } else {
            $hostServerUrl = $appUrl;
        }

        // ── 4. APPLY RUNTIME CONFIG ───────────────────────────────────────
        $runtimeConfig = [
            'app.url' => $appUrl,

            'sanctum.stateful' => array_unique(array_merge(
                explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost,127.0.0.1')),
                [$currentHost]
            )),

            // Session driver
            'session.driver' => $isMobile
                ? 'file'
                : ($isDesktop ? env('SESSION_DRIVER', 'file') : env('SESSION_DRIVER', 'database')),

            // Database connection
            'database.connections.mysql.host' => $dbHost,
            'database.connections.mysql.port' => env('DB_PORT', '3306'),
            'database.connections.mysql.database' => env('DB_DATABASE', config('database.connections.mysql.database', 'wedding_flowers_decorasi')),
            'database.connections.mysql.username' => env('DB_USERNAME', 'root'),
            'database.connections.mysql.password' => env('DB_PASSWORD', ''),

            // Reverb / Broadcasting
            'reverb.apps.0.host' => $reverbHost,
            'broadcasting.connections.reverb.options.host' => $reverbHost,
            'broadcasting.connections.pusher.options.host' => $reverbHost,

            // AI / CBIR Service
            'services.ai_core_url' => $isMobile
                ? str_replace(['127.0.0.1', 'localhost'], $hostIp, env('AI_CORE_URL', 'http://127.0.0.1:5000'))
                : env('AI_CORE_URL', 'http://127.0.0.1:5000'),
            'services.cbir_api_url' => $isMobile
                ? str_replace(['127.0.0.1', 'localhost'], $hostIp, env('CBIR_API_URL', 'http://127.0.0.1:5000'))
                : env('CBIR_API_URL', 'http://127.0.0.1:5000'),
        ];

        // ── 4a. MOBILE-SPECIFIC CONFIG ────────────────────────────────────
        $proxyUrl = "{$hostServerUrl}/api/db-proxy";

        if ($isMobile) {
            $runtimeConfig['database.default'] = 'mysql_proxy';
            $runtimeConfig['database.connections.mysql_proxy.proxy_url'] = $proxyUrl;
            $runtimeConfig['database.connections.mysql_proxy.proxy_secret'] = env('NATIVE_DB_PROXY_SECRET', 'nativephp-db-proxy-secret-2024');
            $runtimeConfig['database.connections.mysql_proxy.database'] = env('DB_DATABASE', config('database.connections.mysql.database', 'wedding_flowers_decorasi'));

            // Google OAuth: redirect to deep-link scheme on Android/iOS
            if (env('GOOGLE_MOBILE_REDIRECT_URL')) {
                $runtimeConfig['services.google.redirect'] = env('GOOGLE_MOBILE_REDIRECT_URL');
            }

            // Force asset() and route() to use the host PC URL, not NativePHP's localhost
            URL::forceRootUrl($hostServerUrl);
        }

        // ── 4b. DESKTOP-SPECIFIC CONFIG ───────────────────────────────────
        if ($isDesktop) {
            // Electron embeds PHP at 127.0.0.1 — ensure DB connects to the same machine
            // (no proxy needed; pdo_mysql is available in the Electron PHP build)
            $runtimeConfig['database.default'] = env('DB_CONNECTION', 'mysql');

            // Google OAuth: use standard web redirect for desktop
            if (env('GOOGLE_DESKTOP_REDIRECT_URL')) {
                $runtimeConfig['services.google.redirect'] = env('GOOGLE_DESKTOP_REDIRECT_URL');
            }
        }

        // ── 4c. PUBLIC DISK URL (absolute for both web & native) ─────────
        // Prevents Spatie Media Library from returning '/storage/...' which
        // causes Blade to double-prefix 'storage//storage/'.
        $runtimeConfig['filesystems.disks.public.url'] = ($isMobile ? $hostServerUrl : $appUrl).'/storage';

        config($runtimeConfig);

        // ── 5. DEBUG LOG ──────────────────────────────────────────────────
        if ($isMobile || $isDesktop) {
            $platform = $isMobile ? 'Mobile' : 'Desktop';
            error_log(sprintf(
                '[NativePHP %s] OS: %s | Host IP: %s | DB: %s | App URL: %s',
                $platform,
                PHP_OS_FAMILY,
                $hostIp,
                config('database.default'),
                $appUrl
            ));
        }

        // ── 6. ON-DEMAND INITIALIZATION (Mobile only) ────────────────────
        // Bypassed when using mysql_proxy — the PC already has migrations/seeders.
        $flagFile = storage_path('framework/mobile_init.flag');

        if ($isMobile
            && config('database.default') !== 'mysql_proxy'
            && ! file_exists($flagFile)
            && ! app()->runningInConsole()
        ) {
            try {
                // Fast ping: abort early if host PC is unreachable (prevents white screen)
                try {
                    Http::timeout(2)->post($proxyUrl, ['method' => 'select', 'query' => 'SELECT 1', 'bindings' => []]);
                } catch (\Throwable) {
                    error_log('[NativePHP Mobile] Host PC unreachable. Skipping DB init.');

                    return;
                }

                $hasUsers = false;
                try {
                    $hasUsers = User::exists();
                } catch (\Throwable) {
                    $hasUsers = false;
                }

                if (! $hasUsers) {
                    error_log('[NativePHP Mobile] Database empty. Initializing...');
                    Artisan::call('migrate', ['--force' => true]);

                    $seeders = [
                        'RolesAndPermissionsSeeder',
                        'SuperAdminSeeder',
                        'ProductSeeder',
                        'PackageSeeder',
                        'TermsAndConditionsSeeder',
                        'VoucherSeeder',
                    ];

                    foreach ($seeders as $seeder) {
                        try {
                            Artisan::call('db:seed', [
                                '--class' => "Database\\Seeders\\{$seeder}",
                                '--force' => true,
                            ]);
                            error_log("[NativePHP Mobile] Seeder done: {$seeder}");
                        } catch (\Throwable $e) {
                            error_log("[NativePHP Mobile] Seeder failed ({$seeder}): ".$e->getMessage());
                        }
                    }

                    error_log('[NativePHP Mobile] Initialization done.');
                }

                file_put_contents($flagFile, date('Y-m-d H:i:s'));
            } catch (\Throwable $e) {
                error_log('[NativePHP Mobile] Init failed: '.$e->getMessage());
            }
        }

        // ── 7. DESKTOP INITIALIZATION (Electron only) ────────────────────
        // Run migrations on first boot so the embedded SQLite/MySQL is ready.
        $desktopFlagFile = storage_path('framework/desktop_init.flag');

        if ($isDesktop && ! file_exists($desktopFlagFile) && ! app()->runningInConsole()) {
            try {
                Artisan::call('migrate', ['--force' => true]);
                error_log('[NativePHP Desktop] Migrations applied.');
                file_put_contents($desktopFlagFile, date('Y-m-d H:i:s'));
            } catch (\Throwable $e) {
                error_log('[NativePHP Desktop] Migration failed: '.$e->getMessage());
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // NATIVEPHP MOBILE PLUGINS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * The NativePHP Mobile plugins to enable.
     * Only plugins listed here will be compiled into Android/iOS builds.
     *
     * @return array<int, class-string<ServiceProvider>>
     */
    public function plugins(): array
    {
        return [
            ScreenServiceProvider::class,
            SystemServiceProvider::class,
            DeviceServiceProvider::class,
            NetworkServiceProvider::class,
            CameraServiceProvider::class,
            FileServiceProvider::class,
        ];
    }
}
