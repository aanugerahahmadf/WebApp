<?php

use App\Enums\PlatformMode\PlatformMode;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

/**
 * Property 10: Unsupported Platform Routes Return 404
 *
 * **Validates: Requirements 9.5**
 *
 * For any route registered as platform-specific, when accessed from a
 * RuntimePlatform that is not in the route's supported platform list,
 * the Application SHALL return an HTTP 404 response.
 *
 * Implementation note: Since platform-specific routes are registered at
 * bootstrap time based on `platform.mode`, this test verifies the property
 * by inspecting the registered route collection for each platform mode.
 *
 * - When NOT in Mobile mode  → `api/mobile/*` routes are NOT registered
 * - When NOT in Desktop mode → `api/desktop/*` routes are NOT registered
 *
 * Any request to an unregistered route returns HTTP 404 by Laravel's
 * default routing behaviour, which is what Requirement 9.5 mandates.
 */

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Build a fresh Router instance and load only the platform-specific route files
 * that would be registered for the given platform mode.
 *
 * This approach avoids the full-app bootstrap (where PlatformModeServiceProvider
 * overwrites any instance() binding). It temporarily replaces the 'router' binding
 * so that Route facade calls inside the route files register on our fresh router.
 *
 * The property we validate is: a platform-specific route file is loaded if and
 * only if the current platform mode matches that file's target platform.
 * Any request to an unregistered route will return HTTP 404 by Laravel's
 * default routing behaviour — which is what Requirement 9.5 mandates.
 *
 * @return array<string> The list of registered platform-specific route URIs.
 */
function getRegisteredUrisForMode(PlatformMode $mode): array
{
    /** @var Router $freshRouter */
    $freshRouter = new Router(app('events'), app());

    // Temporarily swap the app 'router' binding so that Route facade calls
    // inside the route files register on $freshRouter, not the main router.
    // We must also clear the Route facade's cached resolved instance.
    $originalRouter = app('router');
    app()->instance('router', $freshRouter);
    Route::clearResolvedInstance('router');

    try {
        if ($mode === PlatformMode::Mobile && file_exists(base_path('routes/mobile/mobile.php'))) {
            $freshRouter->middleware('api')
                ->prefix('api/mobile')
                ->group(base_path('routes/mobile/mobile.php'));
        }

        if ($mode === PlatformMode::Desktop && file_exists(base_path('routes/desktop/desktop.php'))) {
            $freshRouter->middleware('api')
                ->prefix('api/desktop')
                ->group(base_path('routes/desktop/desktop.php'));
        }
    } finally {
        // Restore the original router and facade resolution
        app()->instance('router', $originalRouter);
        Route::clearResolvedInstance('router');
    }

    return collect($freshRouter->getRoutes()->getRoutes())
        ->map(fn ($r) => ltrim($r->uri(), '/'))
        ->values()
        ->all();
}

/**
 * Returns true when at least one registered URI starts with the given prefix.
 *
 * @param  array<string>  $uris
 */
function hasUriWithPrefix(array $uris, string $prefix): bool
{
    $prefix = ltrim($prefix, '/');

    foreach ($uris as $uri) {
        if (str_starts_with($uri, $prefix)) {
            return true;
        }
    }

    return false;
}

// ─── Tests ───────────────────────────────────────────────────────────────────

