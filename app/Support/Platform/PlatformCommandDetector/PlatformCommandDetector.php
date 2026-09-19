<?php

namespace App\Support\Platform\PlatformCommandDetector;

use App\Enums\PlatformMode\PlatformMode;

class PlatformCommandDetector
{
    /**
     * Detect the platform mode from the current execution context.
     *
     * This method analyzes the command-line arguments or runtime environment
     * to determine which platform mode the application is running in.
     */
    public static function detectMode(): PlatformMode
    {
        $argv = $_SERVER['argv'] ?? [];

        if (self::isRunningArtisan($argv)) {
            return self::detectFromCommand($argv);
        }

        return self::detectFromRuntime();
    }

    /**
     * Check if the current execution is through the Artisan CLI.
     */
    private static function isRunningArtisan(array $argv): bool
    {
        return isset($argv[0]) && str_ends_with($argv[0], 'artisan');
    }

    /**
     * Detect platform mode from the executed Artisan command.
     *
     * Maps specific Artisan commands to their corresponding platform modes:
     * - serve → Web
     * - native:run → Mobile
     * - native:serve → Desktop
     */
    private static function detectFromCommand(array $argv): PlatformMode
    {
        $command = $argv[1] ?? null;

        return match (true) {
            $command === 'serve' => PlatformMode::Web,
            $command === 'native:run' => PlatformMode::Mobile,
            $command === 'native:serve' => PlatformMode::Desktop,
            str_starts_with($command ?? '', 'native:') => PlatformMode::Desktop,
            default => PlatformMode::Web
        };
    }

    /**
     * Detect platform mode from the current runtime environment.
     *
     * Used when not running via Artisan CLI (e.g., HTTP requests, native runtime).
     * Checks if we're actually running in a native context by examining environment
     * variables and the running process, not just class availability.
     */
    private static function detectFromRuntime(): PlatformMode
    {
        // Check if running in NativePHP Desktop context
        // NativePHP sets NATIVEPHP_RUNNING environment variable when active
        if (isset($_ENV['NATIVEPHP_RUNNING']) || isset($_SERVER['NATIVEPHP_RUNNING'])) {
            return PlatformMode::Desktop;
        }

        // Check if running in Laravel Native Mobile context
        // Laravel Native Mobile sets specific environment variables
        if (isset($_ENV['NATIVE_MOBILE_RUNNING']) || isset($_SERVER['NATIVE_MOBILE_RUNNING'])) {
            return PlatformMode::Mobile;
        }

        // Check if we're in an Electron environment (alternative detection)
        if (isset($_ENV['ELECTRON_RUN_AS_NODE']) || isset($_SERVER['ELECTRON_RUN_AS_NODE'])) {
            return PlatformMode::Desktop;
        }

        // Default to Web mode for HTTP requests and other contexts
        return PlatformMode::Web;
    }
}
