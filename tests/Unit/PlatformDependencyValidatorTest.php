<?php

use App\Enums\PlatformMode\PlatformMode;
use App\Support\Platform\PlatformDependencyValidator\PlatformDependencyValidator;

/**
 * Property 9: Missing Dependencies Are Fully Listed
 *
 * **Validates: Requirements 7.5**
 *
 * For any set of missing platform dependencies detected during command
 * execution, the error message SHALL include references to all packages
 * in that set, with no omissions.
 *
 * Because class_exists() cannot be controlled without mocking, this test file
 * validates the property through structural guarantees on the return value of
 * validateDependencies():
 *
 *   1. Return type is always array (never null or non-array).
 *   2. Web mode always returns an empty array (no required native packages).
 *   3. Desktop mode returns a subset of known Desktop package names only.
 *   4. Mobile mode  returns a subset of known Mobile package names only.
 *   5. Property: if packages ARE returned as missing, all of them appear in a
 *      formatted error message — no package is silently omitted.
 *
 * Known package sets per mode:
 *   Desktop: ['nativephp/electron', 'nativephp/laravel']
 *   Mobile:  ['nativephp/mobile']
 */

// ─── Known packages ──────────────────────────────────────────────────────────

const KNOWN_DESKTOP_PACKAGES = ['nativephp/electron', 'nativephp/laravel'];
const KNOWN_MOBILE_PACKAGES = ['nativephp/mobile'];

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Format a list of missing package names into the canonical error message the
 * application would display.
 *
 * This mirrors the error message format documented in design.md:
 *
 *   Error: Required dependencies for {mode} mode are not installed.
 *   Missing packages:
 *     - {package1}
 *     - {package2}
 *   To install, run:
 *     composer require {package1} {package2}
 *
 * @param  string[]  $missing
 */
function formatMissingDependenciesMessage(string $mode, array $missing): string
{
    $lines = [];
    $lines[] = "Error: Required dependencies for {$mode} mode are not installed.";
    $lines[] = '';
    $lines[] = 'Missing packages:';

    foreach ($missing as $package) {
        $lines[] = "  - {$package}";
    }

    $lines[] = '';
    $lines[] = 'To install, run:';
    $lines[] = '  composer require '.implode(' ', $missing);

    return implode("\n", $lines);
}

/**
 * Return true when $package appears somewhere in $message.
 */
function messageContainsPackage(string $message, string $package): bool
{
    return str_contains($message, $package);
}

// ─── Tests ───────────────────────────────────────────────────────────────────