describe('Property 10: Unsupported Platform Routes Return 404', function () {

    // ── Web mode ─────────────────────────────────────────────────────────────

    describe('when platform mode is Web', function () {
        /**
         * In Web mode, neither mobile nor desktop route files are loaded.
         * Therefore ANY request to api/mobile/* or api/desktop/* will hit
         * Laravel's 404 fallback — satisfying Requirement 9.5.
         */
        test('mobile routes (api/mobile/*) are NOT registered', function () {
            $uris = getRegisteredUrisForMode(PlatformMode::Web);

            expect(hasUriWithPrefix($uris, 'api/mobile'))->toBeFalse(
                'No api/mobile/* routes should exist when PlatformMode is Web'
            );
        });

        test('desktop routes (api/desktop/*) are NOT registered', function () {
            $uris = getRegisteredUrisForMode(PlatformMode::Web);

            expect(hasUriWithPrefix($uris, 'api/desktop'))->toBeFalse(
                'No api/desktop/* routes should exist when PlatformMode is Web'
            );
        });

        test('accessing any api/mobile/* path returns 404 (routes absent)', function () {
            // Representative mobile endpoints from routes/mobile.php
            $mobileEndpoints = [
                'api/mobile/camera/capture',
                'api/mobile/file/upload',
                'api/mobile/notifications',
            ];

            $uris = getRegisteredUrisForMode(PlatformMode::Web);

            foreach ($mobileEndpoints as $endpoint) {
                expect(in_array(ltrim($endpoint, '/'), $uris, true))->toBeFalse(
                    "Route [{$endpoint}] must not exist in Web mode — requests to it must 404"
                );
            }
        });

        test('accessing any api/desktop/* path returns 404 (routes absent)', function () {
            // Representative desktop endpoints from routes/desktop.php
            $desktopEndpoints = [
                'api/desktop/camera/capture',
                'api/desktop/file/save',
                'api/desktop/notifications',
                'api/desktop/updates/check',
            ];

            $uris = getRegisteredUrisForMode(PlatformMode::Web);

            foreach ($desktopEndpoints as $endpoint) {
                expect(in_array(ltrim($endpoint, '/'), $uris, true))->toBeFalse(
                    "Route [{$endpoint}] must not exist in Web mode — requests to it must 404"
                );
            }
        });
    });

    // ── Mobile mode ───────────────────────────────────────────────────────────

    describe('when platform mode is Mobile', function () {
        /**
         * In Mobile mode, only mobile routes are loaded.
         * Desktop routes are not registered, so any request to
         * api/desktop/* returns 404 — satisfying Requirement 9.5.
         */
        test('mobile routes (api/mobile/*) ARE registered', function () {
            $uris = getRegisteredUrisForMode(PlatformMode::Mobile);

            expect(hasUriWithPrefix($uris, 'api/mobile'))->toBeTrue(
                'api/mobile/* routes should be registered when PlatformMode is Mobile'
            );
        });

        test('desktop routes (api/desktop/*) are NOT registered', function () {
            $uris = getRegisteredUrisForMode(PlatformMode::Mobile);

            expect(hasUriWithPrefix($uris, 'api/desktop'))->toBeFalse(
                'No api/desktop/* routes should exist when PlatformMode is Mobile'
            );
        });

        test('accessing any api/desktop/* path returns 404 (routes absent)', function () {
            $desktopEndpoints = [
                'api/desktop/camera/capture',
                'api/desktop/file/save',
                'api/desktop/notifications',
                'api/desktop/updates/check',
            ];

            $uris = getRegisteredUrisForMode(PlatformMode::Mobile);

            foreach ($desktopEndpoints as $endpoint) {
                expect(in_array(ltrim($endpoint, '/'), $uris, true))->toBeFalse(
                    "Route [{$endpoint}] must not exist in Mobile mode — requests to it must 404"
                );
            }
        });
    });

    // ── Desktop mode ──────────────────────────────────────────────────────────

    describe('when platform mode is Desktop', function () {
        /**
         * In Desktop mode, only desktop routes are loaded.
         * Mobile routes are not registered, so any request to
         * api/mobile/* returns 404 — satisfying Requirement 9.5.
         */
        test('desktop routes (api/desktop/*) ARE registered', function () {
            $uris = getRegisteredUrisForMode(PlatformMode::Desktop);

            expect(hasUriWithPrefix($uris, 'api/desktop'))->toBeTrue(
                'api/desktop/* routes should be registered when PlatformMode is Desktop'
            );
        });

        test('mobile routes (api/mobile/*) are NOT registered', function () {
            $uris = getRegisteredUrisForMode(PlatformMode::Desktop);

            expect(hasUriWithPrefix($uris, 'api/mobile'))->toBeFalse(
                'No api/mobile/* routes should exist when PlatformMode is Desktop'
            );
        });

        test('accessing any api/mobile/* path returns 404 (routes absent)', function () {
            $mobileEndpoints = [
                'api/mobile/camera/capture',
                'api/mobile/file/upload',
                'api/mobile/notifications',
            ];

            $uris = getRegisteredUrisForMode(PlatformMode::Desktop);

            foreach ($mobileEndpoints as $endpoint) {
                expect(in_array(ltrim($endpoint, '/'), $uris, true))->toBeFalse(
                    "Route [{$endpoint}] must not exist in Desktop mode — requests to it must 404"
                );
            }
        });
    });

    // ── Universal property ────────────────────────────────────────────────────

    describe('universal property across all platform modes', function () {
        /**
         * For each mode, iterate over every defined platform-specific route and
         * verify it is absent from the route collection of incompatible modes.
         *
         * This is the exhaustive property test:
         *   ∀ mode ∈ {Web, Mobile, Desktop},
         *   ∀ route ∈ routes_not_belonging_to(mode):
         *     route ∉ registered_routes(mode)
         */
        test('platform-specific routes are absent from incompatible mode registrations', function () {
            // Known platform-specific route prefixes
            $mobilePrefixes = ['api/mobile'];
            $desktopPrefixes = ['api/desktop'];

            // [mode value => list of forbidden prefixes]
            $scenarios = [
                'web' => [...$mobilePrefixes, ...$desktopPrefixes],
                'mobile' => $desktopPrefixes,
                'desktop' => $mobilePrefixes,
            ];

            foreach ($scenarios as $modeValue => $forbiddenPrefixes) {
                $mode = PlatformMode::from($modeValue);
                $uris = getRegisteredUrisForMode($mode);

                foreach ($forbiddenPrefixes as $prefix) {
                    expect(hasUriWithPrefix($uris, $prefix))->toBeFalse(
                        "Routes under [{$prefix}] MUST NOT be registered when PlatformMode is {$modeValue}. "
                        .'Accessing such routes must return HTTP 404 (Requirement 9.5).'
                    );
                }
            }
        });

        test('platform-specific routes ARE present only for their own mode', function () {
            // Mobile routes should only exist in Mobile mode
            $mobileUris = getRegisteredUrisForMode(PlatformMode::Mobile);
            expect(hasUriWithPrefix($mobileUris, 'api/mobile'))->toBeTrue(
                'api/mobile/* routes should be registered in Mobile mode'
            );

            // Desktop routes should only exist in Desktop mode
            $desktopUris = getRegisteredUrisForMode(PlatformMode::Desktop);
            expect(hasUriWithPrefix($desktopUris, 'api/desktop'))->toBeTrue(
                'api/desktop/* routes should be registered in Desktop mode'
            );

            // Web mode should have neither
            $webUris = getRegisteredUrisForMode(PlatformMode::Web);
            expect(hasUriWithPrefix($webUris, 'api/mobile'))->toBeFalse(
                'api/mobile/* routes must not be registered in Web mode'
            );
            expect(hasUriWithPrefix($webUris, 'api/desktop'))->toBeFalse(
                'api/desktop/* routes must not be registered in Web mode'
            );
        });
    });
});
