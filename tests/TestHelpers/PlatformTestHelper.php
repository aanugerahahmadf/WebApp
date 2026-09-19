<?php

namespace Tests\TestHelpers;

use App\Enums\PlatformMode\PlatformMode;
use App\Enums\RuntimePlatform\RuntimePlatform;
use App\Support\Platform\PlatformFeatureRegistry\PlatformFeatureRegistry;
use PHPUnit\Framework\Assert;

/**
 * PlatformTestHelper
 *
 * A trait for Laravel test classes that need to control and assert on
 * platform mode and runtime platform singletons.
 *
 * Usage:
 *   class MyTest extends TestCase
 *   {
 *       use PlatformTestHelper;
 *
 *       public function test_something(): void
 *       {
 *           $this->mockPlatformDetection();
 *           // or
 *           $this->setPlatformMode(PlatformMode::Mobile);
 *           $this->setRuntimePlatform(RuntimePlatform::MobileAppAndroid);
 *       }
 *   }
 *
 * Requirements: 10.1, 10.2, 10.3
 */
trait PlatformTestHelper
{
    /**
     * Override the 'platform.mode' singleton in the container.
     *
     * Call this before making requests or running assertions that depend on
     * the active platform mode.
     *
     * @param  PlatformMode  $mode  The platform mode to simulate
     */
    public function setPlatformMode(PlatformMode $mode): void
    {
        $this->app->instance('platform.mode', $mode);
    }

    /**
     * Override the 'runtime.platform' singleton in the container.
     *
     * Call this before making requests or running assertions that depend on
     * the active runtime platform.
     *
     * @param  RuntimePlatform  $platform  The runtime platform to simulate
     */
    public function setRuntimePlatform(RuntimePlatform $platform): void
    {
        $this->app->instance('runtime.platform', $platform);
    }

    /**
     * Set up both platform singletons with controlled values for testing.
     *
     * Falls back to safe defaults when parameters are omitted:
     *   - platform.mode    → PlatformMode::Web
     *   - runtime.platform → RuntimePlatform::WebsiteWindows
     *
     * Also registers PlatformFeatureRegistry as a singleton so feature-gated
     * code works in integration tests without a full bootstrap.
     *
     * @param  PlatformMode|null  $mode  Platform mode to simulate (defaults to PlatformMode::Web)
     * @param  RuntimePlatform|null  $platform  Runtime platform to simulate (defaults to RuntimePlatform::WebsiteWindows)
     */
    public function mockPlatformDetection(?PlatformMode $mode = null, ?RuntimePlatform $platform = null): void
    {
        $this->setPlatformMode($mode ?? PlatformMode::Web);
        $this->setRuntimePlatform($platform ?? RuntimePlatform::WebsiteWindows);
        $this->registerPlatformFeatureRegistry();
    }

    /**
     * Register PlatformFeatureRegistry in the container.
     *
     * Useful in feature/integration tests where the service provider may not
     * have been booted but code relies on app(PlatformFeatureRegistry::class).
     */
    public function registerPlatformFeatureRegistry(): void
    {
        if (! $this->app->bound(PlatformFeatureRegistry::class)) {
            $this->app->singleton(PlatformFeatureRegistry::class);
        }
    }

    /**
     * Assert that the current platform mode singleton matches the expected value.
     *
     * @param  PlatformMode  $expected  The expected platform mode
     */
    public function assertPlatformMode(PlatformMode $expected): void
    {
        $actual = $this->app->make('platform.mode');

        Assert::assertSame(
            $expected,
            $actual,
            sprintf(
                'Failed asserting that platform mode is [%s]. Got [%s].',
                $expected->value,
                $actual instanceof PlatformMode ? $actual->value : gettype($actual),
            ),
        );
    }

    /**
     * Assert that the current runtime platform singleton matches the expected value.
     *
     * @param  RuntimePlatform  $expected  The expected runtime platform
     */
    public function assertRuntimePlatform(RuntimePlatform $expected): void
    {
        $actual = $this->app->make('runtime.platform');

        Assert::assertSame(
            $expected,
            $actual,
            sprintf(
                'Failed asserting that runtime platform is [%s]. Got [%s].',
                $expected->value,
                $actual instanceof RuntimePlatform ? $actual->value : gettype($actual),
            ),
        );
    }
}
