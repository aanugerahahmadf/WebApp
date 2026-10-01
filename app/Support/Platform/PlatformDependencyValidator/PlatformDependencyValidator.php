<?php

namespace App\Support\Platform\PlatformDependencyValidator;

use App\Enums\PlatformMode\PlatformMode;

/**
 * Validates that the workspace needed to build a platform shell is present.
 *
 * The shells in `app/Capacitor/{AdminApp,UserApp}` are Node projects, not PHP
 * packages: Capacitor compiles the same Laravel server that already runs
 * everywhere and wraps it in a WebView, so there is no Composer package to
 * require and no embedded PHP to bundle. What can be missing is the shell
 * workspace itself — the directory, its `capacitor.config.json`, or the
 * installed `node_modules` holding `@capacitor/cli`.
 *
 * Historically this class checked for `nativephp/electron`, `nativephp/laravel`
 * and `nativephp/mobile`; those are gone.
 */
class PlatformDependencyValidator
{
    /**
     * Which Capacitor shell backs each platform mode.
     *
     * @var array<string, string>
     */
    private const SHELL_BY_MODE = [
        'mobile' => 'UserApp',
        'desktop' => 'UserApp',
    ];

    /**
     * Validate the prerequisites for a platform mode.
     *
     * @return string[] Human-readable list of problems; empty means all good.
     */
    public function validateDependencies(PlatformMode $mode): array
    {
        // Web mode has no shell: the Laravel server is the whole product.
        if ($mode === PlatformMode::Web) {
            return [];
        }

        $shell = self::SHELL_BY_MODE[$mode->value] ?? 'UserApp';
        $shellPath = base_path("app/Capacitor/{$shell}");

        if (! is_dir($shellPath)) {
            return ["Capacitor shell not found: app/Capacitor/{$shell}"];
        }

        $missing = [];

        if (! file_exists($shellPath.'/capacitor.config.json')) {
            $missing[] = "Capacitor config missing: app/Capacitor/{$shell}/capacitor.config.json";
        }

        if (! is_dir($shellPath.'/node_modules/@capacitor/cli')) {
            $missing[] = "Capacitor CLI not installed. Run: cd app/Capacitor/{$shell} && npm install";
        }

        return $missing;
    }
}
