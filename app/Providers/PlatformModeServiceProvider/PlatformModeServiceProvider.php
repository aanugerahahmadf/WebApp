<?php

namespace App\Providers\PlatformModeServiceProvider;

use App\Enums\PlatformMode\PlatformMode;
use App\Enums\RuntimePlatform\RuntimePlatform;
use App\Support\Platform\EnvironmentManager\EnvironmentManager;
use App\Support\Platform\PlatformAssetManager\PlatformAssetManager;
use App\Support\Platform\PlatformCommandDetector\PlatformCommandDetector;
use App\Support\Platform\ProductionValidator\ProductionValidator;
use App\Support\Platform\RuntimePlatformDetector\RuntimePlatformDetector;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * PlatformModeServiceProvider
 *
 * Bootstraps the multi-platform support layer early in the application lifecycle.
 * This provider MUST be registered before RouteServiceProvider so that the
 * platform.mode singleton is available when conditional routes are loaded.
 *
 * Responsibilities:
 * - Detect and register platform mode as a singleton (app('platform.mode'))
 * - Register RuntimePlatformDetector, EnvironmentManager, PlatformAssetManager as singletons
 * - Load platform-specific environment variables
 * - Detect and register the runtime platform as a singleton (app('runtime.platform'))
 * - Configure PlatformAssetManager for the detected platform mode
 * - Log platform detection details in local/development environments
 *
 * Requirements: 1.4, 1.5, 2.5
 */
class PlatformModeServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * Runs before boot(). Registers all singletons so they are
     * available to other providers during the boot phase.
     */
    public function register(): void
    {
        // Detect platform mode from command-line arguments or runtime environment
        $this->app->singleton('platform.mode', function () {
            return PlatformCommandDetector::detectMode();
        });

        // Register supporting services as singletons
        $this->app->singleton(RuntimePlatformDetector::class);
        $this->app->singleton(EnvironmentManager::class);
        $this->app->singleton(PlatformAssetManager::class);
        $this->app->singleton(ProductionValidator::class);
    }

    /**
     * Bootstrap any application services.
     *
     * Runs after all providers have been registered. Performs the full
     * platform detection sequence:
     *   1. Resolve the already-detected platform mode
     *   2. Load platform-specific .env.{mode} file
     *   3. Detect the runtime platform (OS/device)
     *   4. Configure the asset manager for the detected mode
     *   5. Log detection details in local environment
     */
    public function boot(): void
    {
        /** @var PlatformMode $mode */
        $mode = $this->app->make('platform.mode');

        // Load platform-specific environment file (.env.web / .env.mobile / .env.desktop)
        /** @var EnvironmentManager $environmentManager */
        $environmentManager = $this->app->make(EnvironmentManager::class);
        $environmentManager->loadPlatformEnvironment($mode);

        // Run production environment validation — throws in production if required vars missing
        /** @var ProductionValidator $validator */
        $validator = $this->app->make(ProductionValidator::class);
        $validator->validateDependencies($mode);

        // Detect the runtime platform (user agent / OS / NativePHP API).
        // Wrap in try/catch because the HTTP request may not be available yet
        // when booting in a CLI context (queue workers, scheduled commands, etc.).
        $this->app->singleton('runtime.platform', function () use ($mode): RuntimePlatform {
            try {
                /** @var RuntimePlatformDetector $detector */
                $detector = $this->app->make(RuntimePlatformDetector::class);

                // Only pass the current request when we are actually handling an HTTP request.
                $request = null;
                if ($this->app->bound('request')) {
                    try {
                        $request = $this->app->make('request');
                    } catch (\Throwable) {
                        // Request not yet resolved — continue with null
                    }
                }

                return $detector->detect($mode, $request);
            } catch (\Throwable $e) {
                Log::warning('PlatformModeServiceProvider: runtime platform detection failed, defaulting to WebsiteWindows', [
                    'error' => $e->getMessage(),
                ]);

                return RuntimePlatform::WebsiteWindows;
            }
        });

        // Force resolution so the singleton is stored immediately
        $this->app->make('runtime.platform');

        // Configure the asset manager for the detected platform mode
        /** @var PlatformAssetManager $assetManager */
        $assetManager = $this->app->make(PlatformAssetManager::class);
        $assetManager->configure($mode);

        // Development-only logging — only emit in local environment
        if ($this->app->environment('local')) {
            /** @var RuntimePlatform $runtimePlatform */
            $runtimePlatform = $this->app->make('runtime.platform');

            Log::info('Platform detected', [
                'mode' => $mode->value,
                'mode_label' => $mode->label(),
                'runtime_platform' => $runtimePlatform->value,
                'runtime_label' => $runtimePlatform->label(),
                'asset_directory' => $assetManager->getBuildDirectory(),
                'environment_file' => $mode->environmentFile(),
            ]);
        }
    }
}
