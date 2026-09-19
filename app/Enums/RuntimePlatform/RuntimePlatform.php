<?php

namespace App\Enums\RuntimePlatform;

use App\Support\Platform\PlatformFeatureRegistry\PlatformFeatureRegistry;

enum RuntimePlatform: string
{
    case WebsiteWindows = 'website_windows';
    case WebsiteMacOS = 'website_macos';
    case WebsiteAndroid = 'website_android';
    case WebsiteIos = 'website_ios';
    case DesktopAppWindows = 'desktop_app_windows';
    case DesktopAppMacOS = 'desktop_app_macos';
    case MobileAppAndroid = 'mobile_app_android';
    case MobileAppIos = 'mobile_app_ios';

    public function label(): string
    {
        return match ($this) {
            self::WebsiteWindows => 'Website (Windows)',
            self::WebsiteMacOS => 'Website (macOS)',
            self::WebsiteAndroid => 'Website (Android)',
            self::WebsiteIos => 'Website (iPhone/iPad)',
            self::DesktopAppWindows => 'Desktop App (Windows)',
            self::DesktopAppMacOS => 'Desktop App (macOS)',
            self::MobileAppAndroid => 'Mobile App (Android)',
            self::MobileAppIos => 'Mobile App (iOS)',
        };
    }

    public function isWebsite(): bool
    {
        return in_array($this, [
            self::WebsiteWindows,
            self::WebsiteMacOS,
            self::WebsiteAndroid,
            self::WebsiteIos,
        ], true);
    }

    public function isDesktopApp(): bool
    {
        return in_array($this, [
            self::DesktopAppWindows,
            self::DesktopAppMacOS,
        ], true);
    }

    public function isMobileApp(): bool
    {
        return in_array($this, [
            self::MobileAppAndroid,
            self::MobileAppIos,
        ], true);
    }

    public function isMobileShell(): bool
    {
        return $this->isMobileApp()
            || in_array($this, [self::WebsiteAndroid, self::WebsiteIos], true);
    }

    /**
     * Returns the camera mode used by the CBIR feature for this platform.
     *
     * - 'native' → NativePHP Mobile Camera API (MobileApp) or NativePHP Desktop Camera API (DesktopApp)
     * - 'webrtc' → browser MediaDevices.getUserMedia() API (Website)
     *
     * Aligns with the 'camera' and 'webrtc' features in PlatformFeatureRegistry:
     * platforms that have native camera capability ('camera' feature) return 'native',
     * while platforms that rely on WebRTC ('webrtc' feature) return 'webrtc'.
     *
     * @return 'native'|'webrtc'
     */
    public function cbirCameraMode(): string
    {
        return match ($this) {
            self::MobileAppAndroid, self::MobileAppIos,
            self::DesktopAppWindows, self::DesktopAppMacOS => 'native',
            default => 'webrtc',
        };
    }

    /**
     * Check if native camera access is available on this platform.
     * Native camera access is available on mobile and desktop apps.
     */
    public function hasNativeCameraAccess(): bool
    {
        return $this->isMobileApp() || $this->isDesktopApp();
    }

    /**
     * Check if WebRTC camera access is available on this platform.
     * WebRTC is available on all website platforms.
     */
    public function hasWebRTCAccess(): bool
    {
        return $this->isWebsite();
    }

    /**
     * Check if file system access is available on this platform.
     * File system access is available on mobile and desktop apps.
     */
    public function hasFileSystemAccess(): bool
    {
        return $this->isMobileApp() || $this->isDesktopApp();
    }

    /**
     * Check if desktop notifications are available on this platform.
     * Desktop notifications are only available on desktop apps.
     */
    public function hasDesktopNotifications(): bool
    {
        return $this->isDesktopApp();
    }

    /**
     * Check if push notifications are available on this platform.
     * Push notifications are only available on mobile apps.
     */
    public function hasPushNotifications(): bool
    {
        return $this->isMobileApp();
    }

    /**
     * Check if auto updates are available on this platform.
     * Auto updates are available on desktop apps.
     */
    public function hasAutoUpdates(): bool
    {
        return $this->isDesktopApp();
    }

    /**
     * Check if app badge functionality is available on this platform.
     * App badge is available on mobile apps.
     */
    public function hasAppBadge(): bool
    {
        return $this->isMobileApp();
    }

    /**
     * Check if a specific feature is available on this platform.
     * This method delegates to the PlatformFeatureRegistry for flexibility.
     *
     * @param  string  $feature  The feature name to check (camera, webrtc, file_system, etc.)
     */
    public function hasFeature(string $feature): bool
    {
        $registry = app(PlatformFeatureRegistry::class);

        return $registry->isAvailable($feature, $this);
    }

    /**
     * Get all features available on this platform.
     *
     * @return array<string> Array of feature names
     */
    public function getAvailableFeatures(): array
    {
        $registry = app(PlatformFeatureRegistry::class);

        return $registry->getAvailableFeatures($this);
    }
}
