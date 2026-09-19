<?php

use App\Enums\RuntimePlatform\RuntimePlatform;
use App\Support\Platform\PlatformFeatureRegistry\PlatformFeatureRegistry;

describe('RuntimePlatform Feature Integration', function () {
    beforeEach(function () {
        $this->registry = new PlatformFeatureRegistry;
    });

    describe('hasNativeCameraAccess()', function () {
        test('returns true for mobile app platforms', function () {
            expect(RuntimePlatform::MobileAppAndroid->hasNativeCameraAccess())->toBeTrue();
            expect(RuntimePlatform::MobileAppIos->hasNativeCameraAccess())->toBeTrue();
        });

        test('returns true for desktop app platforms', function () {
            expect(RuntimePlatform::DesktopAppWindows->hasNativeCameraAccess())->toBeTrue();
            expect(RuntimePlatform::DesktopAppMacOS->hasNativeCameraAccess())->toBeTrue();
        });

        test('returns false for website platforms', function () {
            expect(RuntimePlatform::WebsiteWindows->hasNativeCameraAccess())->toBeFalse();
            expect(RuntimePlatform::WebsiteMacOS->hasNativeCameraAccess())->toBeFalse();
            expect(RuntimePlatform::WebsiteAndroid->hasNativeCameraAccess())->toBeFalse();
            expect(RuntimePlatform::WebsiteIos->hasNativeCameraAccess())->toBeFalse();
        });

        test('aligns with camera feature in registry', function () {
            $allPlatforms = [
                RuntimePlatform::WebsiteWindows,
                RuntimePlatform::WebsiteMacOS,
                RuntimePlatform::WebsiteAndroid,
                RuntimePlatform::WebsiteIos,
                RuntimePlatform::DesktopAppWindows,
                RuntimePlatform::DesktopAppMacOS,
                RuntimePlatform::MobileAppAndroid,
                RuntimePlatform::MobileAppIos,
            ];

            foreach ($allPlatforms as $platform) {
                $hasNativeCamera = $platform->hasNativeCameraAccess();
                $cameraInRegistry = $this->registry->isAvailable('camera', $platform);

                expect($hasNativeCamera)->toBe($cameraInRegistry);
            }
        });
    });

    describe('hasWebRTCAccess()', function () {
        test('returns true for website platforms', function () {
            expect(RuntimePlatform::WebsiteWindows->hasWebRTCAccess())->toBeTrue();
            expect(RuntimePlatform::WebsiteMacOS->hasWebRTCAccess())->toBeTrue();
            expect(RuntimePlatform::WebsiteAndroid->hasWebRTCAccess())->toBeTrue();
            expect(RuntimePlatform::WebsiteIos->hasWebRTCAccess())->toBeTrue();
        });

        test('returns false for native platforms', function () {
            expect(RuntimePlatform::MobileAppAndroid->hasWebRTCAccess())->toBeFalse();
            expect(RuntimePlatform::MobileAppIos->hasWebRTCAccess())->toBeFalse();
            expect(RuntimePlatform::DesktopAppWindows->hasWebRTCAccess())->toBeFalse();
            expect(RuntimePlatform::DesktopAppMacOS->hasWebRTCAccess())->toBeFalse();
        });

        test('aligns with webrtc feature in registry', function () {
            $allPlatforms = [
                RuntimePlatform::WebsiteWindows,
                RuntimePlatform::WebsiteMacOS,
                RuntimePlatform::WebsiteAndroid,
                RuntimePlatform::WebsiteIos,
                RuntimePlatform::DesktopAppWindows,
                RuntimePlatform::DesktopAppMacOS,
                RuntimePlatform::MobileAppAndroid,
                RuntimePlatform::MobileAppIos,
            ];

            foreach ($allPlatforms as $platform) {
                $hasWebRTC = $platform->hasWebRTCAccess();
                $webrtcInRegistry = $this->registry->isAvailable('webrtc', $platform);

                expect($hasWebRTC)->toBe($webrtcInRegistry);
            }
        });
    });

    describe('hasFileSystemAccess()', function () {
        test('returns true for native platforms', function () {
            expect(RuntimePlatform::MobileAppAndroid->hasFileSystemAccess())->toBeTrue();
            expect(RuntimePlatform::MobileAppIos->hasFileSystemAccess())->toBeTrue();
            expect(RuntimePlatform::DesktopAppWindows->hasFileSystemAccess())->toBeTrue();
            expect(RuntimePlatform::DesktopAppMacOS->hasFileSystemAccess())->toBeTrue();
        });

        test('returns false for website platforms', function () {
            expect(RuntimePlatform::WebsiteWindows->hasFileSystemAccess())->toBeFalse();
            expect(RuntimePlatform::WebsiteMacOS->hasFileSystemAccess())->toBeFalse();
            expect(RuntimePlatform::WebsiteAndroid->hasFileSystemAccess())->toBeFalse();
            expect(RuntimePlatform::WebsiteIos->hasFileSystemAccess())->toBeFalse();
        });

        test('aligns with file_system feature in registry', function () {
            $allPlatforms = [
                RuntimePlatform::WebsiteWindows,
                RuntimePlatform::WebsiteMacOS,
                RuntimePlatform::WebsiteAndroid,
                RuntimePlatform::WebsiteIos,
                RuntimePlatform::DesktopAppWindows,
                RuntimePlatform::DesktopAppMacOS,
                RuntimePlatform::MobileAppAndroid,
                RuntimePlatform::MobileAppIos,
            ];

            foreach ($allPlatforms as $platform) {
                $hasFileSystem = $platform->hasFileSystemAccess();
                $fileSystemInRegistry = $this->registry->isAvailable('file_system', $platform);

                expect($hasFileSystem)->toBe($fileSystemInRegistry);
            }
        });
    });

    describe('hasDesktopNotifications()', function () {
        test('returns true for desktop platforms', function () {
            expect(RuntimePlatform::DesktopAppWindows->hasDesktopNotifications())->toBeTrue();
            expect(RuntimePlatform::DesktopAppMacOS->hasDesktopNotifications())->toBeTrue();
        });

        test('returns false for non-desktop platforms', function () {
            expect(RuntimePlatform::WebsiteWindows->hasDesktopNotifications())->toBeFalse();
            expect(RuntimePlatform::WebsiteMacOS->hasDesktopNotifications())->toBeFalse();
            expect(RuntimePlatform::WebsiteAndroid->hasDesktopNotifications())->toBeFalse();
            expect(RuntimePlatform::WebsiteIos->hasDesktopNotifications())->toBeFalse();
            expect(RuntimePlatform::MobileAppAndroid->hasDesktopNotifications())->toBeFalse();
            expect(RuntimePlatform::MobileAppIos->hasDesktopNotifications())->toBeFalse();
        });

        test('aligns with desktop_notifications feature in registry', function () {
            $allPlatforms = [
                RuntimePlatform::WebsiteWindows,
                RuntimePlatform::WebsiteMacOS,
                RuntimePlatform::WebsiteAndroid,
                RuntimePlatform::WebsiteIos,
                RuntimePlatform::DesktopAppWindows,
                RuntimePlatform::DesktopAppMacOS,
                RuntimePlatform::MobileAppAndroid,
                RuntimePlatform::MobileAppIos,
            ];

            foreach ($allPlatforms as $platform) {
                $hasDesktopNotifications = $platform->hasDesktopNotifications();
                $desktopNotificationsInRegistry = $this->registry->isAvailable('desktop_notifications', $platform);

                expect($hasDesktopNotifications)->toBe($desktopNotificationsInRegistry);
            }
        });
    });

    describe('hasPushNotifications()', function () {
        test('returns true for mobile platforms', function () {
            expect(RuntimePlatform::MobileAppAndroid->hasPushNotifications())->toBeTrue();
            expect(RuntimePlatform::MobileAppIos->hasPushNotifications())->toBeTrue();
        });

        test('returns false for non-mobile platforms', function () {
            expect(RuntimePlatform::WebsiteWindows->hasPushNotifications())->toBeFalse();
            expect(RuntimePlatform::WebsiteMacOS->hasPushNotifications())->toBeFalse();
            expect(RuntimePlatform::WebsiteAndroid->hasPushNotifications())->toBeFalse();
            expect(RuntimePlatform::WebsiteIos->hasPushNotifications())->toBeFalse();
            expect(RuntimePlatform::DesktopAppWindows->hasPushNotifications())->toBeFalse();
            expect(RuntimePlatform::DesktopAppMacOS->hasPushNotifications())->toBeFalse();
        });

        test('aligns with push_notifications feature in registry', function () {
            $allPlatforms = [
                RuntimePlatform::WebsiteWindows,
                RuntimePlatform::WebsiteMacOS,
                RuntimePlatform::WebsiteAndroid,
                RuntimePlatform::WebsiteIos,
                RuntimePlatform::DesktopAppWindows,
                RuntimePlatform::DesktopAppMacOS,
                RuntimePlatform::MobileAppAndroid,
                RuntimePlatform::MobileAppIos,
            ];

            foreach ($allPlatforms as $platform) {
                $hasPushNotifications = $platform->hasPushNotifications();
                $pushNotificationsInRegistry = $this->registry->isAvailable('push_notifications', $platform);

                expect($hasPushNotifications)->toBe($pushNotificationsInRegistry);
            }
        });
    });

    describe('hasAutoUpdates()', function () {
        test('returns true for desktop platforms', function () {
            expect(RuntimePlatform::DesktopAppWindows->hasAutoUpdates())->toBeTrue();
            expect(RuntimePlatform::DesktopAppMacOS->hasAutoUpdates())->toBeTrue();
        });

        test('returns false for non-desktop platforms', function () {
            expect(RuntimePlatform::WebsiteWindows->hasAutoUpdates())->toBeFalse();
            expect(RuntimePlatform::WebsiteMacOS->hasAutoUpdates())->toBeFalse();
            expect(RuntimePlatform::WebsiteAndroid->hasAutoUpdates())->toBeFalse();
            expect(RuntimePlatform::WebsiteIos->hasAutoUpdates())->toBeFalse();
            expect(RuntimePlatform::MobileAppAndroid->hasAutoUpdates())->toBeFalse();
            expect(RuntimePlatform::MobileAppIos->hasAutoUpdates())->toBeFalse();
        });

        test('aligns with auto_updates feature in registry', function () {
            $allPlatforms = [
                RuntimePlatform::WebsiteWindows,
                RuntimePlatform::WebsiteMacOS,
                RuntimePlatform::WebsiteAndroid,
                RuntimePlatform::WebsiteIos,
                RuntimePlatform::DesktopAppWindows,
                RuntimePlatform::DesktopAppMacOS,
                RuntimePlatform::MobileAppAndroid,
                RuntimePlatform::MobileAppIos,
            ];

            foreach ($allPlatforms as $platform) {
                $hasAutoUpdates = $platform->hasAutoUpdates();
                $autoUpdatesInRegistry = $this->registry->isAvailable('auto_updates', $platform);

                expect($hasAutoUpdates)->toBe($autoUpdatesInRegistry);
            }
        });
    });

    describe('hasAppBadge()', function () {
        test('returns true for mobile platforms', function () {
            expect(RuntimePlatform::MobileAppAndroid->hasAppBadge())->toBeTrue();
            expect(RuntimePlatform::MobileAppIos->hasAppBadge())->toBeTrue();
        });

        test('returns false for non-mobile platforms', function () {
            expect(RuntimePlatform::WebsiteWindows->hasAppBadge())->toBeFalse();
            expect(RuntimePlatform::WebsiteMacOS->hasAppBadge())->toBeFalse();
            expect(RuntimePlatform::WebsiteAndroid->hasAppBadge())->toBeFalse();
            expect(RuntimePlatform::WebsiteIos->hasAppBadge())->toBeFalse();
            expect(RuntimePlatform::DesktopAppWindows->hasAppBadge())->toBeFalse();
            expect(RuntimePlatform::DesktopAppMacOS->hasAppBadge())->toBeFalse();
        });

        test('aligns with app_badge feature in registry', function () {
            $allPlatforms = [
                RuntimePlatform::WebsiteWindows,
                RuntimePlatform::WebsiteMacOS,
                RuntimePlatform::WebsiteAndroid,
                RuntimePlatform::WebsiteIos,
                RuntimePlatform::DesktopAppWindows,
                RuntimePlatform::DesktopAppMacOS,
                RuntimePlatform::MobileAppAndroid,
                RuntimePlatform::MobileAppIos,
            ];

            foreach ($allPlatforms as $platform) {
                $hasAppBadge = $platform->hasAppBadge();
                $appBadgeInRegistry = $this->registry->isAvailable('app_badge', $platform);

                expect($hasAppBadge)->toBe($appBadgeInRegistry);
            }
        });
    });

    describe('cbirCameraMode() alignment with feature registry', function () {
        test('native camera mode aligns with camera feature availability', function () {
            $allPlatforms = [
                RuntimePlatform::WebsiteWindows,
                RuntimePlatform::WebsiteMacOS,
                RuntimePlatform::WebsiteAndroid,
                RuntimePlatform::WebsiteIos,
                RuntimePlatform::DesktopAppWindows,
                RuntimePlatform::DesktopAppMacOS,
                RuntimePlatform::MobileAppAndroid,
                RuntimePlatform::MobileAppIos,
            ];

            foreach ($allPlatforms as $platform) {
                $cameraMode = $platform->cbirCameraMode();
                $hasNativeCamera = $this->registry->isAvailable('camera', $platform);
                $hasWebRTC = $this->registry->isAvailable('webrtc', $platform);

                // If camera mode is 'native', then the native camera feature must be available
                if ($cameraMode === 'native') {
                    expect($hasNativeCamera)->toBeTrue();
                    expect($hasWebRTC)->toBeFalse();
                }

                // If camera mode is 'webrtc', then the webrtc feature must be available
                if ($cameraMode === 'webrtc') {
                    expect($hasWebRTC)->toBeTrue();
                    expect($hasNativeCamera)->toBeFalse();
                }
            }
        });

        test('mobile app platforms use native camera mode', function () {
            expect(RuntimePlatform::MobileAppAndroid->cbirCameraMode())->toBe('native');
            expect(RuntimePlatform::MobileAppIos->cbirCameraMode())->toBe('native');

            // Verify these platforms have camera feature
            expect($this->registry->isAvailable('camera', RuntimePlatform::MobileAppAndroid))->toBeTrue();
            expect($this->registry->isAvailable('camera', RuntimePlatform::MobileAppIos))->toBeTrue();
        });

        test('website platforms use webrtc camera mode', function () {
            expect(RuntimePlatform::WebsiteWindows->cbirCameraMode())->toBe('webrtc');
            expect(RuntimePlatform::WebsiteMacOS->cbirCameraMode())->toBe('webrtc');
            expect(RuntimePlatform::WebsiteAndroid->cbirCameraMode())->toBe('webrtc');
            expect(RuntimePlatform::WebsiteIos->cbirCameraMode())->toBe('webrtc');

            // Verify these platforms have webrtc feature
            expect($this->registry->isAvailable('webrtc', RuntimePlatform::WebsiteWindows))->toBeTrue();
            expect($this->registry->isAvailable('webrtc', RuntimePlatform::WebsiteMacOS))->toBeTrue();
            expect($this->registry->isAvailable('webrtc', RuntimePlatform::WebsiteAndroid))->toBeTrue();
            expect($this->registry->isAvailable('webrtc', RuntimePlatform::WebsiteIos))->toBeTrue();
        });

        test('desktop platforms use native camera mode and have native camera capability', function () {
            // Desktop platforms have native camera (NativePHP Desktop Camera API)
            expect(RuntimePlatform::DesktopAppWindows->cbirCameraMode())->toBe('native');
            expect(RuntimePlatform::DesktopAppMacOS->cbirCameraMode())->toBe('native');

            // Verify these platforms have camera feature (native capability)
            expect($this->registry->isAvailable('camera', RuntimePlatform::DesktopAppWindows))->toBeTrue();
            expect($this->registry->isAvailable('camera', RuntimePlatform::DesktopAppMacOS))->toBeTrue();
        });
    });

    describe('hasFeature() method', function () {
        test('delegates to feature registry correctly', function () {
            $allFeatures = [
                'camera',
                'desktop_notifications',
                'push_notifications',
                'file_system',
                'webrtc',
                'auto_updates',
                'app_badge',
            ];

            $allPlatforms = [
                RuntimePlatform::WebsiteWindows,
                RuntimePlatform::WebsiteMacOS,
                RuntimePlatform::WebsiteAndroid,
                RuntimePlatform::WebsiteIos,
                RuntimePlatform::DesktopAppWindows,
                RuntimePlatform::DesktopAppMacOS,
                RuntimePlatform::MobileAppAndroid,
                RuntimePlatform::MobileAppIos,
            ];

            foreach ($allPlatforms as $platform) {
                foreach ($allFeatures as $feature) {
                    $hasFeature = $platform->hasFeature($feature);
                    $registryResult = $this->registry->isAvailable($feature, $platform);

                    expect($hasFeature)->toBe($registryResult);
                }
            }
        });

        test('returns false for unknown features', function () {
            $allPlatforms = [
                RuntimePlatform::WebsiteWindows,
                RuntimePlatform::MobileAppAndroid,
                RuntimePlatform::DesktopAppWindows,
            ];

            foreach ($allPlatforms as $platform) {
                expect($platform->hasFeature('unknown_feature'))->toBeFalse();
                expect($platform->hasFeature(''))->toBeFalse();
            }
        });
    });

    describe('getAvailableFeatures() method', function () {
        test('delegates to feature registry correctly', function () {
            $allPlatforms = [
                RuntimePlatform::WebsiteWindows,
                RuntimePlatform::WebsiteMacOS,
                RuntimePlatform::WebsiteAndroid,
                RuntimePlatform::WebsiteIos,
                RuntimePlatform::DesktopAppWindows,
                RuntimePlatform::DesktopAppMacOS,
                RuntimePlatform::MobileAppAndroid,
                RuntimePlatform::MobileAppIos,
            ];

            foreach ($allPlatforms as $platform) {
                $features = $platform->getAvailableFeatures();
                $registryFeatures = $this->registry->getAvailableFeatures($platform);

                expect($features)->toBe($registryFeatures);
                expect($features)->toBeArray();
            }
        });

        test('mobile platforms return expected features', function () {
            $features = RuntimePlatform::MobileAppAndroid->getAvailableFeatures();

            expect($features)->toContain('camera');
            expect($features)->toContain('push_notifications');
            expect($features)->toContain('file_system');
            expect($features)->toContain('app_badge');
        });

        test('desktop platforms return expected features', function () {
            $features = RuntimePlatform::DesktopAppWindows->getAvailableFeatures();

            expect($features)->toContain('camera');
            expect($features)->toContain('desktop_notifications');
            expect($features)->toContain('file_system');
            expect($features)->toContain('auto_updates');
        });

        test('website platforms return expected features', function () {
            $features = RuntimePlatform::WebsiteWindows->getAvailableFeatures();

            expect($features)->toContain('webrtc');
            expect($features)->not->toContain('camera');
        });
    });

    describe('consistency properties', function () {
        test('all convenience methods align with hasFeature()', function () {
            $allPlatforms = [
                RuntimePlatform::WebsiteWindows,
                RuntimePlatform::WebsiteMacOS,
                RuntimePlatform::WebsiteAndroid,
                RuntimePlatform::WebsiteIos,
                RuntimePlatform::DesktopAppWindows,
                RuntimePlatform::DesktopAppMacOS,
                RuntimePlatform::MobileAppAndroid,
                RuntimePlatform::MobileAppIos,
            ];

            foreach ($allPlatforms as $platform) {
                // Verify all convenience methods match hasFeature()
                expect($platform->hasNativeCameraAccess())->toBe($platform->hasFeature('camera'));
                expect($platform->hasWebRTCAccess())->toBe($platform->hasFeature('webrtc'));
                expect($platform->hasFileSystemAccess())->toBe($platform->hasFeature('file_system'));
                expect($platform->hasDesktopNotifications())->toBe($platform->hasFeature('desktop_notifications'));
                expect($platform->hasPushNotifications())->toBe($platform->hasFeature('push_notifications'));
                expect($platform->hasAutoUpdates())->toBe($platform->hasFeature('auto_updates'));
                expect($platform->hasAppBadge())->toBe($platform->hasFeature('app_badge'));
            }
        });

        test('convenience methods are consistent with category methods', function () {
            $allPlatforms = [
                RuntimePlatform::WebsiteWindows,
                RuntimePlatform::WebsiteMacOS,
                RuntimePlatform::WebsiteAndroid,
                RuntimePlatform::WebsiteIos,
                RuntimePlatform::DesktopAppWindows,
                RuntimePlatform::DesktopAppMacOS,
                RuntimePlatform::MobileAppAndroid,
                RuntimePlatform::MobileAppIos,
            ];

            foreach ($allPlatforms as $platform) {
                // Native camera = mobile or desktop app
                $expectedNativeCamera = $platform->isMobileApp() || $platform->isDesktopApp();
                expect($platform->hasNativeCameraAccess())->toBe($expectedNativeCamera);

                // WebRTC = website
                expect($platform->hasWebRTCAccess())->toBe($platform->isWebsite());

                // File system = mobile or desktop app
                $expectedFileSystem = $platform->isMobileApp() || $platform->isDesktopApp();
                expect($platform->hasFileSystemAccess())->toBe($expectedFileSystem);

                // Desktop notifications = desktop app
                expect($platform->hasDesktopNotifications())->toBe($platform->isDesktopApp());

                // Push notifications = mobile app
                expect($platform->hasPushNotifications())->toBe($platform->isMobileApp());

                // Auto updates = desktop app
                expect($platform->hasAutoUpdates())->toBe($platform->isDesktopApp());

                // App badge = mobile app
                expect($platform->hasAppBadge())->toBe($platform->isMobileApp());
            }
        });
    });
});
