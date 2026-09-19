<?php

namespace App\Support\Platform\PlatformDependencyValidator;

use App\Enums\PlatformMode\PlatformMode;
use Native\Laravel\Facades\Window;
use Native\Laravel\NativeServiceProvider;
use Native\Mobile\Dialog;

class PlatformDependencyValidator
{
    /**
     * Validate that all required packages for the given platform mode are installed.
     *
     * Returns an array of missing Composer package names. An empty array means
     * all required dependencies are present.
     *
     * Requirements: 7.1, 7.2, 7.3, 7.4
     *
     * @param  PlatformMode  $mode  The platform mode to validate dependencies for.
     * @return string[] Array of missing package names (e.g. ['nativephp/electron']).
     */
    public function validateDependencies(PlatformMode $mode): array
    {
        $missing = [];

        if ($mode === PlatformMode::Desktop) {
            if (! class_exists(Window::class)) {
                $missing[] = 'nativephp/electron';
            }

            if (! class_exists(NativeServiceProvider::class)) {
                $missing[] = 'nativephp/laravel';
            }
        }

        if ($mode === PlatformMode::Mobile) {
            if (! class_exists(Dialog::class)) {
                $missing[] = 'nativephp/mobile';
            }
        }

        // Web mode requires no additional platform-specific packages.
        return $missing;
    }
}
