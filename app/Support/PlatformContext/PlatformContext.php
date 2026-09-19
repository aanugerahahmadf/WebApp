<?php

namespace App\Support\PlatformContext;

use App\Enums\RuntimePlatform\RuntimePlatform;
use App\Providers\NativeServiceProvider\NativeServiceProvider;
use Illuminate\Http\Request;

class PlatformContext
{
    private static ?RuntimePlatform $cached = null;

    // ═══════════════════════════════════════════════════════════════════════
    // PUBLIC API
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Resolve and cache the current RuntimePlatform for this request lifecycle.
     * Detection priority (stops at first definitive signal):
     *  1. NATIVEPHP_PLATFORM env/config flag (exact case-sensitive match)
     *  2. NATIVEPHP_RUNNING constant or env var
     *  3. User-Agent string
     *  4. app_display_mode cookie (PWA standalone)
     *  5. OS family heuristic (console context only)
     */
    public static function current(?Request $request = null): RuntimePlatform
    {
        if (self::$cached !== null) {
            return self::$cached;
        }

        // Console context: no HTTP signals available
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return self::$cached = self::resolveFromEnvOnly();
        }

        $request ??= request();
        $userAgent = $request?->userAgent() ?? '';
        $nativePlatform = self::nativePlatformFlag(); // exact case-sensitive value

        // ── Priority 1: Explicit NATIVEPHP_PLATFORM flag ──────────────────
        if ($nativePlatform !== null) {
            return self::$cached = self::resolveFromPlatformFlag($nativePlatform, $userAgent);
        }

        // ── Priority 2: NativePHP Mobile (Android / iOS) ──────────────────
        if (NativeServiceProvider::isNativeMobile()) {
            if (self::uaIsAndroid($userAgent)) {
                return self::$cached = RuntimePlatform::MobileAppAndroid;
            }

            return self::$cached = RuntimePlatform::MobileAppIos;
        }

        // ── Priority 3: NativePHP Desktop (Electron) ──────────────────────
        if (self::isNativeDesktopShell($request, $userAgent)) {
            return self::$cached = self::uaIsWindows($userAgent)
                ? RuntimePlatform::DesktopAppWindows
                : RuntimePlatform::DesktopAppMacOS;
        }

        // ── Priority 4: Website on mobile browser ─────────────────────────
        // iOS must be checked before macOS because iPadOS desktop-mode UA
        // contains "Macintosh" but is still an iOS device.
        if (self::uaIsIos($userAgent)) {
            return self::$cached = RuntimePlatform::WebsiteIos;
        }

        if (self::uaIsAndroid($userAgent)) {
            return self::$cached = RuntimePlatform::WebsiteAndroid;
        }

        // ── Priority 5: Website on desktop browser ────────────────────────
        if (self::uaIsMac($userAgent)) {
            return self::$cached = RuntimePlatform::WebsiteMacOS;
        }

