<?php

use App\Enums\PlatformMode\PlatformMode;
use App\Enums\RuntimePlatform\RuntimePlatform;
use App\Support\Platform\PlatformFeatureRegistry\PlatformFeatureRegistry;

describe('Platform Helper Functions', function () {

    // -------------------------------------------------------------------------
    // platform_mode()
    // -------------------------------------------------------------------------

    describe('platform_mode()', function () {
        test('returns PlatformMode::Web when binding is Web', function () {
            $this->app->instance('platform.mode', PlatformMode::Web);

            $result = platform_mode();

            expect($result)->toBe(PlatformMode::Web);
        });

        test('returns PlatformMode::Mobile when binding is Mobile', function () {
            $this->app->instance('platform.mode', PlatformMode::Mobile);

            $result = platform_mode();

            expect($result)->toBe(PlatformMode::Mobile);
        });

        test('returns PlatformMode::Desktop when binding is Desktop', function () {
            $this->app->instance('platform.mode', PlatformMode::Desktop);

            $result = platform_mode();

            expect($result)->toBe(PlatformMode::Desktop);
        });

        test('returns a PlatformMode instance', function () {
            $this->app->instance('platform.mode', PlatformMode::Web);

            $result = platform_mode();

            expect($result)->toBeInstanceOf(PlatformMode::class);
        });
    });

    // -------------------------------------------------------------------------
    // runtime_platform()
    // -------------------------------------------------------------------------

    describe('runtime_platform()', function () {
        test('returns WebsiteWindows when binding is WebsiteWindows', function () {
            $this->app->instance('runtime.platform', RuntimePlatform::WebsiteWindows);

            $result = runtime_platform();

            expect($result)->toBe(RuntimePlatform::WebsiteWindows);
        });

        test('returns MobileAppAndroid when binding is MobileAppAndroid', function () {
            $this->app->instance('runtime.platform', RuntimePlatform::MobileAppAndroid);

            $result = runtime_platform();

            expect($result)->toBe(RuntimePlatform::MobileAppAndroid);
        });

        test('returns MobileAppIos when binding is MobileAppIos', function () {
            $this->app->instance('runtime.platform', RuntimePlatform::MobileAppIos);

            $result = runtime_platform();

            expect($result)->toBe(RuntimePlatform::MobileAppIos);
        });

        test('returns DesktopAppWindows when binding is DesktopAppWindows', function () {
            $this->app->instance('runtime.platform', RuntimePlatform::DesktopAppWindows);

            $result = runtime_platform();

            expect($result)->toBe(RuntimePlatform::DesktopAppWindows);
        });

        test('returns DesktopAppMacOS when binding is DesktopAppMacOS', function () {
            $this->app->instance('runtime.platform', RuntimePlatform::DesktopAppMacOS);

            $result = runtime_platform();

            expect($result)->toBe(RuntimePlatform::DesktopAppMacOS);
        });

        test('returns a RuntimePlatform instance', function () {
            $this->app->instance('runtime.platform', RuntimePlatform::WebsiteWindows);

            $result = runtime_platform();

            expect($result)->toBeInstanceOf(RuntimePlatform::class);
        });
    });

    // -------------------------------------------------------------------------
    // is_web_mode()
    // -------------------------------------------------------------------------

    describe('is_web_mode()', function () {
        test('returns true when platform mode is Web', function () {
            $this->app->instance('platform.mode', PlatformMode::Web);

            expect(is_web_mode())->toBeTrue();
        });

        test('returns false when platform mode is Mobile', function () {
            $this->app->instance('platform.mode', PlatformMode::Mobile);

            expect(is_web_mode())->toBeFalse();
        });

        test('returns false when platform mode is Desktop', function () {
            $this->app->instance('platform.mode', PlatformMode::Desktop);

            expect(is_web_mode())->toBeFalse();
        });
    });

    // -------------------------------------------------------------------------
    // is_mobile_mode()
    // -------------------------------------------------------------------------

    describe('is_mobile_mode()', function () {
        test('returns true when platform mode is Mobile', function () {
            $this->app->instance('platform.mode', PlatformMode::Mobile);

            expect(is_mobile_mode())->toBeTrue();
        });

        test('returns false when platform mode is Web', function () {
            $this->app->instance('platform.mode', PlatformMode::Web);

            expect(is_mobile_mode())->toBeFalse();
        });

        test('returns false when platform mode is Desktop', function () {
            $this->app->instance('platform.mode', PlatformMode::Desktop);

            expect(is_mobile_mode())->toBeFalse();
        });
    });

    // -------------------------------------------------------------------------
    // is_desktop_mode()
    // -------------------------------------------------------------------------

    describe('is_desktop_mode()', function () {
        test('returns true when platform mode is Desktop', function () {
            $this->app->instance('platform.mode', PlatformMode::Desktop);

            expect(is_desktop_mode())->toBeTrue();
        });

        test('returns false when platform mode is Web', function () {
            $this->app->instance('platform.mode', PlatformMode::Web);

            expect(is_desktop_mode())->toBeFalse();
        });

        test('returns false when platform mode is Mobile', function () {
            $this->app->instance('platform.mode', PlatformMode::Mobile);

            expect(is_desktop_mode())->toBeFalse();
        });
    });

    // -------------------------------------------------------------------------
    // platform_feature() — delegates to PlatformFeatureRegistry
    // -------------------------------------------------------------------------

    describe('platform_feature()', function () {
        test('returns true for camera on MobileAppAndroid', function () {
            $this->app->instance('runtime.platform', RuntimePlatform::MobileAppAndroid);

            expect(platform_feature('camera'))->toBeTrue();
        });

        test('returns true for camera on MobileAppIos', function () {
            $this->app->instance('runtime.platform', RuntimePlatform::MobileAppIos);

            expect(platform_feature('camera'))->toBeTrue();
        });

        test('returns true for camera on DesktopAppWindows', function () {
            $this->app->instance('runtime.platform', RuntimePlatform::DesktopAppWindows);

            expect(platform_feature('camera'))->toBeTrue();
        });

        test('returns true for camera on DesktopAppMacOS', function () {
            $this->app->instance('runtime.platform', RuntimePlatform::DesktopAppMacOS);

            expect(platform_feature('camera'))->toBeTrue();
        });

        test('returns false for camera on WebsiteWindows', function () {
            $this->app->instance('runtime.platform', RuntimePlatform::WebsiteWindows);

            expect(platform_feature('camera'))->toBeFalse();
        });

        test('returns false for camera on WebsiteMacOS', function () {
            $this->app->instance('runtime.platform', RuntimePlatform::WebsiteMacOS);

            expect(platform_feature('camera'))->toBeFalse();
        });

        test('returns false for camera on WebsiteAndroid', function () {
            $this->app->instance('runtime.platform', RuntimePlatform::WebsiteAndroid);

            expect(platform_feature('camera'))->toBeFalse();
        });

        test('returns false for camera on WebsiteIos', function () {
            $this->app->instance('runtime.platform', RuntimePlatform::WebsiteIos);

            expect(platform_feature('camera'))->toBeFalse();
        });

        test('returns false for unknown feature on any platform', function () {
            $this->app->instance('runtime.platform', RuntimePlatform::MobileAppAndroid);

            expect(platform_feature('nonexistent_feature'))->toBeFalse();
        });

        test('delegates to PlatformFeatureRegistry::isAvailable with the current runtime platform', function () {
            $runtimePlatform = RuntimePlatform::MobileAppAndroid;
            $this->app->instance('runtime.platform', $runtimePlatform);

            // Create a mock registry that records the call
            $mockRegistry = Mockery::mock(PlatformFeatureRegistry::class);
            $mockRegistry->shouldReceive('isAvailable')
                ->once()
                ->with('camera', $runtimePlatform)
                ->andReturn(true);

            $this->app->instance(PlatformFeatureRegistry::class, $mockRegistry);

            $result = platform_feature('camera');

            expect($result)->toBeTrue();
        });

        test('delegates to PlatformFeatureRegistry::isAvailable passing runtime platform, not mode', function () {
            // Even when in Web mode, platform_feature uses runtime platform (not mode)
            $this->app->instance('platform.mode', PlatformMode::Web);
            $this->app->instance('runtime.platform', RuntimePlatform::WebsiteWindows);

            // webrtc is available for web platforms
            expect(platform_feature('webrtc'))->toBeTrue();
        });

        test('returns correct result for push_notifications on mobile', function () {
            $this->app->instance('runtime.platform', RuntimePlatform::MobileAppAndroid);

            expect(platform_feature('push_notifications'))->toBeTrue();
        });

        test('returns false for push_notifications on desktop', function () {
            $this->app->instance('runtime.platform', RuntimePlatform::DesktopAppWindows);

            expect(platform_feature('push_notifications'))->toBeFalse();
        });

        test('returns true for file_system on mobile native', function () {
            $this->app->instance('runtime.platform', RuntimePlatform::MobileAppIos);

            expect(platform_feature('file_system'))->toBeTrue();
        });

        test('returns true for file_system on desktop', function () {
            $this->app->instance('runtime.platform', RuntimePlatform::DesktopAppMacOS);

            expect(platform_feature('file_system'))->toBeTrue();
        });

        test('returns false for file_system on web', function () {
            $this->app->instance('runtime.platform', RuntimePlatform::WebsiteAndroid);

            expect(platform_feature('file_system'))->toBeFalse();
        });
    });

    // -------------------------------------------------------------------------
    // Boolean helpers mutual exclusivity
    // -------------------------------------------------------------------------

    describe('boolean mode helpers are mutually exclusive', function () {
        test('only is_web_mode returns true in Web mode', function () {
            $this->app->instance('platform.mode', PlatformMode::Web);

            expect(is_web_mode())->toBeTrue();
            expect(is_mobile_mode())->toBeFalse();
            expect(is_desktop_mode())->toBeFalse();
        });

        test('only is_mobile_mode returns true in Mobile mode', function () {
            $this->app->instance('platform.mode', PlatformMode::Mobile);

            expect(is_web_mode())->toBeFalse();
            expect(is_mobile_mode())->toBeTrue();
            expect(is_desktop_mode())->toBeFalse();
        });

        test('only is_desktop_mode returns true in Desktop mode', function () {
            $this->app->instance('platform.mode', PlatformMode::Desktop);

            expect(is_web_mode())->toBeFalse();
            expect(is_mobile_mode())->toBeFalse();
            expect(is_desktop_mode())->toBeTrue();
        });
    });
});
