<?php

use App\Enums\PlatformMode\PlatformMode;

describe('PlatformMode Enum', function () {
    test('has three cases with correct string values', function () {
        expect(PlatformMode::Web->value)->toBe('web');
        expect(PlatformMode::Mobile->value)->toBe('mobile');
        expect(PlatformMode::Desktop->value)->toBe('desktop');
    });

    test('label() returns correct human-readable labels', function () {
        expect(PlatformMode::Web->label())->toBe('Web Server');
        expect(PlatformMode::Mobile->label())->toBe('Mobile Native');
        expect(PlatformMode::Desktop->label())->toBe('Desktop Application');
    });

    test('environmentFile() returns correct environment file names', function () {
        expect(PlatformMode::Web->environmentFile())->toBe('.env.web');
        expect(PlatformMode::Mobile->environmentFile())->toBe('.env.mobile');
        expect(PlatformMode::Desktop->environmentFile())->toBe('.env.desktop');
    });

    test('assetDirectory() returns correct asset build directories', function () {
        expect(PlatformMode::Web->assetDirectory())->toBe('build/web');
        expect(PlatformMode::Mobile->assetDirectory())->toBe('build/mobile');
        expect(PlatformMode::Desktop->assetDirectory())->toBe('build/desktop');
    });

    test('viteInput() returns correct Vite entry point paths', function () {
        expect(PlatformMode::Web->viteInput())->toBe('resources/js/app-web/app-web.js');
        expect(PlatformMode::Mobile->viteInput())->toBe('resources/js/app-mobile/app-mobile.js');
        expect(PlatformMode::Desktop->viteInput())->toBe('resources/js/app-desktop/app-desktop.js');
    });

    test('allowsCameraAccess() returns true for Mobile and Desktop', function () {
        expect(PlatformMode::Web->allowsCameraAccess())->toBeFalse();
        expect(PlatformMode::Mobile->allowsCameraAccess())->toBeTrue();
        expect(PlatformMode::Desktop->allowsCameraAccess())->toBeTrue();
    });

    test('allowsFileSystemAccess() returns true for Mobile and Desktop', function () {
        expect(PlatformMode::Web->allowsFileSystemAccess())->toBeFalse();
        expect(PlatformMode::Mobile->allowsFileSystemAccess())->toBeTrue();
        expect(PlatformMode::Desktop->allowsFileSystemAccess())->toBeTrue();
    });

    test('all enum cases can be instantiated', function () {
        $cases = PlatformMode::cases();

        expect($cases)->toHaveCount(3);
        expect($cases)->toContain(PlatformMode::Web);
        expect($cases)->toContain(PlatformMode::Mobile);
        expect($cases)->toContain(PlatformMode::Desktop);
    });

    test('from() method can instantiate from string value', function () {
        expect(PlatformMode::from('web'))->toBe(PlatformMode::Web);
        expect(PlatformMode::from('mobile'))->toBe(PlatformMode::Mobile);
        expect(PlatformMode::from('desktop'))->toBe(PlatformMode::Desktop);
    });

    test('tryFrom() returns null for invalid string value', function () {
        expect(PlatformMode::tryFrom('invalid'))->toBeNull();
        expect(PlatformMode::tryFrom('web'))->toBe(PlatformMode::Web);
    });
});