        // Default fallback: Windows website
        return self::$cached = RuntimePlatform::WebsiteWindows;
    }

    /**
     * Reset the static cache so the next call to current() re-runs detection.
     * Use this in tests to isolate platform state between test cases.
     */
    public static function reset(): void
    {
        self::$cached = null;
    }

    public static function isAnyMobile(?Request $request = null): bool
    {
        return self::current($request)->isMobileShell();
    }

    public static function isNativeMobile(?Request $request = null): bool
    {
        return self::current($request)->isMobileApp();
    }

    public static function isNativeDesktop(?Request $request = null): bool
    {
        return self::current($request)->isDesktopApp();
    }

    /**
     * @return 'native'|'mobile_browser_capture'|'webrtc'
     */
    public static function cbirCameraMode(?Request $request = null): string
    {
        return self::current($request)->cbirCameraMode();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Read NATIVEPHP_PLATFORM from env / $_SERVER / config.
     * Returns the raw string value (NOT lowercased) for exact case-sensitive matching.
     * Returns null if not set or empty.
     */
    private static function nativePlatformFlag(): ?string
    {
        $platform = env('NATIVEPHP_PLATFORM')
            ?: ($_SERVER['NATIVEPHP_PLATFORM'] ?? null)
            ?: config('nativephp-internal.platform');

        return is_string($platform) && $platform !== '' ? $platform : null;
    }

    /**
     * Resolve platform from the NATIVEPHP_PLATFORM flag using exact case-sensitive comparison.
     * Unrecognised values fall through to UA-based detection.
     */
    private static function resolveFromPlatformFlag(string $flag, string $userAgent): RuntimePlatform
    {
        // Exact case-sensitive matches as per spec Requirement 1.6
        return match ($flag) {
            'android' => RuntimePlatform::MobileAppAndroid,
            'ios' => RuntimePlatform::MobileAppIos,
            'win32', 'windows' => RuntimePlatform::DesktopAppWindows,
            'mac', 'macos', 'darwin' => RuntimePlatform::DesktopAppMacOS,
            // Unrecognised token → fall through to UA detection
            default => self::resolveFromUserAgent($userAgent),
        };
    }

    /**
     * Resolve platform from User-Agent string alone (no env flags).
     */
    private static function resolveFromUserAgent(string $userAgent): RuntimePlatform
    {
        if (self::uaIsIos($userAgent)) {
            return RuntimePlatform::WebsiteIos;
        }
        if (self::uaIsAndroid($userAgent)) {
            return RuntimePlatform::WebsiteAndroid;
        }
        if (self::uaIsMac($userAgent)) {
            return RuntimePlatform::WebsiteMacOS;
        }

        return RuntimePlatform::WebsiteWindows;
    }

    /**
     * Resolve platform in console context (no HTTP signals available).
     * Uses only env vars, PHP constants, and PHP_OS_FAMILY.
     */
    private static function resolveFromEnvOnly(): RuntimePlatform
    {
        $flag = self::nativePlatformFlag();
        if ($flag !== null) {
            return self::resolveFromPlatformFlag($flag, '');
        }

        if (NativeServiceProvider::isNativeMobile()) {
            return RuntimePlatform::MobileAppAndroid; // best guess in console
        }

        if (NativeServiceProvider::isNativeDesktop()) {
            return PHP_OS_FAMILY === 'Darwin'
                ? RuntimePlatform::DesktopAppMacOS
                : RuntimePlatform::DesktopAppWindows;
        }

        // Safe fallback for console / CI
        return RuntimePlatform::WebsiteWindows;
    }

    /**
     * Determine if the current request is from a NativePHP Desktop (Electron) shell.
     */
    private static function isNativeDesktopShell(?Request $request, string $userAgent): bool
    {
        // Mobile takes precedence
        if (NativeServiceProvider::isNativeMobile()) {
            return false;
        }

        // Delegate to NativeServiceProvider's desktop detection
        if (NativeServiceProvider::isNativeDesktop()) {
            return true;
        }

        // Electron UA (NativePHP Desktop injects "Electron/" or "NativePHP" without "Mobile")
        if (preg_match('/NativePHP(?!.*Mobile)|Electron\//i', $userAgent)) {
            return ! self::uaIsMobile($userAgent);
        }

        // PWA standalone cookie on non-mobile UA → treat as desktop PWA
        if ($request && $request->cookie('app_display_mode') === 'standalone') {
            return ! self::uaIsMobile($userAgent);
        }

        return false;
    }

    // ── UA helpers ────────────────────────────────────────────────────────

    private static function uaIsMobile(string $userAgent): bool
    {
        return (bool) preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $userAgent);
    }

    private static function uaIsAndroid(string $userAgent): bool
    {
        return (bool) preg_match('/Android/i', $userAgent);
    }

    private static function uaIsIos(string $userAgent): bool
    {
        return (bool) preg_match('/iPhone|iPad|iPod/i', $userAgent);
    }

    private static function uaIsMac(string $userAgent): bool
    {
        return (bool) preg_match('/Macintosh|Mac OS X/i', $userAgent)
            || PHP_OS_FAMILY === 'Darwin';
    }

    private static function uaIsWindows(string $userAgent): bool
    {
        return (bool) preg_match('/Windows NT/i', $userAgent)
            || PHP_OS_FAMILY === 'Windows';
    }
}
