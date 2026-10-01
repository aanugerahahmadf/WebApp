<?php

namespace App\Support\AppPlatform;

use App\Enums\RuntimePlatform\RuntimePlatform;
use Illuminate\Http\Request;

/**
 * Single source of truth for "which runtime am I in?".
 *
 * The website and the two Capacitor shells (app/Capacitor/AdminApp and
 * app/Capacitor/UserApp) all render the *same* Filament panel over HTTP, so the
 * server can only tell them apart from request-level signals:
 *
 *   1. `CAPACITOR_PLATFORM` env / config   — explicit, set in a native build
 *   2. `X-Capacitor` / `X-Shell` header    — injected by the native HTTP layer
 *   3. `app_shell` cookie                  — written by the in-page Capacitor probe
 *   4. `X-Requested-With`-style UA sniffing (`Electron/`, `wv)`, iPhone, …)
 *   5. `app_display_mode=standalone` cookie — installed PWA on a desktop OS
 *   6. fallback                             — desktop website
 *
 * Detection is memoised per request so a single page render can never see two
 * different answers. Call {@see static::reset()} to clear it (tests, Octane).
 */
class AppPlatform
{
    /** Value written to the `app_shell` cookie by the in-page Capacitor probe. */
    public const SHELL_COOKIE = 'app_shell';

    public const HEADER_SHELL = 'X-Shell';

    public const HEADER_CAPACITOR = 'X-Capacitor';

    /**
     * Resolve the platform for the current request.
     *
     * The result is memoised in a *scoped* container binding (registered in
     * AppServiceProvider::register) rather than in a static, so a long-lived
     * worker cannot keep serving the first request's platform to every request
     * that follows. Pass an explicit request (or bind RuntimePlatform::class)
     * to control it in tests.
     */
    public static function current(?Request $request = null): RuntimePlatform
    {
        if ($request !== null) {
            return static::detect($request);
        }

        if (app()->bound(RuntimePlatform::class)) {
            return app(RuntimePlatform::class);
        }

        return static::detect();
    }

    /**
     * Pin the platform for the rest of the request/test.
     *
     * The seam for asserting the mobile layout without a real phone: everything
     * reads through {@see static::current()}, so one call flips the whole app
     * into a given surface.
     *
     *     AppPlatform::fake(RuntimePlatform::MobileAppAndroid);
     *     $this->get('/admin/orders')->assertSee('fi-bottom-nav');
     */
    public static function fake(?RuntimePlatform $platform = null): void
    {
        app()->instance(RuntimePlatform::class, $platform ?? RuntimePlatform::WebsiteWindows);
    }

    /**
     * Drop the resolved platform so the next {@see static::current()} re-sniffs
     * the request.
     *
     * The memo lives in a scoped container binding rather than a static, so
     * this only needs to forget the instance for the current lifecycle — which
     * is what a test walking several platforms in one process needs.
     */
    public static function reset(): void
    {
        app()->forgetInstance(RuntimePlatform::class);
    }

    /**
     * True when the request comes from a phone-sized surface: the native mobile
     * shell or a mobile browser. This is the flag panels should branch on for
     * layout decisions.
     */
    public static function isMobile(?Request $request = null): bool
    {
        return static::current($request)->isMobileShell();
    }

    /**
     * True for a phone-sized surface: the native mobile shell or a mobile
     * browser.
     *
     * The same predicate as isMobile(); the name the older call sites (and
     * App\Support\PlatformContext) use. Kept here so both spellings resolve
     * against this class.
     */
    public static function isAnyMobile(?Request $request = null): bool
    {
        return static::isMobile($request);
    }

    /**
     * True only when the request comes from a Capacitor/Electron app shell.
     */
    public static function isNativeApp(?Request $request = null): bool
    {
        $platform = static::current($request);

        return $platform->isMobileApp() || $platform->isDesktopApp();
    }

    public static function isMobileApp(?Request $request = null): bool
    {
        return static::current($request)->isMobileApp();
    }

    /**
     * True only for the Capacitor mobile shell (Android or iOS).
     *
     * The same predicate as isMobileApp(); the name the older call sites (and
     * App\Support\PlatformContext) use. Kept here so both spellings resolve
     * against this class.
     */
    public static function isNativeMobile(?Request $request = null): bool
    {
        return static::isMobileApp($request);
    }

