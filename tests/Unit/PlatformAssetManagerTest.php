<?php

namespace Tests\Unit;

use App\Enums\PlatformMode\PlatformMode;
use App\Support\Platform\PlatformAssetManager\PlatformAssetManager;
use Tests\TestCase;

/**
 * Property Test: Asset Manifest Path Matches Platform Mode
 *
 * Property 6: For ANY platform mode (Web, Mobile, Desktop), when the Asset Manager is
 * configured for that mode, the manifest path returned SHALL contain the directory name
 * matching that mode's asset directory (build/web, build/mobile, or build/desktop).
 *
 * Validates: Requirements 4.6
 */
class PlatformAssetManagerTest extends TestCase
{
    private PlatformAssetManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new PlatformAssetManager;
    }

    /**
     * Property Test: Manifest path contains correct directory name for all platform modes
     *
     * For each platform mode (Web, Mobile, Desktop):
     * - Verify manifest path contains correct directory name
     * - Test getBuildDirectory() returns expected paths
     */
    public function test_manifest_path_matches_platform_mode_for_all_modes(): void
    {
        $modes = [
            PlatformMode::Web,
            PlatformMode::Mobile,
            PlatformMode::Desktop,
        ];

        foreach ($modes as $mode) {
            // Configure asset manager for this platform mode
            $this->manager->configure($mode);

            // Get the expected asset directory from the mode
            $expectedAssetDirectory = $mode->assetDirectory();

            // Verify getBuildDirectory() returns expected path
            $buildDirectory = $this->manager->getBuildDirectory();
            $this->assertSame(
                $expectedAssetDirectory,
                $buildDirectory,
                "getBuildDirectory() should return '{$expectedAssetDirectory}' for {$mode->value} mode"
            );

            // Verify the build directory follows the expected pattern
            $this->assertMatchesRegularExpression(
                '/^build\/(web|mobile|desktop)$/',
                $buildDirectory,
                "Build directory should follow pattern 'build/{mode}'"
            );

            // Verify getManifestPath() contains the correct directory name
            $manifestPath = $this->manager->getManifestPath();
            $this->assertStringContainsString(
                $expectedAssetDirectory,
                $manifestPath,
                "Manifest path should contain directory '{$expectedAssetDirectory}' for {$mode->value} mode"
            );

            // Verify manifest path ends with manifest.json
            $this->assertStringEndsWith(
                '/manifest.json',
                $manifestPath,
                "Manifest path should end with '/manifest.json'"
            );

            // Verify manifest path structure: should be public_path/{buildDir}/manifest.json
            $expectedManifestPath = public_path("{$expectedAssetDirectory}/manifest.json");
            $this->assertSame(
                $expectedManifestPath,
                $manifestPath,
                "Manifest path should be public_path('{$expectedAssetDirectory}/manifest.json')"
            );
        }
    }

    /**
     * Property Test: Build directory always matches mode's asset directory
     *
     * Verifies that getBuildDirectory() consistently returns the value from
     * PlatformMode::assetDirectory() for all modes.
     */
    public function test_build_directory_always_matches_mode_asset_directory(): void
    {
        $testCases = [
            ['mode' => PlatformMode::Web, 'expected' => 'build/web'],
            ['mode' => PlatformMode::Mobile, 'expected' => 'build/mobile'],
            ['mode' => PlatformMode::Desktop, 'expected' => 'build/desktop'],
        ];

        foreach ($testCases as $testCase) {
            $mode = $testCase['mode'];
            $expectedDirectory = $testCase['expected'];

            $this->manager->configure($mode);

            $buildDirectory = $this->manager->getBuildDirectory();
            $modeAssetDirectory = $mode->assetDirectory();

            // Build directory must exactly match mode's asset directory
            $this->assertSame(
                $modeAssetDirectory,
                $buildDirectory,
                "getBuildDirectory() must match {$mode->value}->assetDirectory()"
            );

            // Verify against expected hardcoded value
            $this->assertSame(
                $expectedDirectory,
                $buildDirectory,
                "Build directory should be '{$expectedDirectory}' for {$mode->value} mode"
            );
        }
    }

    /**
     * Property Test: Manifest path structure is consistent across all modes
     *
     * Verifies that all platform modes follow the same path structure pattern:
     * public_path(build/{mode}/manifest.json)
     */
    public function test_manifest_path_structure_is_consistent_across_modes(): void
    {
        $modes = [
            PlatformMode::Web,
            PlatformMode::Mobile,
            PlatformMode::Desktop,
        ];

        foreach ($modes as $mode) {
            $this->manager->configure($mode);

            $manifestPath = $this->manager->getManifestPath();
            $buildDirectory = $this->manager->getBuildDirectory();

            // Extract the path components
            $this->assertStringContainsString('public', $manifestPath);
            $this->assertStringContainsString('build', $manifestPath);
            $this->assertStringContainsString($mode->value, $manifestPath);
            $this->assertStringContainsString('manifest.json', $manifestPath);

            // Verify path structure matches expected pattern
            // Normalize path separators for cross-platform compatibility
            $normalizedPath = str_replace('\\', '/', $manifestPath);
            $expectedPattern = "/build\\/{$mode->value}\\/manifest\\.json$/";
            $this->assertMatchesRegularExpression(
                $expectedPattern,
                $normalizedPath,
                "Manifest path should end with 'build/{$mode->value}/manifest.json' for {$mode->value} mode"
            );
        }
    }

    /**
     * Property Test: Mode changes update build directory immediately
     *
     * Verifies that when the mode is reconfigured, getBuildDirectory()
     * immediately reflects the new mode without caching issues.
     */
    public function test_mode_changes_update_build_directory_immediately(): void
    {
        $transitions = [
            [PlatformMode::Web, PlatformMode::Mobile],
            [PlatformMode::Mobile, PlatformMode::Desktop],
            [PlatformMode::Desktop, PlatformMode::Web],
            [PlatformMode::Web, PlatformMode::Desktop],
            [PlatformMode::Mobile, PlatformMode::Web],
            [PlatformMode::Desktop, PlatformMode::Mobile],
        ];

        foreach ($transitions as [$fromMode, $toMode]) {
            // Configure first mode
            $this->manager->configure($fromMode);
            $firstDirectory = $this->manager->getBuildDirectory();
            $this->assertSame(
                $fromMode->assetDirectory(),
                $firstDirectory,
                "Initial directory should match {$fromMode->value} mode"
            );

            // Reconfigure to second mode
            $this->manager->configure($toMode);
            $secondDirectory = $this->manager->getBuildDirectory();
            $this->assertSame(
                $toMode->assetDirectory(),
                $secondDirectory,
                "Directory should immediately update to {$toMode->value} mode"
            );

            // Verify directories are different (if modes are different)
            if ($fromMode !== $toMode) {
                $this->assertNotSame(
                    $firstDirectory,
                    $secondDirectory,
                    "Build directory should change when mode changes from {$fromMode->value} to {$toMode->value}"
                );
            }
        }
    }

    /**
     * Property Test: Default mode when not configured
     *
     * Verifies that when the asset manager is not configured,
     * it defaults to web mode directories.
     */
    public function test_defaults_to_web_mode_when_not_configured(): void
    {
        // Create a fresh manager without configuration
        $manager = new PlatformAssetManager;

        // Should default to web mode
        $buildDirectory = $manager->getBuildDirectory();
        $this->assertSame(
            'build/web',
            $buildDirectory,
            "Should default to 'build/web' when not configured"
        );

        $manifestPath = $manager->getManifestPath();
        $this->assertStringContainsString(
            'build/web',
            $manifestPath,
            "Manifest path should contain 'build/web' when not configured"
        );

        $viteInput = $manager->getViteInput();
        $this->assertSame(
            'resources/js/app-web/app-web.js',
            $viteInput,
            'Vite input should default to web entry point when not configured'
        );
    }

    /**
     * Property Test: Vite input path matches platform mode
     *
     * Verifies that getViteInput() returns the correct entry point
     * file path for each platform mode.
     */
    public function test_vite_input_path_matches_platform_mode(): void
    {
        $testCases = [
            ['mode' => PlatformMode::Web, 'expected' => 'resources/js/app-web/app-web.js'],
            ['mode' => PlatformMode::Mobile, 'expected' => 'resources/js/app-mobile/app-mobile.js'],
            ['mode' => PlatformMode::Desktop, 'expected' => 'resources/js/app-desktop/app-desktop.js'],
        ];

        foreach ($testCases as $testCase) {
            $mode = $testCase['mode'];
            $expectedInput = $testCase['expected'];

            $this->manager->configure($mode);

            $viteInput = $this->manager->getViteInput();
            $modeViteInput = $mode->viteInput();

            // Vite input must exactly match mode's vite input
            $this->assertSame(
                $modeViteInput,
                $viteInput,
                "getViteInput() must match {$mode->value}->viteInput()"
            );

            // Verify against expected hardcoded value
            $this->assertSame(
                $expectedInput,
                $viteInput,
                "Vite input should be '{$expectedInput}' for {$mode->value} mode"
            );

            // Verify the pattern
            $this->assertMatchesRegularExpression(
                '/^resources\/js\/app-(web|mobile|desktop)\/app-\1\.js$/',
                $viteInput,
                "Vite input should follow pattern 'resources/js/app-{mode}/app-{mode}.js'"
            );
        }
    }

    /**
     * Property Test: Mode getter returns configured mode
     *
     * Verifies that getMode() correctly returns the currently configured mode.
     */
    public function test_mode_getter_returns_configured_mode(): void
    {
        $modes = [
            PlatformMode::Web,
            PlatformMode::Mobile,
            PlatformMode::Desktop,
        ];

        foreach ($modes as $mode) {
            $this->manager->configure($mode);

            $retrievedMode = $this->manager->getMode();
            $this->assertSame(
                $mode,
                $retrievedMode,
                "getMode() should return the configured {$mode->value} mode"
            );
        }
    }

    /**
     * Property Test: All platform modes produce valid public paths
     *
     * Verifies that manifest paths for all modes are valid public paths
     * (i.e., they start with the public directory).
     */
    public function test_all_modes_produce_valid_public_paths(): void
    {
        $modes = [
            PlatformMode::Web,
            PlatformMode::Mobile,
            PlatformMode::Desktop,
        ];

        $publicPath = public_path();

        foreach ($modes as $mode) {
            $this->manager->configure($mode);

            $manifestPath = $this->manager->getManifestPath();

            // Manifest path should start with public_path()
            $this->assertStringStartsWith(
                $publicPath,
                $manifestPath,
                "Manifest path for {$mode->value} mode should start with public_path()"
            );

            // Path should be absolute
            $this->assertTrue(
                str_starts_with($manifestPath, '/') || preg_match('/^[A-Za-z]:/', $manifestPath),
                "Manifest path should be absolute for {$mode->value} mode"
            );
        }
    }
}
