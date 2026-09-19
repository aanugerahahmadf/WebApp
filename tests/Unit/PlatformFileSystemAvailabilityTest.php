<?php

use App\Enums\RuntimePlatform\RuntimePlatform;
use App\Support\Platform\PlatformFeatureRegistry\PlatformFeatureRegistry;

/**
 * Property 7: File System Availability Matches Platform Mode
 *
 * **Validates: Requirements 5.8**
 *
 * For all RuntimePlatform enum cases, file system access feature availability
 * SHALL return true if and only if the platform is in Mobile Native Mode or
 * Desktop App Mode (MobileAppAndroid, MobileAppIos, DesktopAppWindows, DesktopAppMacOS).
 */
describe('Property 7: File System Availability Matches Platform Mode', function () {
    beforeEach(function () {
        $this->registry = new PlatformFeatureRegistry;
    });

    test('file_system returns true for all Mobile platforms', function () {
        // **Validates: Requirements 5.8**
        // Property: file_system is available for all mobile app platforms
        $mobilePlatforms = [
            RuntimePlatform::MobileAppAndroid,
            RuntimePlatform::MobileAppIos,
        ];

        foreach ($mobilePlatforms as $platform) {
            $result = $this->registry->isAvailable('file_system', $platform);

            expect($result)->toBeTrue();
        }
    });

    test('file_system returns true for all Desktop platforms', function () {
        // **Validates: Requirements 5.8**
        // Property: file_system is available for all desktop app platforms
        $desktopPlatforms = [
            RuntimePlatform::DesktopAppWindows,
            RuntimePlatform::DesktopAppMacOS,
        ];

        foreach ($desktopPlatforms as $platform) {
            $result = $this->registry->isAvailable('file_system', $platform);

            expect($result)->toBeTrue();
        }
    });

    test('file_system returns false for all Website platforms', function () {
        // **Validates: Requirements 5.8**
        // Property: file_system is NOT available for any website platform
        $websitePlatforms = [
            RuntimePlatform::WebsiteWindows,
            RuntimePlatform::WebsiteMacOS,
            RuntimePlatform::WebsiteAndroid,
            RuntimePlatform::WebsiteIos,
        ];

        foreach ($websitePlatforms as $platform) {
            $result = $this->registry->isAvailable('file_system', $platform);

            expect($result)->toBeFalse();
        }
    });

    test('file_system availability matches isMobileApp() or isDesktopApp() for every RuntimePlatform case', function () {
        // **Validates: Requirements 5.8**
        // Property: file_system available ⟺ (isMobileApp() OR isDesktopApp())
        // This is the core biconditional that must hold for ALL enum cases.
        $allPlatforms = RuntimePlatform::cases();

        // Ensure we cover all 8 enum cases
        expect(count($allPlatforms))->toBe(8);

        foreach ($allPlatforms as $platform) {
            $fileSystemAvailable = $this->registry->isAvailable('file_system', $platform);
            $isNativePlatform = $platform->isMobileApp() || $platform->isDesktopApp();

            // Biconditional: available if and only if native
            expect($fileSystemAvailable)->toBe($isNativePlatform);
        }
    });

    test('file_system availability is mutually exclusive with isWebsite() for every RuntimePlatform case', function () {
        // **Validates: Requirements 5.8**
        // Property: file_system available ⟺ NOT isWebsite()
        $allPlatforms = RuntimePlatform::cases();

        foreach ($allPlatforms as $platform) {
            $fileSystemAvailable = $this->registry->isAvailable('file_system', $platform);
            $isWebsite = $platform->isWebsite();

            // file_system and isWebsite() must be mutually exclusive
            expect($fileSystemAvailable && $isWebsite)->toBeFalse();

            // Every platform is either native (file_system=true) or website (isWebsite=true)
            expect($fileSystemAvailable || $isWebsite)->toBeTrue();
        }
    });

    test('hasFileSystemAccess() enum helper is consistent with PlatformFeatureRegistry for every case', function () {
        // **Validates: Requirements 5.8**
        // Property: RuntimePlatform::hasFileSystemAccess() must agree with
        //           PlatformFeatureRegistry::isAvailable('file_system', ...)
        $allPlatforms = RuntimePlatform::cases();

        foreach ($allPlatforms as $platform) {
            $registryResult = $this->registry->isAvailable('file_system', $platform);
            $enumResult = $platform->hasFileSystemAccess();

            expect($registryResult)->toBe($enumResult);
        }
    });
});
