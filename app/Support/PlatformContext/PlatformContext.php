<?php

namespace App\Support\PlatformContext;

use App\Enums\RuntimePlatform\RuntimePlatform;
use App\Support\AppPlatform\AppPlatform;
use Illuminate\Http\Request;

/**
 * Backwards-compatible façade over {@see AppPlatform}.
 *
 * This class used to be the real implementation: it sniffed the NativePHP
 * runtime (the `NATIVEPHP_RUNNING` constant, the `nativephp-internal.platform`
 * config, a device-local SQLite connection) to work out whether it was running
 * on Android, iOS or Electron. That runtime is gone — the shells in
 * `app/Capacitor/{AdminApp,UserApp}` are Capacitor wrappers around a Laravel
 * server reached over plain HTTP, so there is no embedded PHP and no
 * device-local database to detect.
 *
 * `AppPlatform` already answers the same question from request-level signals
 * (the `X-Shell` header, the `app_shell` cookie, `CAPACITOR_PLATFORM`, the
 * User-Agent, the `app_display_mode` cookie), so this class now only forwards.
 * It stays in place because it is the name a large number of call sites and
 * Blade partials already import.
 *
 * New code should call {@see AppPlatform} directly.
 *
 * @see AppPlatform
 */
class PlatformContext
{
    /**
     * Resolve the platform for the current request.
     */
    public static function current(?Request $request = null): RuntimePlatform
    {
        return AppPlatform::current($request);
    }

    /**
     * Clear any memoised state so the next call re-runs detection.
     */
    public static function reset(): void
    {
        AppPlatform::reset();
    }

    /**
     * True for a phone-sized surface: the Capacitor shell or a mobile browser.
     */
    public static function isAnyMobile(?Request $request = null): bool
    {
        return AppPlatform::isAnyMobile($request);
    }

    /**
     * True only for the Capacitor mobile shell (Android or iOS).
     */
    public static function isNativeMobile(?Request $request = null): bool
    {
        return AppPlatform::isNativeMobile($request);
    }

    /**
     * True only for the Capacitor desktop shell (Electron).
     */
    public static function isNativeDesktop(?Request $request = null): bool
    {
        return AppPlatform::isDesktopApp($request);
    }

    /**
     * @return 'native'|'webrtc'
     */
    public static function cbirCameraMode(?Request $request = null): string
    {
        return AppPlatform::cbirCameraMode($request);
    }

    /**
     * The host that serves this application, for building absolute URLs.
     */
    public static function mobileHostIp(): string
    {
        return AppPlatform::mobileHostIp();
    }

    /**
     * Rewrite a local URL onto the origin serving the current request.
     */
    public static function normalizeUrl(string $url): string
    {
        return AppPlatform::normalizeUrl($url);
    }
}