    public static function isDesktopApp(?Request $request = null): bool
    {
        return static::current($request)->isDesktopApp();
    }

    public static function isWebsite(?Request $request = null): bool
    {
        return static::current($request)->isWebsite();
    }

    public static function cbirCameraMode(?Request $request = null): string
    {
        return static::current($request)->cbirCameraMode();
    }

    /**
     * Sniff the platform from request-level signals.
     *
     * Public so a caller can bypass the scoped memo and resolve a platform for
     * a specific request; prefer {@see static::current()} in application code.
     */
    public static function detect(?Request $request = null): RuntimePlatform
    {
        $forced = static::forcedPlatform();

        if ($forced instanceof RuntimePlatform) {
            return $forced;
        }

        // Console (artisan, queue workers, tinker) has no request worth
        // sniffing: the bound `request` instance there is an empty stub whose
        // User-Agent would report the *server's* OS to every panel render.
        $request ??= app()->runningInConsole() ? null : request();

        if (! $request instanceof Request) {
            return RuntimePlatform::WebsiteWindows;
        }

        $userAgent = (string) $request->userAgent();

        // Only the User-Agent may decide this. The server's own OS says nothing
        // about the client, and mixing it in would report "website_windows" to
        // every unknown desktop UA whenever Laravel happens to run on Windows.
        $isMac = str_contains($userAgent, 'Macintosh') || str_contains($userAgent, 'Mac OS X');

        // ── 2. Explicit native headers ───────────────────────────────────────
        $shell = strtolower((string) ($request->header(static::HEADER_SHELL)
            ?? $request->header(static::HEADER_CAPACITOR, '')));

        if ($shell !== '') {
            if ($shell === 'android' || $shell === '1') {
                return RuntimePlatform::MobileAppAndroid;
            }

            if ($shell === 'ios') {
                return RuntimePlatform::MobileAppIos;
            }

            if (str_contains($shell, 'electron') || str_contains($shell, 'desktop')) {
                return $isMac
                    ? RuntimePlatform::DesktopAppMacOS
                    : RuntimePlatform::DesktopAppWindows;
            }
        }

        // ── 3. Cookie written by the in-page Capacitor probe ──────────────────
        $shellCookie = strtolower((string) $request->cookie(static::SHELL_COOKIE, ''));

        if ($shellCookie !== '') {
            if (str_contains($shellCookie, 'android')) {
                return RuntimePlatform::MobileAppAndroid;
            }

            if (str_contains($shellCookie, 'ios')) {
                return RuntimePlatform::MobileAppIos;
            }

            if (str_contains($shellCookie, 'electron') || str_contains($shellCookie, 'desktop')) {
                return $isMac
                    ? RuntimePlatform::DesktopAppMacOS
                    : RuntimePlatform::DesktopAppWindows;
            }
        }

        // ── 4. User-agent sniffing ───────────────────────────────────────────
        if (str_contains($userAgent, 'Electron/')) {
            return str_contains($userAgent, 'Windows NT')
                ? RuntimePlatform::DesktopAppWindows
                : RuntimePlatform::DesktopAppMacOS;
        }

        // iPadOS in desktop mode reports a Macintosh UA; the touch-point count is
        // the standard tell, but it is not visible server-side, so treat a plain
        // Macintosh UA as macOS unless the shell cookie already said iOS.
        if (preg_match('/iPhone|iPad|iPod/i', $userAgent) === 1) {
            return RuntimePlatform::WebsiteIos;
        }

        // Capacitor's Android WebView carries a `; wv` token in the platform
        // section, which a real Chrome install never sends. Combined with the
        // shell cookie above this tells "the app on Android" from "Chrome on
        // Android" on the very first paint, before the cookie exists.
        if (preg_match('/Android/i', $userAgent) === 1) {
            return preg_match('/(?:;|\s)wv(?:\)|;|\s|$)/i', $userAgent) === 1
                ? RuntimePlatform::MobileAppAndroid
                : RuntimePlatform::WebsiteAndroid;
        }

        // ── 5. Installed desktop PWA ──────────────────────────────────────────
        // Checked *after* the mobile branches on purpose: a PWA installed on a
        // phone also reports `display-mode: standalone`, and that must stay a
        // mobile platform, not become a desktop app.
        if ($request->cookie('app_display_mode', '') === 'standalone') {
            return $isMac ? RuntimePlatform::DesktopAppMacOS : RuntimePlatform::DesktopAppWindows;
        }

        if ($isMac) {
            return RuntimePlatform::WebsiteMacOS;
        }

        // ── 6. Fallback ──────────────────────────────────────────────────────
        // An unrecognised User-Agent (a bot, curl, a crawler) is a desktop
        // website, and nothing in the request says which desktop OS. Pick one
        // fixed default rather than leaking the *server's* OS into the client's
        // platform.
        return RuntimePlatform::WebsiteWindows;
    }

