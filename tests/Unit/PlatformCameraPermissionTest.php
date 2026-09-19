<?php

use App\Enums\RuntimePlatform\RuntimePlatform;
use App\Http\Controllers\PlatformCameraController\PlatformCameraController;

/**
 * Property 8: Permission Denial Messages Are Platform-Aware
 *
 * **Validates: Requirements 6.5**
 *
 * For any RuntimePlatform case, when camera access permission is denied,
 * the Application SHALL display a non-empty error message appropriate for
 * that platform's permission model.
 *
 * Expected messages per platform category:
 *  - Website  → "Camera access denied. Please allow camera access in your browser settings."
 *  - Mobile   → "Camera permission required. Please enable camera access in Settings > Privacy."
 *  - Desktop  → "Camera access denied. Please grant camera permission to this application."
 */
describe('Property 8: Permission Denial Messages Are Platform-Aware', function () {

    // ── Expected messages per category ───────────────────────────────────────

    $webMessage = 'Camera access denied. Please allow camera access in your browser settings.';
    $mobileMessage = 'Camera permission required. Please enable camera access in Settings > Privacy.';
    $desktopMessage = 'Camera access denied. Please grant camera permission to this application.';

    // ── Universal non-empty property ─────────────────────────────────────────

    /**
     * For ALL RuntimePlatform enum cases the permission-denial message
     * must be a non-empty string. This is the primary property from the spec.
     */
    describe('for every RuntimePlatform case the message is non-empty', function () {
        $allCases = RuntimePlatform::cases();

        foreach ($allCases as $platform) {
            test("message is non-empty for {$platform->value}", function () use ($platform) {
                $message = PlatformCameraController::getPlatformPermissionDeniedMessage($platform);

                expect($message)->toBeString();
                expect(strlen(trim($message)))->toBeGreaterThan(0);
            });
        }
    });

    // ── Platform-appropriate message property ─────────────────────────────────

    /**
     * Messages must differ across the three platform categories — ensuring
     * they are genuinely platform-appropriate and not a single generic string.
     */
    test('messages are distinct across Web, Mobile, and Desktop categories', function () use ($webMessage, $mobileMessage, $desktopMessage) {
        expect($webMessage)->not->toBe($mobileMessage);
        expect($webMessage)->not->toBe($desktopMessage);
        expect($mobileMessage)->not->toBe($desktopMessage);
    });

    // ── Website platform cases ────────────────────────────────────────────────

    describe('website platforms return browser-appropriate message', function () use ($webMessage) {
        $websiteCases = array_filter(
            RuntimePlatform::cases(),
            fn (RuntimePlatform $p) => $p->isWebsite()
        );

        foreach ($websiteCases as $platform) {
            test("returns browser message for {$platform->value}", function () use ($platform, $webMessage) {
                $message = PlatformCameraController::getPlatformPermissionDeniedMessage($platform);

                expect($message)->toBe($webMessage);
            });
        }
    });

    // ── Mobile platform cases ─────────────────────────────────────────────────

    describe('mobile app platforms return Settings > Privacy message', function () use ($mobileMessage) {
        $mobileCases = array_filter(
            RuntimePlatform::cases(),
            fn (RuntimePlatform $p) => $p->isMobileApp()
        );

        foreach ($mobileCases as $platform) {
            test("returns mobile message for {$platform->value}", function () use ($platform, $mobileMessage) {
                $message = PlatformCameraController::getPlatformPermissionDeniedMessage($platform);

                expect($message)->toBe($mobileMessage);
            });
        }
    });

    // ── Desktop platform cases ────────────────────────────────────────────────

    describe('desktop app platforms return application-permission message', function () use ($desktopMessage) {
        $desktopCases = array_filter(
            RuntimePlatform::cases(),
            fn (RuntimePlatform $p) => $p->isDesktopApp()
        );

        foreach ($desktopCases as $platform) {
            test("returns desktop message for {$platform->value}", function () use ($platform, $desktopMessage) {
                $message = PlatformCameraController::getPlatformPermissionDeniedMessage($platform);

                expect($message)->toBe($desktopMessage);
            });
        }
    });

    // ── Exhaustive coverage check ─────────────────────────────────────────────

    /**
     * Every RuntimePlatform case must fall into exactly one of the three
     * message categories. This guards against future enum additions being
     * silently unclassified.
     */
    test('every RuntimePlatform case maps to exactly one message category', function () use ($webMessage, $mobileMessage, $desktopMessage) {
        $knownMessages = [$webMessage, $mobileMessage, $desktopMessage];

        foreach (RuntimePlatform::cases() as $platform) {
            $message = PlatformCameraController::getPlatformPermissionDeniedMessage($platform);

            expect(in_array($message, $knownMessages, true))->toBeTrue(
                "Platform {$platform->value} produced an unexpected message: \"{$message}\""
            );
        }
    });

    /**
     * The set of cases must be fully covered:
     * websiteCases + mobileCases + desktopCases === RuntimePlatform::cases()
     */
    test('all RuntimePlatform cases are covered by exactly one category method', function () {
        $allCases = RuntimePlatform::cases();

        $websiteCases = array_filter($allCases, fn ($p) => $p->isWebsite());
        $mobileCases = array_filter($allCases, fn ($p) => $p->isMobileApp());
        $desktopCases = array_filter($allCases, fn ($p) => $p->isDesktopApp());

        $coveredCount = count($websiteCases) + count($mobileCases) + count($desktopCases);

        expect($coveredCount)->toBe(count($allCases),
            'Every RuntimePlatform case should belong to exactly one category (website/mobile/desktop)'
        );
    });
});