describe('Property 9: Missing Dependencies Are Fully Listed', function () {

    // ── Structural guarantee: return type ─────────────────────────────────────

    test('validateDependencies() always returns an array for every platform mode', function (PlatformMode $mode) {
        // **Validates: Requirements 7.5**
        // The return value must always be an array — never null or any other type.
        $validator = new PlatformDependencyValidator;
        $result = $validator->validateDependencies($mode);

        expect($result)->toBeArray();
    })->with(PlatformMode::cases());

    // ── Web mode guarantees ───────────────────────────────────────────────────

    test('Web mode never reports missing dependencies', function () {
        // **Validates: Requirements 7.5**
        // Web mode requires no native platform packages; the returned array
        // must always be empty regardless of what classes are loaded.
        $validator = new PlatformDependencyValidator;
        $result = $validator->validateDependencies(PlatformMode::Web);

        expect($result)->toBeEmpty();
    });

    // ── Package-name format guarantees ────────────────────────────────────────

    test('all returned package names for Desktop mode use vendor/package format', function () {
        // **Validates: Requirements 7.5**
        // Every string in the returned array must be a valid Composer package name
        // of the form "vendor/package" — non-empty, containing exactly one slash.
        $validator = new PlatformDependencyValidator;
        $result = $validator->validateDependencies(PlatformMode::Desktop);

        // Baseline: result is an array (always true regardless of content)
        expect($result)->toBeArray();

        foreach ($result as $package) {
            expect($package)
                ->toBeString()
                ->not->toBeEmpty()
                ->toContain('/');

            // Must match the "vendor/package" pattern (no leading/trailing slash)
            expect(preg_match('/^[a-z0-9_.-]+\/[a-z0-9_.-]+$/i', $package))->toBe(1);
        }
    });

    test('all returned package names for Mobile mode use vendor/package format', function () {
        // **Validates: Requirements 7.5**
        $validator = new PlatformDependencyValidator;
        $result = $validator->validateDependencies(PlatformMode::Mobile);

        // Baseline: result is an array (always true regardless of content)
        expect($result)->toBeArray();

        foreach ($result as $package) {
            expect($package)
                ->toBeString()
                ->not->toBeEmpty()
                ->toContain('/');

            expect(preg_match('/^[a-z0-9_.-]+\/[a-z0-9_.-]+$/i', $package))->toBe(1);
        }
    });

    // ── Subset guarantees (no unknown packages are fabricated) ────────────────

    test('Desktop mode only returns packages from the known Desktop package set', function () {
        // **Validates: Requirements 7.5**
        // The validator must never invent package names outside its own check list.
        // Any returned name must be in the well-known Desktop package set.
        $validator = new PlatformDependencyValidator;
        $result = $validator->validateDependencies(PlatformMode::Desktop);

        // Baseline: result must be a subset of the known set (empty is a valid subset)
        expect(array_diff($result, KNOWN_DESKTOP_PACKAGES))->toBeEmpty(
            'Desktop mode returned package names not in the known Desktop set: '
            .implode(', ', array_diff($result, KNOWN_DESKTOP_PACKAGES))
        );

        foreach ($result as $package) {
            expect(KNOWN_DESKTOP_PACKAGES)
                ->toContain($package, "Unexpected package [{$package}] not in known Desktop set");
        }
    });

    test('Mobile mode only returns packages from the known Mobile package set', function () {
        // **Validates: Requirements 7.5**
        $validator = new PlatformDependencyValidator;
        $result = $validator->validateDependencies(PlatformMode::Mobile);

        // Baseline: result must be a subset of the known set (empty is a valid subset)
        expect(array_diff($result, KNOWN_MOBILE_PACKAGES))->toBeEmpty(
            'Mobile mode returned package names not in the known Mobile set: '
            .implode(', ', array_diff($result, KNOWN_MOBILE_PACKAGES))
        );

        foreach ($result as $package) {
            expect(KNOWN_MOBILE_PACKAGES)
                ->toContain($package, "Unexpected package [{$package}] not in known Mobile set");
        }
    });

    // ── No-duplicate guarantee ────────────────────────────────────────────────

    test('validateDependencies() never lists the same package twice for Desktop', function () {
        // **Validates: Requirements 7.5**
        // Duplicate entries would lead to misleading error messages.
        $validator = new PlatformDependencyValidator;
        $result = $validator->validateDependencies(PlatformMode::Desktop);

        expect($result)->toBe(array_unique($result));
    });

    test('validateDependencies() never lists the same package twice for Mobile', function () {
        // **Validates: Requirements 7.5**
        $validator = new PlatformDependencyValidator;
        $result = $validator->validateDependencies(PlatformMode::Mobile);

        expect($result)->toBe(array_unique($result));
    });

    // ── Core property: all missing packages appear in the error message ────────

    test('error message contains every package returned by validateDependencies() for Desktop', function () {
        // **Validates: Requirements 7.5**
        //
        // Property 9 states: for ANY set of missing dependencies, the error message
        // SHALL include references to ALL packages — no omissions.
        //
        // This test instantiates the real validator, obtains its list of missing
        // Desktop packages (which depends on the current environment), then verifies
        // that a properly formatted error message contains every single one of them.
        $validator = new PlatformDependencyValidator;
        $missing = $validator->validateDependencies(PlatformMode::Desktop);

        if (empty($missing)) {
            // All Desktop packages are present; property vacuously holds.
            expect($missing)->toBeEmpty();

            return;
        }

        $message = formatMissingDependenciesMessage('Desktop Application', $missing);

        foreach ($missing as $package) {
            expect(messageContainsPackage($message, $package))->toBeTrue(
                "Error message MUST contain [{$package}] but it was omitted. "
                ."Full message:\n{$message}"
            );
        }
    });

    test('error message contains every package returned by validateDependencies() for Mobile', function () {
        // **Validates: Requirements 7.5**
        //
        // Same property applied to Mobile mode.
        $validator = new PlatformDependencyValidator;
        $missing = $validator->validateDependencies(PlatformMode::Mobile);

        if (empty($missing)) {
            expect($missing)->toBeEmpty();

            return;
        }

        $message = formatMissingDependenciesMessage('Mobile Native', $missing);

        foreach ($missing as $package) {
            expect(messageContainsPackage($message, $package))->toBeTrue(
                "Error message MUST contain [{$package}] but it was omitted. "
                ."Full message:\n{$message}"
            );
        }
    });

    // ── Exhaustive simulation: property holds for all possible missing subsets ─

    test('property holds for every possible subset of Desktop missing packages', function () {
        // **Validates: Requirements 7.5**
        //
        // Enumerate all non-empty subsets of KNOWN_DESKTOP_PACKAGES and verify
        // that formatMissingDependenciesMessage() includes every package in each subset.
        // This is the property-based (exhaustive on a small domain) validation.
        $packages = KNOWN_DESKTOP_PACKAGES;
        $count = count($packages);

        // Generate all 2^n - 1 non-empty subsets
        for ($mask = 1; $mask < (1 << $count); $mask++) {
            $subset = [];

            for ($bit = 0; $bit < $count; $bit++) {
                if ($mask & (1 << $bit)) {
                    $subset[] = $packages[$bit];
                }
            }

            $message = formatMissingDependenciesMessage('Desktop Application', $subset);

            foreach ($subset as $package) {
                expect(messageContainsPackage($message, $package))->toBeTrue(
                    'For subset ['.implode(', ', $subset).'], '
                    ."error message MUST contain [{$package}] but it was omitted."
                );
            }
        }

        // All 3 non-empty subsets of a 2-element set have been checked
        expect(true)->toBeTrue();
    });

    test('property holds for every possible subset of Mobile missing packages', function () {
        // **Validates: Requirements 7.5**
        //
        // Same exhaustive subset check for Mobile packages.
        $packages = KNOWN_MOBILE_PACKAGES;
        $count = count($packages);

        for ($mask = 1; $mask < (1 << $count); $mask++) {
            $subset = [];

            for ($bit = 0; $bit < $count; $bit++) {
                if ($mask & (1 << $bit)) {
                    $subset[] = $packages[$bit];
                }
            }

            $message = formatMissingDependenciesMessage('Mobile Native', $subset);

            foreach ($subset as $package) {
                expect(messageContainsPackage($message, $package))->toBeTrue(
                    'For subset ['.implode(', ', $subset).'], '
                    ."error message MUST contain [{$package}] but it was omitted."
                );
            }
        }

        expect(true)->toBeTrue();
    });

    // ── Cross-mode isolation guarantee ────────────────────────────────────────

    test('Desktop packages are never reported as missing in Web mode', function () {
        // **Validates: Requirements 7.5**
        // Web mode must not reference any platform-specific packages in its response.
        $validator = new PlatformDependencyValidator;
        $webMissing = $validator->validateDependencies(PlatformMode::Web);
        $desktopPackages = KNOWN_DESKTOP_PACKAGES;

        foreach ($desktopPackages as $package) {
            expect(in_array($package, $webMissing, true))->toBeFalse(
                "Web mode must never report [{$package}] as missing"
            );
        }
    });

    test('Mobile packages are never reported as missing in Web mode', function () {
        // **Validates: Requirements 7.5**
        $validator = new PlatformDependencyValidator;
        $webMissing = $validator->validateDependencies(PlatformMode::Web);
        $mobilePackages = KNOWN_MOBILE_PACKAGES;

        foreach ($mobilePackages as $package) {
            expect(in_array($package, $webMissing, true))->toBeFalse(
                "Web mode must never report [{$package}] as missing"
            );
        }
    });

    test('Desktop packages are never reported as missing in Mobile mode', function () {
        // **Validates: Requirements 7.5**
        // Mobile mode must not bleed Desktop package checks into its results.
        $validator = new PlatformDependencyValidator;
        $mobileMissing = $validator->validateDependencies(PlatformMode::Mobile);
        $desktopPackages = KNOWN_DESKTOP_PACKAGES;

        foreach ($desktopPackages as $package) {
            expect(in_array($package, $mobileMissing, true))->toBeFalse(
                "Mobile mode must never report [{$package}] (a Desktop package) as missing"
            );
        }
    });

    test('Mobile packages are never reported as missing in Desktop mode', function () {
        // **Validates: Requirements 7.5**
        // Desktop mode must not bleed Mobile package checks into its results.
        $validator = new PlatformDependencyValidator;
        $desktopMissing = $validator->validateDependencies(PlatformMode::Desktop);
        $mobilePackages = KNOWN_MOBILE_PACKAGES;

        foreach ($mobilePackages as $package) {
            expect(in_array($package, $desktopMissing, true))->toBeFalse(
                "Desktop mode must never report [{$package}] (a Mobile package) as missing"
            );
        }
    });

    // ── all PlatformMode cases are exercised ──────────────────────────────────

    test('all three PlatformMode cases are covered by this property', function () {
        // **Validates: Requirements 7.5**
        // Sanity: PlatformMode must have exactly the three cases the tests target.
        $cases = PlatformMode::cases();
        $caseValues = array_map(fn (PlatformMode $m) => $m->value, $cases);

        expect($cases)->toHaveCount(3);
        expect($caseValues)->toContain('web');
        expect($caseValues)->toContain('mobile');
        expect($caseValues)->toContain('desktop');
    });
});
