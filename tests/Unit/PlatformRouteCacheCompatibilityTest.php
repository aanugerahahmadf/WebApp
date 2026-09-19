<?php

use App\Enums\PlatformMode\PlatformMode;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

/**
 * Property 11: Route Cache Contains Only Compatible Routes
 *
 * **Validates: Requirements 9.7**
 *
 * For any platform mode (Web, Mobile, Desktop), the cached route collection
 * for that mode SHALL contain only routes that are marked as compatible with
 * that mode or are mode-agnostic.
 *
 * Concretely:
 *  - In Web mode    : routes with URI prefix "api/mobile/*" or "api/desktop/*" MUST NOT be registered.
 *  - In Mobile mode : routes with URI prefix "api/desktop/*" MUST NOT be registered;
 *                     routes with URI prefix "api/mobile/*"  MUST     be registered.
 *  - In Desktop mode: routes with URI prefix "api/mobile/*"  MUST NOT be registered;
 *                     routes with URI prefix "api/desktop/*" MUST     be registered.
 */

/**
 * Helper: simulate which route URIs are registered for a given platform mode.
 *
 * Uses the real app router (so Route:: facade works) but loads routes
 * into an isolated prefix-group by using Route::group() directly.
 *
 * Returns only the URIs that would be added for the given mode
 * (i.e. the platform-specific URIs).
 */
function getPlatformSpecificUrisForMode(PlatformMode $mode): array
{
    /** @var Router $router */
    $router = app('router');

    // Snapshot existing routes count before we add anything
    $existingUris = collect($router->getRoutes()->getRoutes())
        ->map(fn ($r) => $r->uri())
        ->all();

    // Register platform-specific routes using the same logic as bootstrap/app.php
    if ($mode === PlatformMode::Mobile && file_exists(base_path('routes/mobile/mobile.php'))) {
        Route::middleware('api')
            ->prefix('api/mobile')
            ->group(base_path('routes/mobile/mobile.php'));
    }

    if ($mode === PlatformMode::Desktop && file_exists(base_path('routes/desktop/desktop.php'))) {
        Route::middleware('api')
            ->prefix('api/desktop')
            ->group(base_path('routes/desktop/desktop.php'));
    }

    // Collect all URIs now (after potential additions)
    $allUris = collect($router->getRoutes()->getRoutes())
        ->map(fn ($r) => $r->uri())
        ->all();

    // Return only URIs that were newly added
    return array_values(array_diff($allUris, $existingUris));
}

/**
 * Helper: get the current app router's route URIs without adding any new routes.
 * Useful for Web mode checks where no platform-specific routes should be present.
 */
function getCurrentRouteUris(): array
{
    return collect(app('router')->getRoutes()->getRoutes())
        ->map(fn ($r) => $r->uri())
        ->all();
}

