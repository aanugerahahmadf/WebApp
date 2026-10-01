<?php

namespace App\Support\Platform\RuntimePlatformDetector;

use App\Enums\PlatformMode\PlatformMode;
use App\Enums\RuntimePlatform\RuntimePlatform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RuntimePlatformDetector
{
    /**
     * Detect the specific RuntimePlatform case based on the platform mode and device information.
     *
     * @param  PlatformMode  $mode  The platform mode (Web, Mobile, or Desktop)
     * @param  Request|null  $request  The HTTP request (required for Web mode)
     * @return RuntimePlatform The detected runtime platform
     */
    public function detect(PlatformMode $mode, ?Request $request = null): RuntimePlatform
    {
        try {
            return match ($mode) {
                PlatformMode::Web => $this->detectWebPlatform($request),
                PlatformMode::Mobile => $this->detectMobilePlatform($request),
                PlatformMode::Desktop => $this->detectDesktopPlatform($request),
            };
        } catch (\Throwable $e) {
            Log::warning('Platform detection failed', [
                'mode' => $mode->value,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return RuntimePlatform::WebsiteWindows;
        }
    }

    /**
     * Detect the web platform from the user agent string.
     *
     * @param  Request|null  $request  The HTTP request containing user agent
     * @return RuntimePlatform The detected website platform
     */
    private function detectWebPlatform(?Request $request): RuntimePlatform
    {
        if (! $request) {
            return RuntimePlatform::WebsiteWindows;
        }

        $userAgent = strtolower($request->userAgent() ?? '');

        // Check for iOS devices (iPhone, iPad, iPod)
        if (str_contains($userAgent, 'iphone') ||
            str_contains($userAgent, 'ipad') ||
            str_contains($userAgent, 'ipod')) {
            return RuntimePlatform::WebsiteIos;
        }

        // Check for Android devices
        if (str_contains($userAgent, 'android')) {
            return RuntimePlatform::WebsiteAndroid;
        }

        // Check for macOS (including Safari on Mac)
        if (str_contains($userAgent, 'mac') ||
            str_contains($userAgent, 'darwin')) {
            return RuntimePlatform::WebsiteMacOS;
        }

        // Default to Windows for all other cases
        return RuntimePlatform::WebsiteWindows;
    }

    /**
     * Detect the platform of the Capacitor mobile shell (Android or iOS).
     *
     * The shell reaches this server over plain HTTP, so there is no on-device
     * API to ask. The User-Agent is the only signal that distinguishes the
     * WebView from a real mobile browser, and `CAPACITOR_PLATFORM` is the
     * explicit override for builds whose WebView sends nothing distinctive.
     */
    private function detectMobilePlatform(?Request $request): RuntimePlatform
    {
        $forced = config('app-platform.force_platform') ?: env('CAPACITOR_PLATFORM');

        if (is_string($forced) && $forced !== '' && str_contains(strtolower($forced), 'ios')) {
            return RuntimePlatform::MobileAppIos;
        }

        if ($request && preg_match('/iPhone|iPad|iPod/i', (string) $request->userAgent()) === 1) {
            return RuntimePlatform::MobileAppIos;
        }

        return RuntimePlatform::MobileAppAndroid;
    }

    /**
     * Detect the platform of the Capacitor desktop shell (Electron).
     */
    private function detectDesktopPlatform(?Request $request): RuntimePlatform
    {
        if ($request) {
            $userAgent = (string) $request->userAgent();

            if (str_contains($userAgent, 'Macintosh') || str_contains($userAgent, 'Mac OS X')) {
                return RuntimePlatform::DesktopAppMacOS;
            }
        }

        // No request (queue worker, scheduler): fall back to the server's OS.
        return PHP_OS_FAMILY === 'Darwin'
            ? RuntimePlatform::DesktopAppMacOS
            : RuntimePlatform::DesktopAppWindows;
    }
}
