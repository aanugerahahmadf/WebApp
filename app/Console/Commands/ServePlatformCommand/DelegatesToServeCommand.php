<?php

namespace App\Console\Commands\ServePlatformCommand;

use App\Enums\PlatformMode\PlatformMode;

/**
 * Shared delegation for the `serve:web` / `serve:mobile` / `serve:desktop`
 * commands.
 *
 * The Capacitor shells in `app/Capacitor/*` are plain HTTP clients: they point
 * their WebView at this server (see each shell's `capacitor.config.json`) and
 * load whichever Vite bundle is built into `public/build/{web,mobile,desktop}`.
 * There is no embedded PHP runtime and no per-mode native binary any more, so
 * all three modes serve through the same built-in `serve` command — the only
 * difference is which `.env.{mode}` file gets loaded and which build directory
 * `PlatformAssetManager` reads, both of which are keyed off `platform.mode`
 * (detected by `PlatformCommandDetector` from the command name).
 *
 * So each command only declares its own name and mode, and hands off here.
 *
 *   php artisan serve:web      → PlatformMode::Web      → build/web
 *   php artisan serve:mobile   → PlatformMode::Mobile   → build/mobile
 *   php artisan serve:desktop  → PlatformMode::Desktop  → build/desktop
 */
trait DelegatesToServeCommand
{
    /**
     * Delegate to the built-in `serve` command for the given platform mode.
     */
    protected function serveInMode(PlatformMode $mode): int
    {
        $this->line('Starting Laravel in <info>'.$mode->label().'</info> mode...');

        return $this->call('serve', array_filter([
            '--host' => $this->option('host'),
            '--port' => $this->option('port'),
            '--tries' => $this->option('tries'),
        ], fn ($value): bool => $value !== null && $value !== ''));
    }
}
