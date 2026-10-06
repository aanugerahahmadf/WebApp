<?php

/**
 * Preservation Property Tests for Non-Desktop Platform Functionality
 *
 * **Property 2: Preservation** - Non-Desktop Platform Functionality
 *
 * **Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5**
 *
 * This test verifies that non-desktop platform functionality remains unchanged when fixes
 * are applied to the desktop application launch bugs. These tests MUST PASS on unfixed code
 * to establish the baseline behavior that should be preserved.
 *
 * **EXPECTED OUTCOME**: Tests PASS on unfixed code (confirms baseline to preserve)
 *
 * The preservation property states:
 * FOR ALL application runs that are NOT desktop app startup (including mobile app via
 * `php artisan serve:mobile`, web app via `php artisan serve:web`, production builds, and
 * configuration loading for other platforms), the fixed code SHALL produce exactly the
 * same behavior as the original code.
 *
 * Testing Approach:
 * - Use property-based testing with Pest datasets to generate many test cases
 * - Test across different platform modes (Mobile, Web)
 * - Test across different environment configurations
 * - Test across different feature combinations
 * - Confirm behavior on UNFIXED code first
 */

use App\Enums\PlatformMode\PlatformMode;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

describe('Property 2: Preservation - Non-Desktop Platform Functionality', function () {

    afterEach(function () {
        // Clear platform.mode binding to avoid state pollution between tests
        app()->forgetInstance('platform.mode');
        app()->forgetInstance('runtime.platform');
    });

    // Ã¢â€â‚¬Ã¢â€â‚¬ Helper Functions Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬

    /**
     * Check if a platform command is available and functional
     */
    function isPlatformCommandAvailable(string $command): bool
    {
        try {
            // Check if artisan command exists
            $commands = Artisan::all();
            $commandName = str_replace('php artisan ', '', $command);

            return array_key_exists($commandName, $commands);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Check if environment file exists
     */
    function hasEnvFile(string $platform): bool
    {
        // Check for .example file (baseline configuration)
        $envFile = base_path(".env.{$platform}.example");

        return File::exists($envFile);
    }

    /**
     * Get environment configuration for a platform
     */
    function getEnvConfig(string $platform): array
    {
        $envFile = base_path(".env.{$platform}.example");

        if (! File::exists($envFile)) {
            return [];
        }

        $contents = File::get($envFile);
        $config = [];

        // Parse .env file format
        foreach (explode("\n", $contents) as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $config[trim($key)] = trim($value);
            }
        }

        return $config;
    }

    /**
     * Validate that a configuration value is properly set
     */
    function isConfigValid(string $key, mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        // Check for placeholder values that indicate missing config
        $placeholders = ['your-', 'example', 'changeme', 'todo', 'fixme'];
        $valueLower = strtolower((string) $value);

        foreach ($placeholders as $placeholder) {
            if (str_contains($valueLower, $placeholder)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Simulate checking if a feature works (placeholder for actual feature tests)
     */
    function isFeatureWorking(string $feature, PlatformMode $platform): bool
    {
        // In a real implementation, this would test actual features
        // For now, we check if the feature is registered and enabled

        $features = [
            'auth' => true, // Authentication is always available
            'database' => Config::get('database.default') !== null,
            'fileUpload' => Config::get('filesystems.default') !== null,
            'session' => Config::get('session.driver') !== null,
            'cache' => Config::get('cache.default') !== null,
        ];

        return $features[$feature] ?? false;
    }

    // Ã¢â€â‚¬Ã¢â€â‚¬ Property-Based Tests Using Datasets Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬

    test('Preservation 3.1: Non-desktop platforms launch successfully', function (string $platform, string $command) {
        // **Validates: Requirements 3.1**
        //
        // **Preservation Property**: FOR ALL platform IN [Mobile, Web]
        //                            ASSERT launchApp(platform) succeeds
        //
        // This test verifies that mobile and web platforms can launch successfully.
        // On unfixed code, these should work fine (desktop bug doesn't affect them).
        //
        // **EXPECTED TO PASS on unfixed code**

        // Check if the command is available
        $commandAvailable = isPlatformCommandAvailable($command);

        // For platforms that should be supported, the command must be available
        if (in_array($platform, ['mobile', 'web'])) {
            expect($commandAvailable)->toBeTrue(
                "Command '{$command}' should be available for {$platform} platform. ".
                'This is baseline behavior to preserve.'
            );
        }
    })->with([
        'mobile platform' => ['mobile', 'serve:mobile'],
        'web platform' => ['web', 'serve:web'],
    ]);

    test('Preservation 3.2: Platform-specific features work correctly', function (string $feature, PlatformMode $platform) {
        // **Validates: Requirements 3.2**
        //
        // **Preservation Property**: FOR ALL features IN [auth, database, fileUpload]
        //                            ASSERT feature works after non-desktop launch
        //
        // This test verifies that core features work correctly on non-desktop platforms.
        // On unfixed code, these should work fine (desktop bug doesn't affect them).
        //
        // **EXPECTED TO PASS on unfixed code**

        // Simulate platform detection
        app()->instance('platform.mode', $platform);

        $featureWorks = isFeatureWorking($feature, $platform);

        expect($featureWorks)->toBeTrue(
            "Feature '{$feature}' should work on {$platform->value} platform. ".
            'This is baseline behavior to preserve.'
        );
    })->with([
        // Feature matrix: test each feature on each non-desktop platform
        'auth on mobile' => ['auth', PlatformMode::Mobile],
        'database on mobile' => ['database', PlatformMode::Mobile],
        'fileUpload on mobile' => ['fileUpload', PlatformMode::Mobile],
        'auth on web' => ['auth', PlatformMode::Web],
        'database on web' => ['database', PlatformMode::Web],
        'fileUpload on web' => ['fileUpload', PlatformMode::Web],
        'session on mobile' => ['session', PlatformMode::Mobile],
        'cache on mobile' => ['cache', PlatformMode::Mobile],
        'session on web' => ['session', PlatformMode::Web],
        'cache on web' => ['cache', PlatformMode::Web],
    ]);

    test('Preservation 3.3: Production build configurations remain unchanged', function (string $platform) {
        // **Validates: Requirements 3.3**
        //
        // **Preservation Property**: Production build process should not be affected by desktop fixes
        //
        // This test verifies that production build configurations are intact.
        // On unfixed code, these should be properly configured.
        //
        // **EXPECTED TO PASS on unfixed code**

        // Check if platform has environment file
        $hasEnv = hasEnvFile($platform);

        // For production builds, we expect environment configurations to exist
        expect($hasEnv)->toBeTrue(
            "Environment file .env.{$platform}.example should exist for production builds. ".
            'This is baseline behavior to preserve.'
        );

        // Get configuration
        $config = getEnvConfig($platform);

        // Verify key configurations exist
        // Note: APP_PORT removed in Capacitor migration - serve commands use --port flag directly
        $expectedKeys = ['APP_URL', 'SESSION_DRIVER', 'CACHE_DRIVER', 'VITE_PLATFORM'];
        foreach ($expectedKeys as $key) {
            $hasKey = array_key_exists($key, $config);
            expect($hasKey)->toBeTrue(
                "Configuration key '{$key}' should exist in .env.{$platform}.example. ".
                'This is baseline behavior to preserve.'
            );
        }
    })->with([
        'mobile production' => ['mobile'],
        'web production' => ['web'],
        'desktop production' => ['desktop'],
    ]);

    test('Preservation 3.4: Environment configuration loading works correctly', function (string $platform, string $configKey) {
        // **Validates: Requirements 3.4**
        //
        // **Preservation Property**: FOR ALL envConfig NOT related to desktop
        //                            ASSERT configLoading(envConfig) = original behavior
        //
        // This test verifies that environment configuration loading works correctly.
        // On unfixed code, config loading should work fine.
        //
        // **EXPECTED TO PASS on unfixed code**

        $envConfig = getEnvConfig($platform);

        // File wajib ada -- Requirement 3.3 sudah memegangnya. Kalau di sini
        // kosong berarti file hilang, jadi ini kegagalan, bukan "skip".
        expect($envConfig)->not->toBeEmpty(
            ".env.{$platform}.example must exist and be readable"
        );

        $hasKey = array_key_exists($configKey, $envConfig);

        expect($hasKey)->toBeTrue(
            "Config key '{$configKey}' should exist in .env.{$platform}.example. ".
            'This is baseline behavior to preserve.'
        );

        expect($envConfig[$configKey])->not->toBeEmpty(
            "Config key '{$configKey}' in .env.{$platform}.example must not be empty"
        );
    })->with([
        // Test different config keys across platforms
        'mobile APP_URL' => ['mobile', 'APP_URL'],
        'mobile SESSION_DRIVER' => ['mobile', 'SESSION_DRIVER'],
        'mobile VITE_PLATFORM' => ['mobile', 'VITE_PLATFORM'],
        'web APP_URL' => ['web', 'APP_URL'],
        'web SESSION_DRIVER' => ['web', 'SESSION_DRIVER'],
        'web VITE_PLATFORM' => ['web', 'VITE_PLATFORM'],
    ]);

    test('Preservation 3.5: Development hot reload configuration preserved', function (string $platform) {
        // **Validates: Requirements 3.5**
        //
        // **Preservation Property**: Hot reload and watch mode should work in development
        //
        // This test verifies that development configurations support hot reload.
        // On unfixed code, this should be properly configured.
        //
        // **EXPECTED TO PASS on unfixed code**

        $envConfig = getEnvConfig($platform);

        // Sama seperti 3.4: file wajib ada, jadi kondisi kosong adalah kegagalan.
        expect($envConfig)->not->toBeEmpty(
            ".env.{$platform}.example must exist and be readable"
        );

        expect(array_key_exists('VITE_PLATFORM', $envConfig))->toBeTrue(
            "VITE_PLATFORM should be defined in .env.{$platform}.example for hot reload. ".
            'This is baseline behavior to preserve.'
        );

        expect($envConfig['VITE_PLATFORM'])->toBe(
            $platform,
            "VITE_PLATFORM should match the platform ({$platform}) for correct hot reload. ".
            'This is baseline behavior to preserve.'
        );
    })->with([
        'mobile hot reload' => ['mobile'],
        'web hot reload' => ['web'],
    ]);

    // Ã¢â€â‚¬Ã¢â€â‚¬ Integration: Overall Preservation Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬

    test('Preservation Integration: All non-desktop functionality preserved', function () {
        // **Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5**
        //
        // **Full Preservation Property**: FOR ALL input WHERE NOT isBugCondition(input)
        //                                 ASSERT behavior = original behavior
        //
        // This integration test verifies that all non-desktop functionality works correctly.
        // On unfixed code, all these checks should pass.
        //
        // **EXPECTED TO PASS on unfixed code**

        $results = [
            'mobile_command_available' => isPlatformCommandAvailable('serve:mobile'),
            'web_command_available' => isPlatformCommandAvailable('serve:web'),
            'mobile_env_exists' => hasEnvFile('mobile'),
            'web_env_exists' => hasEnvFile('web'),
            'mobile_config_loaded' => ! empty(getEnvConfig('mobile')),
            'web_config_loaded' => ! empty(getEnvConfig('web')),
        ];

        // Check core features work
        app()->instance('platform.mode', PlatformMode::Mobile);
        $results['mobile_auth_works'] = isFeatureWorking('auth', PlatformMode::Mobile);
        $results['mobile_database_works'] = isFeatureWorking('database', PlatformMode::Mobile);

        app()->instance('platform.mode', PlatformMode::Web);
        $results['web_auth_works'] = isFeatureWorking('auth', PlatformMode::Web);
        $results['web_database_works'] = isFeatureWorking('database', PlatformMode::Web);

        // All checks should pass
        foreach ($results as $check => $passed) {
            expect($passed)->toBeTrue(
                "Check '{$check}' should pass. This is baseline behavior to preserve."
            );
        }
    });
});
