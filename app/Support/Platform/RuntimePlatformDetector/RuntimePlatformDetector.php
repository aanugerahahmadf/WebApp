<?php

namespace App\Support\Platform\RuntimePlatformDetector;

use App\Enums\PlatformMode\PlatformMode;
use App\Enums\RuntimePlatform\RuntimePlatform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Native\Mobile\Device;

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
                PlatformMode::Mobile => $this->detectMobilePlatform(),
                PlatformMode::Desktop => $this->detectDesktopPlatform(),
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
     * Detect the mobile platform using NativePHP Mobile Device API.
     *
     * @return RuntimePlatform The detected mobile app platform
     */
    private function detectMobilePlatform(): RuntimePlatform
    {
        // Check for NativePHP Mobile APIs
        if (class_exists(Device::class)) {
            try {
                $platform = Device::platform();

                return $platform === 'ios'
                    ? RuntimePlatform::MobileAppIos
                    : RuntimePlatform::MobileAppAndroid;
            } catch (\Throwable $e) {
                Log::debug('Failed to detect mobile platform from NativePHP API', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Fallback detection from environment or config
        $platform = config('native.platform', 'android');

        return $platform === 'ios'
            ? RuntimePlatform::MobileAppIos
            : RuntimePlatform::MobileAppAndroid;
    }

    /**
     * Detect the desktop platform using PHP_OS_FAMILY constant.
     *
     * @return RuntimePlatform The detected desktop app platform
     */
    private function detectDesktopPlatform(): RuntimePlatform
    {
        // Check PHP_OS_FAMILY constant for the operating system
        $os = PHP_OS_FAMILY;

        if ($os === 'Darwin') {
            return RuntimePlatform::DesktopAppMacOS;
        }

        // Default to Windows for Windows, Linux, and other operating systems
        return RuntimePlatform::DesktopAppWindows;
    }
}
