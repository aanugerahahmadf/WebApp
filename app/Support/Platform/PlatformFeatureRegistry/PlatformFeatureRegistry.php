<?php

namespace App\Support\Platform\PlatformFeatureRegistry;

use App\Enums\RuntimePlatform\RuntimePlatform;

class PlatformFeatureRegistry
{
    private const FEATURE_MATRIX = [
        'camera' => [
            RuntimePlatform::MobileAppAndroid,
            RuntimePlatform::MobileAppIos,
            RuntimePlatform::DesktopAppWindows,
            RuntimePlatform::DesktopAppMacOS,
        ],
        'desktop_notifications' => [
            RuntimePlatform::DesktopAppWindows,
            RuntimePlatform::DesktopAppMacOS,
        ],
        'push_notifications' => [
            RuntimePlatform::MobileAppAndroid,
            RuntimePlatform::MobileAppIos,
        ],
        'file_system' => [
            RuntimePlatform::MobileAppAndroid,
            RuntimePlatform::MobileAppIos,
            RuntimePlatform::DesktopAppWindows,
            RuntimePlatform::DesktopAppMacOS,
        ],
        'webrtc' => [
            RuntimePlatform::WebsiteWindows,
            RuntimePlatform::WebsiteMacOS,
            RuntimePlatform::WebsiteAndroid,
            RuntimePlatform::WebsiteIos,
        ],
        'auto_updates' => [
            RuntimePlatform::DesktopAppWindows,
            RuntimePlatform::DesktopAppMacOS,
        ],
        'app_badge' => [
            RuntimePlatform::MobileAppAndroid,
            RuntimePlatform::MobileAppIos,
        ],
    ];

    /**
     * Check if a feature is available on the specified platform.
     *
     * @param  string  $feature  The feature name to check
     * @param  RuntimePlatform  $platform  The runtime platform to check against
     * @return bool True if the feature is available on the platform, false otherwise
     */
    public function isAvailable(string $feature, RuntimePlatform $platform): bool
    {
        if (! isset(self::FEATURE_MATRIX[$feature])) {
            return false;
        }

        return in_array($platform, self::FEATURE_MATRIX[$feature], true);
    }

    /**
     * Get all features available on the specified platform.
     *
     * @param  RuntimePlatform  $platform  The runtime platform to query
     * @return array<string> Array of feature names available on the platform
     */
    public function getAvailableFeatures(RuntimePlatform $platform): array
    {
        $features = [];

        foreach (self::FEATURE_MATRIX as $feature => $platforms) {
            if (in_array($platform, $platforms, true)) {
                $features[] = $feature;
            }
        }

        return $features;
    }

    /**
     * Get all platforms that support a specific feature.
     *
     * @param  string  $feature  The feature name to query
     * @return array<RuntimePlatform> Array of RuntimePlatform cases that support the feature
     */
    public function getPlatformsForFeature(string $feature): array
    {
        return self::FEATURE_MATRIX[$feature] ?? [];
    }
}
