<?php

use App\Enums\RuntimePlatform\RuntimePlatform;
use App\Support\Platform\PlatformFeatureRegistry\PlatformFeatureRegistry;

describe('PlatformFeatureRegistry', function () {
    beforeEach(function () {
        $this->registry = new PlatformFeatureRegistry;
    });

    describe('isAvailable()', function () {
        describe('camera feature', function () {
            test('returns true for MobileAppAndroid', function () {
                $result = $this->registry->isAvailable('camera', RuntimePlatform::MobileAppAndroid);

                expect($result)->toBeTrue();
            });

            test('returns true for MobileAppIos', function () {
                $result = $this->registry->isAvailable('camera', RuntimePlatform::MobileAppIos);

                expect($result)->toBeTrue();
            });

            test('returns true for DesktopAppWindows', function () {
                $result = $this->registry->isAvailable('camera', RuntimePlatform::DesktopAppWindows);

                expect($result)->toBeTrue();
            });

            test('returns true for DesktopAppMacOS', function () {
                $result = $this->registry->isAvailable('camera', RuntimePlatform::DesktopAppMacOS);

                expect($result)->toBeTrue();
            });

            test('returns false for all Website platforms', function () {
                $websitePlatforms = [
                    RuntimePlatform::WebsiteWindows,
                    RuntimePlatform::WebsiteMacOS,
                    RuntimePlatform::WebsiteAndroid,
                    RuntimePlatform::WebsiteIos,
                ];

                foreach ($websitePlatforms as $platform) {
                    $result = $this->registry->isAvailable('camera', $platform);

                    expect($result)->toBeFalse();
                }
            });
        });

        describe('desktop_notifications feature', function () {
            test('returns true for DesktopAppWindows', function () {
                $result = $this->registry->isAvailable('desktop_notifications', RuntimePlatform::DesktopAppWindows);

                expect($result)->toBeTrue();
            });

            test('returns true for DesktopAppMacOS', function () {
                $result = $this->registry->isAvailable('desktop_notifications', RuntimePlatform::DesktopAppMacOS);

                expect($result)->toBeTrue();
            });

            test('returns false for all other platforms', function () {
                $otherPlatforms = [
                    RuntimePlatform::WebsiteWindows,
                    RuntimePlatform::WebsiteMacOS,
                    RuntimePlatform::WebsiteAndroid,
                    RuntimePlatform::WebsiteIos,
                    RuntimePlatform::MobileAppAndroid,
                    RuntimePlatform::MobileAppIos,
                ];

                foreach ($otherPlatforms as $platform) {
                    $result = $this->registry->isAvailable('desktop_notifications', $platform);

                    expect($result)->toBeFalse();
                }
            });
        });

        describe('push_notifications feature', function () {
            test('returns true for MobileAppAndroid', function () {
                $result = $this->registry->isAvailable('push_notifications', RuntimePlatform::MobileAppAndroid);

                expect($result)->toBeTrue();
            });

            test('returns true for MobileAppIos', function () {
                $result = $this->registry->isAvailable('push_notifications', RuntimePlatform::MobileAppIos);

                expect($result)->toBeTrue();
            });

            test('returns false for all other platforms', function () {
                $otherPlatforms = [
                    RuntimePlatform::WebsiteWindows,
                    RuntimePlatform::WebsiteMacOS,
                    RuntimePlatform::WebsiteAndroid,
                    RuntimePlatform::WebsiteIos,
                    RuntimePlatform::DesktopAppWindows,
                    RuntimePlatform::DesktopAppMacOS,
                ];

                foreach ($otherPlatforms as $platform) {
                    $result = $this->registry->isAvailable('push_notifications', $platform);

                    expect($result)->toBeFalse();
                }
            });
        });

        describe('file_system feature', function () {
            test('returns true for all native platforms', function () {
                $nativePlatforms = [
                    RuntimePlatform::MobileAppAndroid,
                    RuntimePlatform::MobileAppIos,
                    RuntimePlatform::DesktopAppWindows,
                    RuntimePlatform::DesktopAppMacOS,
                ];

                foreach ($nativePlatforms as $platform) {
                    $result = $this->registry->isAvailable('file_system', $platform);

                    expect($result)->toBeTrue();
                }
            });

            test('returns false for all website platforms', function () {
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
        });

        describe('webrtc feature', function () {
            test('returns true for all website platforms', function () {
                $websitePlatforms = [
                    RuntimePlatform::WebsiteWindows,
                    RuntimePlatform::WebsiteMacOS,
                    RuntimePlatform::WebsiteAndroid,
                    RuntimePlatform::WebsiteIos,
                ];

                foreach ($websitePlatforms as $platform) {
                    $result = $this->registry->isAvailable('webrtc', $platform);

                    expect($result)->toBeTrue();
                }
            });

            test('returns false for all native platforms', function () {
                $nativePlatforms = [
                    RuntimePlatform::MobileAppAndroid,
                    RuntimePlatform::MobileAppIos,
                    RuntimePlatform::DesktopAppWindows,
                    RuntimePlatform::DesktopAppMacOS,
                ];

                foreach ($nativePlatforms as $platform) {
                    $result = $this->registry->isAvailable('webrtc', $platform);

                    expect($result)->toBeFalse();
                }
            });
        });

        describe('auto_updates feature', function () {
            test('returns true for DesktopAppWindows', function () {
                $result = $this->registry->isAvailable('auto_updates', RuntimePlatform::DesktopAppWindows);

                expect($result)->toBeTrue();
            });

            test('returns true for DesktopAppMacOS', function () {
                $result = $this->registry->isAvailable('auto_updates', RuntimePlatform::DesktopAppMacOS);

                expect($result)->toBeTrue();
            });

            test('returns false for all other platforms', function () {
                $otherPlatforms = [
                    RuntimePlatform::WebsiteWindows,
                    RuntimePlatform::WebsiteMacOS,
                    RuntimePlatform::WebsiteAndroid,
                    RuntimePlatform::WebsiteIos,
                    RuntimePlatform::MobileAppAndroid,
                    RuntimePlatform::MobileAppIos,
                ];

                foreach ($otherPlatforms as $platform) {
                    $result = $this->registry->isAvailable('auto_updates', $platform);

                    expect($result)->toBeFalse();
                }
            });
        });

        describe('app_badge feature', function () {
            test('returns true for MobileAppAndroid', function () {
                $result = $this->registry->isAvailable('app_badge', RuntimePlatform::MobileAppAndroid);

                expect($result)->toBeTrue();
            });

            test('returns true for MobileAppIos', function () {
                $result = $this->registry->isAvailable('app_badge', RuntimePlatform::MobileAppIos);

                expect($result)->toBeTrue();
            });

            test('returns false for all other platforms', function () {
                $otherPlatforms = [
                    RuntimePlatform::WebsiteWindows,
                    RuntimePlatform::WebsiteMacOS,
                    RuntimePlatform::WebsiteAndroid,
                    RuntimePlatform::WebsiteIos,
                    RuntimePlatform::DesktopAppWindows,
                    RuntimePlatform::DesktopAppMacOS,
                ];

                foreach ($otherPlatforms as $platform) {
                    $result = $this->registry->isAvailable('app_badge', $platform);

                    expect($result)->toBeFalse();
                }
            });
        });

        describe('unknown feature', function () {
            test('returns false for unknown feature on any platform', function () {
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
                    $result = $this->registry->isAvailable('nonexistent_feature', $platform);

                    expect($result)->toBeFalse();
                }
            });

            test('returns false for empty string feature name', function () {
                $result = $this->registry->isAvailable('', RuntimePlatform::WebsiteWindows);

                expect($result)->toBeFalse();
            });
        });
    });

    describe('getAvailableFeatures()', function () {
        test('returns correct features for MobileAppAndroid', function () {
            $features = $this->registry->getAvailableFeatures(RuntimePlatform::MobileAppAndroid);

            expect($features)->toBeArray();
            expect($features)->toContain('camera');
            expect($features)->toContain('push_notifications');
            expect($features)->toContain('file_system');
            expect($features)->toContain('app_badge');
            expect($features)->not->toContain('desktop_notifications');
            expect($features)->not->toContain('webrtc');
            expect($features)->not->toContain('auto_updates');
        });

        test('returns correct features for MobileAppIos', function () {
            $features = $this->registry->getAvailableFeatures(RuntimePlatform::MobileAppIos);

            expect($features)->toBeArray();
            expect($features)->toContain('camera');
            expect($features)->toContain('push_notifications');
            expect($features)->toContain('file_system');
            expect($features)->toContain('app_badge');
            expect($features)->not->toContain('desktop_notifications');
            expect($features)->not->toContain('webrtc');
            expect($features)->not->toContain('auto_updates');
        });

        test('returns correct features for DesktopAppWindows', function () {
            $features = $this->registry->getAvailableFeatures(RuntimePlatform::DesktopAppWindows);

            expect($features)->toBeArray();
            expect($features)->toContain('camera');
            expect($features)->toContain('desktop_notifications');
            expect($features)->toContain('file_system');
            expect($features)->toContain('auto_updates');
            expect($features)->not->toContain('push_notifications');
            expect($features)->not->toContain('webrtc');
            expect($features)->not->toContain('app_badge');
        });

        test('returns correct features for DesktopAppMacOS', function () {
            $features = $this->registry->getAvailableFeatures(RuntimePlatform::DesktopAppMacOS);

            expect($features)->toBeArray();
            expect($features)->toContain('camera');
            expect($features)->toContain('desktop_notifications');
            expect($features)->toContain('file_system');
            expect($features)->toContain('auto_updates');
            expect($features)->not->toContain('push_notifications');
            expect($features)->not->toContain('webrtc');
            expect($features)->not->toContain('app_badge');
        });

        test('returns correct features for WebsiteWindows', function () {
            $features = $this->registry->getAvailableFeatures(RuntimePlatform::WebsiteWindows);

            expect($features)->toBeArray();
            expect($features)->toContain('webrtc');
            expect($features)->not->toContain('camera');
            expect($features)->not->toContain('desktop_notifications');
            expect($features)->not->toContain('push_notifications');
            expect($features)->not->toContain('file_system');
            expect($features)->not->toContain('auto_updates');
            expect($features)->not->toContain('app_badge');
        });

        test('returns correct features for WebsiteMacOS', function () {
            $features = $this->registry->getAvailableFeatures(RuntimePlatform::WebsiteMacOS);

            expect($features)->toBeArray();
            expect($features)->toContain('webrtc');
            expect($features)->not->toContain('camera');
        });

        test('returns correct features for WebsiteAndroid', function () {
            $features = $this->registry->getAvailableFeatures(RuntimePlatform::WebsiteAndroid);

            expect($features)->toBeArray();
            expect($features)->toContain('webrtc');
            expect($features)->not->toContain('camera');
        });

        test('returns correct features for WebsiteIos', function () {
            $features = $this->registry->getAvailableFeatures(RuntimePlatform::WebsiteIos);

            expect($features)->toBeArray();
            expect($features)->toContain('webrtc');
            expect($features)->not->toContain('camera');
        });

        test('returns array of strings', function () {
            $features = $this->registry->getAvailableFeatures(RuntimePlatform::MobileAppAndroid);

            expect($features)->toBeArray();
            foreach ($features as $feature) {
                expect($feature)->toBeString();
            }
        });

        test('returns unique feature names', function () {
            $features = $this->registry->getAvailableFeatures(RuntimePlatform::DesktopAppWindows);

            $uniqueFeatures = array_unique($features);
            expect(count($features))->toBe(count($uniqueFeatures));
        });
    });

    describe('getPlatformsForFeature()', function () {
        test('returns correct platforms for camera feature', function () {
            $platforms = $this->registry->getPlatformsForFeature('camera');

            expect($platforms)->toBeArray();
            expect($platforms)->toContain(RuntimePlatform::MobileAppAndroid);
            expect($platforms)->toContain(RuntimePlatform::MobileAppIos);
            expect($platforms)->toContain(RuntimePlatform::DesktopAppWindows);
            expect($platforms)->toContain(RuntimePlatform::DesktopAppMacOS);
            expect(count($platforms))->toBe(4);
        });

        test('returns correct platforms for desktop_notifications feature', function () {
            $platforms = $this->registry->getPlatformsForFeature('desktop_notifications');

            expect($platforms)->toBeArray();
            expect($platforms)->toContain(RuntimePlatform::DesktopAppWindows);
            expect($platforms)->toContain(RuntimePlatform::DesktopAppMacOS);
            expect(count($platforms))->toBe(2);
        });

        test('returns correct platforms for push_notifications feature', function () {
            $platforms = $this->registry->getPlatformsForFeature('push_notifications');

            expect($platforms)->toBeArray();
            expect($platforms)->toContain(RuntimePlatform::MobileAppAndroid);
            expect($platforms)->toContain(RuntimePlatform::MobileAppIos);
            expect(count($platforms))->toBe(2);
        });

        test('returns correct platforms for file_system feature', function () {
            $platforms = $this->registry->getPlatformsForFeature('file_system');

            expect($platforms)->toBeArray();
            expect($platforms)->toContain(RuntimePlatform::MobileAppAndroid);
            expect($platforms)->toContain(RuntimePlatform::MobileAppIos);
            expect($platforms)->toContain(RuntimePlatform::DesktopAppWindows);
            expect($platforms)->toContain(RuntimePlatform::DesktopAppMacOS);
            expect(count($platforms))->toBe(4);
        });

        test('returns correct platforms for webrtc feature', function () {
            $platforms = $this->registry->getPlatformsForFeature('webrtc');

            expect($platforms)->toBeArray();
            expect($platforms)->toContain(RuntimePlatform::WebsiteWindows);
            expect($platforms)->toContain(RuntimePlatform::WebsiteMacOS);
            expect($platforms)->toContain(RuntimePlatform::WebsiteAndroid);
            expect($platforms)->toContain(RuntimePlatform::WebsiteIos);
            expect(count($platforms))->toBe(4);
        });

        test('returns correct platforms for auto_updates feature', function () {
            $platforms = $this->registry->getPlatformsForFeature('auto_updates');

            expect($platforms)->toBeArray();
            expect($platforms)->toContain(RuntimePlatform::DesktopAppWindows);
            expect($platforms)->toContain(RuntimePlatform::DesktopAppMacOS);
            expect(count($platforms))->toBe(2);
        });

        test('returns correct platforms for app_badge feature', function () {
            $platforms = $this->registry->getPlatformsForFeature('app_badge');

            expect($platforms)->toBeArray();
            expect($platforms)->toContain(RuntimePlatform::MobileAppAndroid);
            expect($platforms)->toContain(RuntimePlatform::MobileAppIos);
            expect(count($platforms))->toBe(2);
        });

        test('returns empty array for unknown feature', function () {
            $platforms = $this->registry->getPlatformsForFeature('nonexistent_feature');

            expect($platforms)->toBeArray();
            expect($platforms)->toBeEmpty();
        });

        test('returns empty array for empty string feature name', function () {
            $platforms = $this->registry->getPlatformsForFeature('');

            expect($platforms)->toBeArray();
            expect($platforms)->toBeEmpty();
        });
    });

    describe('property-based tests', function () {
        describe('Property 7: File System Availability Matches Platform Mode', function () {
            /**
             * **Validates: Requirements 5.8**
             *
             * For all RuntimePlatform enum cases, file system access feature availability
             * SHALL return true if and only if the platform is in Mobile Native Mode or
             * Desktop App Mode (MobileAppAndroid, MobileAppIos, DesktopAppWindows, DesktopAppMacOS).
             */
            test('file system is available only for native platforms', function () {
                $nativePlatforms = [
                    RuntimePlatform::MobileAppAndroid,
                    RuntimePlatform::MobileAppIos,
                    RuntimePlatform::DesktopAppWindows,
                    RuntimePlatform::DesktopAppMacOS,
                ];

                $websitePlatforms = [
                    RuntimePlatform::WebsiteWindows,
                    RuntimePlatform::WebsiteMacOS,
                    RuntimePlatform::WebsiteAndroid,
                    RuntimePlatform::WebsiteIos,
                ];

                // Property: file_system is available if and only if platform is native
                foreach ($nativePlatforms as $platform) {
                    $isAvailable = $this->registry->isAvailable('file_system', $platform);
                    $isNative = $platform->isMobileApp() || $platform->isDesktopApp();

                    expect($isAvailable)->toBe($isNative);
                    expect($isAvailable)->toBeTrue();
                }

                foreach ($websitePlatforms as $platform) {
                    $isAvailable = $this->registry->isAvailable('file_system', $platform);
                    $isNative = $platform->isMobileApp() || $platform->isDesktopApp();

                    expect($isAvailable)->toBe($isNative);
                    expect($isAvailable)->toBeFalse();
                }
            });

            test('file system availability matches isMobileApp or isDesktopApp', function () {
                // Property: file_system available ⟺ (isMobileApp() OR isDesktopApp())
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
                    $fileSystemAvailable = $this->registry->isAvailable('file_system', $platform);
                    $isNativePlatform = $platform->isMobileApp() || $platform->isDesktopApp();

                    // The biconditional: available if and only if native
                    expect($fileSystemAvailable)->toBe($isNativePlatform);

                    // Verify consistency
                    if ($isNativePlatform) {
                        expect($fileSystemAvailable)->toBeTrue();
                    } else {
                        expect($fileSystemAvailable)->toBeFalse();
                    }
                }
            });
        });

        describe('Feature Registry Consistency', function () {
            test('isAvailable and getAvailableFeatures are consistent', function () {
                // Property: feature in getAvailableFeatures(platform) ⟺ isAvailable(feature, platform)
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

                $allFeatures = [
                    'camera',
                    'desktop_notifications',
                    'push_notifications',
                    'file_system',
                    'webrtc',
                    'auto_updates',
                    'app_badge',
                ];

                foreach ($allPlatforms as $platform) {
                    $availableFeatures = $this->registry->getAvailableFeatures($platform);

                    foreach ($allFeatures as $feature) {
                        $isAvailable = $this->registry->isAvailable($feature, $platform);
                        $inAvailableList = in_array($feature, $availableFeatures, true);

                        // Consistency check
                        expect($isAvailable)->toBe($inAvailableList);
                    }
                }
            });

            test('isAvailable and getPlatformsForFeature are consistent', function () {
                // Property: platform in getPlatformsForFeature(feature) ⟺ isAvailable(feature, platform)
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

                $allFeatures = [
                    'camera',
                    'desktop_notifications',
                    'push_notifications',
                    'file_system',
                    'webrtc',
                    'auto_updates',
                    'app_badge',
                ];

                foreach ($allFeatures as $feature) {
                    $platformsForFeature = $this->registry->getPlatformsForFeature($feature);

                    foreach ($allPlatforms as $platform) {
                        $isAvailable = $this->registry->isAvailable($feature, $platform);
                        $inPlatformList = in_array($platform, $platformsForFeature, true);

                        // Consistency check
                        expect($isAvailable)->toBe($inPlatformList);
                    }
                }
            });

            test('all platforms returned by getPlatformsForFeature are valid RuntimePlatform cases', function () {
                // Property: ∀feature. ∀platform ∈ getPlatformsForFeature(feature). platform is valid RuntimePlatform
                $allFeatures = [
                    'camera',
                    'desktop_notifications',
                    'push_notifications',
                    'file_system',
                    'webrtc',
                    'auto_updates',
                    'app_badge',
                ];

                $validPlatforms = [
                    RuntimePlatform::WebsiteWindows,
                    RuntimePlatform::WebsiteMacOS,
                    RuntimePlatform::WebsiteAndroid,
                    RuntimePlatform::WebsiteIos,
                    RuntimePlatform::DesktopAppWindows,
                    RuntimePlatform::DesktopAppMacOS,
                    RuntimePlatform::MobileAppAndroid,
                    RuntimePlatform::MobileAppIos,
                ];

                foreach ($allFeatures as $feature) {
                    $platforms = $this->registry->getPlatformsForFeature($feature);

                    foreach ($platforms as $platform) {
                        expect($platform)->toBeInstanceOf(RuntimePlatform::class);
                        expect(in_array($platform, $validPlatforms, true))->toBeTrue();
                    }
                }
            });

            test('getAvailableFeatures returns only known features', function () {
                // Property: ∀platform. ∀feature ∈ getAvailableFeatures(platform). feature is in known feature list
                $knownFeatures = [
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
                    $features = $this->registry->getAvailableFeatures($platform);

                    foreach ($features as $feature) {
                        expect(in_array($feature, $knownFeatures, true))->toBeTrue();
                    }
                }
            });
        });
    });
});