    /**
     * The "localhost" equivalent for the machine running this code.
     *
     * A Capacitor shell loads the Laravel server over real HTTP, so there is no
     * embedded PHP and no device-local database any more. The host is only
     * needed for diagnostics (the /api/ping payload) and for building absolute
     * URLs, so this resolves in priority order:
     *
     *   1. `CAPACITOR_HOST_IP` — explicit override
     *   2. the host in `APP_URL` when it is a routable LAN/domain address
     *   3. the live request host
     *   4. `10.0.2.2`, the Android emulator's alias for the host machine
     *   5. `127.0.0.1`
     */
    public static function mobileHostIp(): string
    {
        if ($override = config('app-platform.host_ip') ?: env('CAPACITOR_HOST_IP')) {
            return $override;
        }

        $appHost = parse_url((string) env('APP_URL'), PHP_URL_HOST);

        if ($appHost && ! in_array($appHost, ['127.0.0.1', 'localhost'], true)) {
            return $appHost;
        }

        if (! app()->runningInConsole() && request()->getHost()) {
            return request()->getHost();
        }

        return PHP_OS_FAMILY === 'Linux' ? '10.0.2.2' : '127.0.0.1';
    }

    /**
     * Rewrite a local URL so it points at the origin that is serving *this*
     * request.
     *
     * `asset()` and `url()` bake in `APP_URL`, which is usually
     * `http://localhost` during development. A Capacitor shell reaches the
     * server through a different origin (the emulator's `10.0.2.2`, a LAN IP,
     * or the production domain), and a WebView will refuse a cross-origin
     * navigation or a mixed-content image. Rewriting the scheme+host+port
     * prefix onto the current request root keeps every absolute URL inside the
     * shell's own origin.
     */
    public static function normalizeUrl(string $url): string
    {
        if ($url === '' || app()->runningInConsole() || ! request()) {
            return $url;
        }

        $parts = parse_url($url);

        if (! isset($parts['host'], $parts['scheme'])) {
            return $url;
        }

        $localHosts = array_values(array_unique(array_filter([
            '127.0.0.1',
            'localhost',
            parse_url((string) env('APP_URL'), PHP_URL_HOST),
            request()->getHost(),
        ])));

        if (! in_array($parts['host'], $localHosts, true)) {
            return $url;
        }

        $sourceRoot = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        $targetRoot = request()->getSchemeAndHttpHost();

        if ($sourceRoot === $targetRoot) {
            return $url;
        }

        return preg_replace('#^'.preg_quote($sourceRoot, '#').'#', $targetRoot, $url) ?: $url;
    }

    /**
     * `CAPACITOR_PLATFORM` wins over every request signal. Set it in the native
     * build's environment when the UA/cookie heuristic is not good enough.
     */
    protected static function forcedPlatform(): ?RuntimePlatform
    {
        $value = config('app-platform.force_platform') ?: env('CAPACITOR_PLATFORM');

        if (! is_string($value) || $value === '') {
            return null;
        }

        return match (strtolower($value)) {
            'android', 'mobile_app_android' => RuntimePlatform::MobileAppAndroid,
            'ios', 'mobile_app_ios' => RuntimePlatform::MobileAppIos,
            'electron', 'desktop', 'win32', 'windows', 'desktop_app_windows' => RuntimePlatform::DesktopAppWindows,
            'mac', 'macos', 'darwin', 'desktop_app_macos' => RuntimePlatform::DesktopAppMacOS,
            'website_android' => RuntimePlatform::WebsiteAndroid,
            'website_ios' => RuntimePlatform::WebsiteIos,
            'website_macos' => RuntimePlatform::WebsiteMacOS,
            'web', 'website', 'website_windows' => RuntimePlatform::WebsiteWindows,
            default => null,
        };
    }
}