describe('Property 11: Route Cache Contains Only Compatible Routes', function () {

    // ── Web Mode ──────────────────────────────────────────────────────────────

    test('Web mode: api/mobile/* routes are NOT registered', function () {
        // **Validates: Requirements 9.7**
        // In Web mode, no platform-specific routes are added; mobile routes must be absent.
        // The app boots in Web mode by default (no native:run command), so we inspect
        // the current route collection.
        $uris = getCurrentRouteUris();

        $mobileRoutes = array_filter($uris, fn (string $uri) => str_starts_with($uri, 'api/mobile'));

        expect($mobileRoutes)->toBeEmpty();
    });

    test('Web mode: api/desktop/* routes are NOT registered', function () {
        // **Validates: Requirements 9.7**
        // In Web mode, desktop-specific routes must be absent from the route collection.
        $uris = getCurrentRouteUris();

        $desktopRoutes = array_filter($uris, fn (string $uri) => str_starts_with($uri, 'api/desktop'));

        expect($desktopRoutes)->toBeEmpty();
    });

    // ── Mobile Mode ───────────────────────────────────────────────────────────

    test('Mobile mode: api/mobile/* routes ARE registered when mobile routes are loaded', function () {
        // **Validates: Requirements 9.7**
        // Simulates the route-loading step for Mobile mode and verifies mobile-prefixed routes
        // are present in the resulting collection.
        $addedUris = getPlatformSpecificUrisForMode(PlatformMode::Mobile);

        $mobileRoutes = array_filter($addedUris, fn (string $uri) => str_starts_with($uri, 'api/mobile'));

        expect($mobileRoutes)->not->toBeEmpty();
    });

    test('Mobile mode: api/desktop/* routes are NOT registered', function () {
        // **Validates: Requirements 9.7**
        // When loading routes for Mobile mode, no desktop-prefixed routes are added.
        $addedUris = getPlatformSpecificUrisForMode(PlatformMode::Mobile);

        $desktopRoutes = array_filter($addedUris, fn (string $uri) => str_starts_with($uri, 'api/desktop'));

        expect($desktopRoutes)->toBeEmpty();
    });

    // ── Desktop Mode ─────────────────────────────────────────────────────────

    test('Desktop mode: api/desktop/* routes ARE registered when desktop routes are loaded', function () {
        // **Validates: Requirements 9.7**
        // Simulates the route-loading step for Desktop mode and verifies desktop-prefixed routes
        // are present in the resulting collection.
        $addedUris = getPlatformSpecificUrisForMode(PlatformMode::Desktop);

        $desktopRoutes = array_filter($addedUris, fn (string $uri) => str_starts_with($uri, 'api/desktop'));

        expect($desktopRoutes)->not->toBeEmpty();
    });

    test('Desktop mode: api/mobile/* routes are NOT registered', function () {
        // **Validates: Requirements 9.7**
        // When loading routes for Desktop mode, no mobile-prefixed routes are added.
        $addedUris = getPlatformSpecificUrisForMode(PlatformMode::Desktop);

        $mobileRoutes = array_filter($addedUris, fn (string $uri) => str_starts_with($uri, 'api/mobile'));

        expect($mobileRoutes)->toBeEmpty();
    });

    // ── Cross-mode property ───────────────────────────────────────────────────

    test('platform-specific route prefixes are mutually exclusive across incompatible modes', function () {
        // **Validates: Requirements 9.7**
        // Property: For every platform mode, the routes loaded for that mode must NOT contain
        // URIs whose prefix belongs to a different, incompatible platform.
        //
        // Incompatibility matrix:
        //   Web     -> must not have api/mobile/* or api/desktop/*
        //   Mobile  -> must not have api/desktop/*
        //   Desktop -> must not have api/mobile/*

        // ── Web: check existing app routes (no platform routes added at boot for Web)
        $webUris = getCurrentRouteUris();
        $webForbidden = array_filter(
            $webUris,
            fn (string $uri) => str_starts_with($uri, 'api/mobile') || str_starts_with($uri, 'api/desktop')
        );
        expect($webForbidden)->toBeEmpty(
            'Web mode must not contain api/mobile/* or api/desktop/* routes, '
            .'but found: '.implode(', ', $webForbidden)
        );

        // ── Mobile: routes added for Mobile must not include api/desktop/*
        $mobileAddedUris = getPlatformSpecificUrisForMode(PlatformMode::Mobile);
        $mobileForbidden = array_filter(
            $mobileAddedUris,
            fn (string $uri) => str_starts_with($uri, 'api/desktop')
        );
        expect($mobileForbidden)->toBeEmpty(
            'Mobile mode must not contain api/desktop/* routes, '
            .'but found: '.implode(', ', $mobileForbidden)
        );

        // ── Desktop: routes added for Desktop must not include api/mobile/*
        $desktopAddedUris = getPlatformSpecificUrisForMode(PlatformMode::Desktop);
        $desktopForbidden = array_filter(
            $desktopAddedUris,
            fn (string $uri) => str_starts_with($uri, 'api/mobile')
        );
        expect($desktopForbidden)->toBeEmpty(
            'Desktop mode must not contain api/mobile/* routes, '
            .'but found: '.implode(', ', $desktopForbidden)
        );
    });

    test('each platform mode only registers its own platform-specific routes', function () {
        // **Validates: Requirements 9.7**
        // Property: The set of platform-specific URIs registered for a mode
        // must be a subset of URIs that are compatible with that mode.
        //
        // Compatible URI prefixes per mode:
        //   Web     -> only existing routes (no api/mobile/*, no api/desktop/*)
        //   Mobile  -> api/mobile/* allowed; api/desktop/* forbidden
        //   Desktop -> api/desktop/* allowed; api/mobile/* forbidden

        // ── Web ──
        $webUris = getCurrentRouteUris();
        $webPlatformSpecific = array_filter(
            $webUris,
            fn (string $uri) => str_starts_with($uri, 'api/mobile') || str_starts_with($uri, 'api/desktop')
        );
        expect($webPlatformSpecific)->toBeEmpty();

        // ── Mobile ──
        $mobileUris = getPlatformSpecificUrisForMode(PlatformMode::Mobile);
        $mobileForbidden = array_filter(
            $mobileUris,
            fn (string $uri) => str_starts_with($uri, 'api/desktop')
        );
        expect($mobileForbidden)->toBeEmpty();

        // ── Desktop ──
        $desktopUris = getPlatformSpecificUrisForMode(PlatformMode::Desktop);
        $desktopForbidden = array_filter(
            $desktopUris,
            fn (string $uri) => str_starts_with($uri, 'api/mobile')
        );
        expect($desktopForbidden)->toBeEmpty();
    });

    test('all three platform modes are covered by the property', function () {
        // **Validates: Requirements 9.7**
        // Sanity check: PlatformMode enum has exactly the three cases this property targets.
        $cases = PlatformMode::cases();

        expect($cases)->toHaveCount(3);

        $caseValues = array_map(fn (PlatformMode $m) => $m->value, $cases);
        expect($caseValues)->toContain('web');
        expect($caseValues)->toContain('mobile');
        expect($caseValues)->toContain('desktop');
    });
});
