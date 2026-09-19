<?php

namespace Tests\Unit;

use App\Enums\PlatformMode\PlatformMode;
use App\Enums\RuntimePlatform\RuntimePlatform;
use App\Providers\PlatformModeServiceProvider\PlatformModeServiceProvider;
use App\Support\Platform\EnvironmentManager\EnvironmentManager;
use App\Support\Platform\PlatformAssetManager\PlatformAssetManager;
use App\Support\Platform\RuntimePlatformDetector\RuntimePlatformDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Test the PlatformModeServiceProvider
 *
 * Validates:
 * - Platform mode detection and singleton registration
 * - Runtime platform detection and singleton registration
 * - Service registrations (RuntimePlatformDetector, EnvironmentManager, PlatformAssetManager)
 * - Environment loading during boot
 * - Asset manager configuration
 * - Development mode logging
 */
class PlatformModeServiceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clear any cached singletons
        if (app()->bound('platform.mode')) {
            app()->forgetInstance('platform.mode');
        }
        if (app()->bound('runtime.platform')) {
            app()->forgetInstance('runtime.platform');
        }
    }

    /**
     * Test that the service provider registers platform.mode singleton
     */
    public function test_registers_platform_mode_singleton(): void
    {
        // Simulate running "php artisan serve" command
        $_SERVER['argv'] = ['artisan', 'serve'];

        $provider = new PlatformModeServiceProvider($this->app);
        $provider->register();

        $this->assertTrue($this->app->bound('platform.mode'));

        $mode = $this->app->make('platform.mode');
        $this->assertInstanceOf(PlatformMode::class, $mode);
    }

    /**
     * Test that the service provider registers required service singletons
     */
    public function test_registers_required_service_singletons(): void
    {
        $provider = new PlatformModeServiceProvider($this->app);
        $provider->register();

        // Verify all required services are registered as singletons
        $this->assertTrue($this->app->bound(RuntimePlatformDetector::class));
        $this->assertTrue($this->app->bound(EnvironmentManager::class));
        $this->assertTrue($this->app->bound(PlatformAssetManager::class));

        // Verify they are singletons (same instance on multiple calls)
        $detector1 = $this->app->make(RuntimePlatformDetector::class);
        $detector2 = $this->app->make(RuntimePlatformDetector::class);
        $this->assertSame($detector1, $detector2);

        $envManager1 = $this->app->make(EnvironmentManager::class);
        $envManager2 = $this->app->make(EnvironmentManager::class);
        $this->assertSame($envManager1, $envManager2);

        $assetManager1 = $this->app->make(PlatformAssetManager::class);
        $assetManager2 = $this->app->make(PlatformAssetManager::class);
        $this->assertSame($assetManager1, $assetManager2);
    }

    /**
     * Test that boot method detects runtime platform and stores it as singleton
     */
    public function test_boot_detects_and_stores_runtime_platform(): void
    {
        // Simulate Web mode
        $_SERVER['argv'] = ['artisan', 'serve'];

        // Create a mock request with Windows user agent
        $request = Request::create('/', 'GET');
        $request->headers->set('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        $this->app->instance('request', $request);

        $provider = new PlatformModeServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        // Verify runtime platform is registered as singleton
        $this->assertTrue($this->app->bound('runtime.platform'));

        $runtimePlatform = $this->app->make('runtime.platform');
        $this->assertInstanceOf(RuntimePlatform::class, $runtimePlatform);
        $this->assertEquals(RuntimePlatform::WebsiteWindows, $runtimePlatform);
    }

    /**
     * Test that boot method configures asset manager
     */
    public function test_boot_configures_asset_manager(): void
    {
        // Simulate Mobile mode
        $_SERVER['argv'] = ['artisan', 'native:run'];

        $provider = new PlatformModeServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        // Get the asset manager and verify it's configured for Mobile mode
        $assetManager = $this->app->make(PlatformAssetManager::class);
        $this->assertNotNull($assetManager->getMode());
        $this->assertEquals(PlatformMode::Mobile, $assetManager->getMode());
        $this->assertEquals('build/mobile', $assetManager->getBuildDirectory());
    }

    /**
     * Test that boot method logs platform detection in local environment
     */
    public function test_boot_logs_platform_detection_in_local_environment(): void
    {
        // Set environment to local
        $this->app['env'] = 'local';

        // Simulate Desktop mode
        $_SERVER['argv'] = ['artisan', 'native:serve'];

        // Allow any debug/warning calls from EnvironmentManager and ProductionValidator
        Log::shouldReceive('debug')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->zeroOrMoreTimes();

        // Expect at least one 'Platform detected' info entry from the provider itself
        Log::shouldReceive('info')
            ->atLeast()->once()
            ->with('Platform detected', \Mockery::type('array'));

        // Allow other info calls (e.g. EnvironmentManager logging loaded env file)
        Log::shouldReceive('info')->zeroOrMoreTimes();

        $provider = new PlatformModeServiceProvider($this->app);
        $provider->register();
        $provider->boot();
    }

    /**
     * Test that boot method does not log in production environment
     */
    public function test_boot_does_not_log_in_production_environment(): void
    {
        // Set environment to production
        $this->app['env'] = 'production';

        // Set required production env variables so ProductionValidator does not throw
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        putenv('APP_KEY=base64:'.base64_encode(random_bytes(32)));
        $_ENV['APP_KEY'] = 'base64:'.base64_encode(random_bytes(32));
        $_ENV['APP_URL'] = 'http://localhost';
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_HOST'] = '127.0.0.1';
        $_ENV['DB_DATABASE'] = ':memory:';
        $_ENV['DB_USERNAME'] = 'root';
        $_ENV['SESSION_DRIVER'] = 'array';

        // Simulate Web mode
        $_SERVER['argv'] = ['artisan', 'serve'];

        // Expect NO 'info' log entry from the platform provider itself (production mode)
        Log::shouldReceive('info')->never();

        // Allow debug/warning/error calls from other components (EnvironmentManager, ProductionValidator)
        Log::shouldReceive('debug')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        $provider = new PlatformModeServiceProvider($this->app);
        $provider->register();
        $provider->boot();
    }

    /**
     * Test that boot method calls environment manager to load platform environment
     */
    public function test_boot_loads_platform_environment(): void
    {
        // Simulate Web mode
        $_SERVER['argv'] = ['artisan', 'serve'];

        $provider = new PlatformModeServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        // Verify environment manager was used (indirectly by checking it's instantiated)
        $envManager = $this->app->make(EnvironmentManager::class);
        $this->assertInstanceOf(EnvironmentManager::class, $envManager);
    }

    /**
     * Test complete bootstrap flow for Web mode
     */
    public function test_complete_bootstrap_flow_for_web_mode(): void
    {
        $_SERVER['argv'] = ['artisan', 'serve'];

        $request = Request::create('/', 'GET');
        $request->headers->set('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)');
        $this->app->instance('request', $request);

        $provider = new PlatformModeServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        // Verify platform mode
        $mode = $this->app->make('platform.mode');
        $this->assertEquals(PlatformMode::Web, $mode);

        // Verify runtime platform
        $runtime = $this->app->make('runtime.platform');
        $this->assertEquals(RuntimePlatform::WebsiteMacOS, $runtime);

        // Verify asset manager configuration
        $assetManager = $this->app->make(PlatformAssetManager::class);
        $this->assertEquals('build/web', $assetManager->getBuildDirectory());
    }

    /**
     * Test complete bootstrap flow for Mobile mode
     */
    public function test_complete_bootstrap_flow_for_mobile_mode(): void
    {
        $_SERVER['argv'] = ['artisan', 'native:run'];

        // Mobile mode doesn't use request, uses config/environment
        config(['native.platform' => 'android']);

        $provider = new PlatformModeServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        // Verify platform mode
        $mode = $this->app->make('platform.mode');
        $this->assertEquals(PlatformMode::Mobile, $mode);

        // Verify runtime platform
        $runtime = $this->app->make('runtime.platform');
        $this->assertEquals(RuntimePlatform::MobileAppAndroid, $runtime);

        // Verify asset manager configuration
        $assetManager = $this->app->make(PlatformAssetManager::class);
        $this->assertEquals('build/mobile', $assetManager->getBuildDirectory());
    }

    /**
     * Test complete bootstrap flow for Desktop mode
     */
    public function test_complete_bootstrap_flow_for_desktop_mode(): void
    {
        $_SERVER['argv'] = ['artisan', 'native:serve'];

        $provider = new PlatformModeServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        // Verify platform mode
        $mode = $this->app->make('platform.mode');
        $this->assertEquals(PlatformMode::Desktop, $mode);

        // Verify runtime platform (depends on PHP_OS_FAMILY)
        $runtime = $this->app->make('runtime.platform');
        $this->assertInstanceOf(RuntimePlatform::class, $runtime);
        $this->assertTrue($runtime->isDesktopApp());

        // Verify asset manager configuration
        $assetManager = $this->app->make(PlatformAssetManager::class);
        $this->assertEquals('build/desktop', $assetManager->getBuildDirectory());
    }

    /**
     * Test that runtime platform singleton returns same instance
     */
    public function test_runtime_platform_singleton_consistency(): void
    {
        $_SERVER['argv'] = ['artisan', 'serve'];

        $provider = new PlatformModeServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        $runtime1 = $this->app->make('runtime.platform');
        $runtime2 = $this->app->make('runtime.platform');

        $this->assertSame($runtime1, $runtime2);
    }

    /**
     * Test that platform mode singleton returns same instance
     */
    public function test_platform_mode_singleton_consistency(): void
    {
        $_SERVER['argv'] = ['artisan', 'serve'];

        $provider = new PlatformModeServiceProvider($this->app);
        $provider->register();

        $mode1 = $this->app->make('platform.mode');
        $mode2 = $this->app->make('platform.mode');

        $this->assertSame($mode1, $mode2);
    }
}
