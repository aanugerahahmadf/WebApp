<?php

namespace Tests\Unit;

use App\Enums\PlatformMode\PlatformMode;
use App\Enums\RuntimePlatform\RuntimePlatform;
use App\Support\Platform\PlatformCommandDetector\PlatformCommandDetector;
use Tests\TestCase;

/**
 * Integration tests for command-based platform mode initialization.
 *
 * Validates: Requirements 10.7
 */
class PlatformCommandInitializationTest extends TestCase
{
    private array $originalArgv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalArgv = $_SERVER['argv'] ?? [];
    }

    protected function tearDown(): void
    {
        $_SERVER['argv'] = $this->originalArgv;
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // PlatformCommandDetector::detectMode() — argv mapping
    // -----------------------------------------------------------------------

    public function test_serve_command_detects_web_mode(): void
    {
        $_SERVER['argv'] = ['artisan', 'serve'];
        $this->assertSame(PlatformMode::Web, PlatformCommandDetector::detectMode());
    }

    public function test_native_run_command_detects_mobile_mode(): void
    {
        $_SERVER['argv'] = ['artisan', 'native:run'];
        $this->assertSame(PlatformMode::Mobile, PlatformCommandDetector::detectMode());
    }

    public function test_native_serve_command_detects_desktop_mode(): void
    {
        $_SERVER['argv'] = ['artisan', 'native:serve'];
        $this->assertSame(PlatformMode::Desktop, PlatformCommandDetector::detectMode());
    }

    public function test_unknown_command_defaults_to_web_mode(): void
    {
        $_SERVER['argv'] = ['artisan', 'migrate'];
        $this->assertSame(PlatformMode::Web, PlatformCommandDetector::detectMode());
    }

    public function test_native_prefix_commands_detect_desktop_mode(): void
    {
        $_SERVER['argv'] = ['artisan', 'native:install'];
        $this->assertSame(PlatformMode::Desktop, PlatformCommandDetector::detectMode());
    }

    public function test_empty_argv_defaults_to_web_mode(): void
    {
        $_SERVER['argv'] = [];
        $this->assertSame(PlatformMode::Web, PlatformCommandDetector::detectMode());
    }

    public function test_non_artisan_script_uses_runtime_detection(): void
    {
        // When argv[0] does not end with 'artisan', detectFromRuntime() is used.
        // Without any NATIVEPHP_RUNNING env vars, it should default to Web.
        $_SERVER['argv'] = ['php', '-r', 'echo 1;'];
        $this->assertSame(PlatformMode::Web, PlatformCommandDetector::detectMode());
    }

    // -----------------------------------------------------------------------
    // Service provider — app('platform.mode') returns bound mode
    // -----------------------------------------------------------------------

    public function test_platform_mode_singleton_is_bound_in_container(): void
    {
        // PlatformModeServiceProvider is registered in bootstrap/providers.php
        // and runs during the test suite bootstrap, so the binding should exist.
        $this->assertTrue($this->app->bound('platform.mode'));
    }

    public function test_platform_mode_singleton_returns_platform_mode_instance(): void
    {
        $mode = $this->app->make('platform.mode');
        $this->assertInstanceOf(PlatformMode::class, $mode);
    }

    public function test_runtime_platform_singleton_is_bound_in_container(): void
    {
        $this->assertTrue($this->app->bound('runtime.platform'));
    }

    public function test_runtime_platform_singleton_returns_valid_runtime_platform(): void
    {
        $platform = $this->app->make('runtime.platform');
        $this->assertInstanceOf(RuntimePlatform::class, $platform);
        $this->assertContains($platform, RuntimePlatform::cases());
    }

    // -----------------------------------------------------------------------
    // app()->instance() override — simulating mode switching
    // -----------------------------------------------------------------------

    public function test_can_override_platform_mode_in_tests(): void
    {
        $this->app->instance('platform.mode', PlatformMode::Mobile);
        $this->assertSame(PlatformMode::Mobile, $this->app->make('platform.mode'));
    }

    public function test_can_override_runtime_platform_in_tests(): void
    {
        $this->app->instance('runtime.platform', RuntimePlatform::MobileAppAndroid);
        $this->assertSame(RuntimePlatform::MobileAppAndroid, $this->app->make('runtime.platform'));
    }
}
