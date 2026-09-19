<?php

use App\Enums\PlatformMode\PlatformMode;
use App\Enums\RuntimePlatform\RuntimePlatform;
use App\Support\Platform\PlatformFeatureRegistry\PlatformFeatureRegistry;

if (! function_exists('platform_mode')) {
    /**
     * Get the current platform mode.
     */
    function platform_mode(): PlatformMode
    {
        return app('platform.mode');
    }
}

if (! function_exists('runtime_platform')) {
    /**
     * Get the current runtime platform.
     */
    function runtime_platform(): RuntimePlatform
    {
        return app('runtime.platform');
    }
}

if (! function_exists('platform_feature')) {
    /**
     * Check if a platform feature is available on the current runtime platform.
     *
     * @param  string  $feature  The feature name to check (e.g. 'camera', 'webrtc', 'file_system')
     */
    function platform_feature(string $feature): bool
    {
        $registry = app(PlatformFeatureRegistry::class);

        return $registry->isAvailable($feature, runtime_platform());
    }
}

if (! function_exists('is_web_mode')) {
    /**
     * Determine if the application is running in web mode.
     */
    function is_web_mode(): bool
    {
        return platform_mode() === PlatformMode::Web;
    }
}

if (! function_exists('is_mobile_mode')) {
    /**
     * Determine if the application is running in mobile mode.
     */
    function is_mobile_mode(): bool
    {
        return platform_mode() === PlatformMode::Mobile;
    }
}

if (! function_exists('is_desktop_mode')) {
    /**
     * Determine if the application is running in desktop mode.
     */
    function is_desktop_mode(): bool
    {
        return platform_mode() === PlatformMode::Desktop;
    }
}
