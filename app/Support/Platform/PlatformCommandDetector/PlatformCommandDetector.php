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
        // An explicit override always wins: `PLATFORM_MODE=mobile php artisan serve`
        // is how a dev points a shell at the server without picking a wrapper
        // command. Useful for queue workers, which have no meaningful argv[1].
        $override = self::fromEnvironment();

        if ($override !== null) {
            return $override;
        }

        $argv = $_SERVER['argv'] ?? [];

        if (self::isRunningArtisan($argv)) {
            return self::detectFromCommand($argv);
        }

        return self::detectFromRuntime();
    }

    /**
     * Read an explicit mode from `PLATFORM_MODE`, if set and recognised.
     */
    private static function fromEnvironment(): ?PlatformMode
    {
        $value = $_ENV['PLATFORM_MODE'] ?? $_SERVER['PLATFORM_MODE'] ?? null;

        if (! is_string($value) || $value === '') {
            return null;
        }

        return PlatformMode::tryFrom(strtolower(trim($value)));
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
     * Maps the platform serve wrappers to their corresponding platform modes:
     * - serve / serve:web     → Web
     * - serve:mobile          → Mobile
     * - serve:desktop         → Desktop
     *
     * Anything else runs in Web mode: there is no longer an embedded runtime
     * that a command could spin up, so no other command implies a mode.
     */
    private static function detectFromCommand(array $argv): PlatformMode
    {
        $command = $argv[1] ?? null;

        return match ($command) {
            'serve:mobile' => PlatformMode::Mobile,
            'serve:desktop' => PlatformMode::Desktop,
            default => PlatformMode::Web,
        };
    }

    /**
     * Detect platform mode from the current runtime environment.
     *
     * Used when not running via Artisan CLI (e.g. HTTP requests, queue workers
     * started through a supervisor, PHP-FPM).
     *
     * The Capacitor shells are ordinary HTTP clients — each one's
     * `capacitor.config.json` points its WebView at this server over the
     * network — so there is no runtime marker that distinguishes an app shell
     * from a browser here. That distinction is made per-request by
     * `RuntimePlatform`/`AppPlatform` from the User-Agent and the
     * `CAPACITOR_PLATFORM` hint, not by the process-wide mode. Which *bundle*
     * to serve stays a build/serve-time choice (`serve:mobile`, `serve:desktop`).
     */
    private static function detectFromRuntime(): PlatformMode
    {
        return PlatformMode::Web;
    }
}
