<?php

namespace App\Console\Commands\PlatformCacheClearCommand;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Clear cached platform detection state and platform-specific route cache.
 *
 * When switching between platform modes during development, cached data
 * (routes, config, views) from the previous mode must be cleared so that
 * the new mode initialises cleanly.
 *
 * Requirements: 8.4
 */
class PlatformCacheClearCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:clear
                            {--routes : Clear only the route cache}
                            {--config : Clear only the config cache}
                            {--all    : Clear routes, config, views, and application cache (default)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear cached platform detection state (routes, config, views, application cache)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $clearRoutes = $this->option('routes');
        $clearConfig = $this->option('config');
        $clearAll = $this->option('all') || (! $clearRoutes && ! $clearConfig);

        $this->line('');
        $this->line('<fg=cyan;options=bold>Clearing Platform Cache</>');
        $this->line('<fg=cyan>======================</>');
        $this->line('');

        if ($clearAll || $clearRoutes) {
            $this->clearRouteCache();
        }

        if ($clearAll || $clearConfig) {
            $this->clearConfigCache();
        }

        if ($clearAll) {
            $this->clearViewCache();
            $this->clearApplicationCache();
        }

        $this->line('');
        $this->info('Platform cache cleared successfully.');
        $this->line('<fg=gray>Restart your development server to apply the new platform mode.</>');
        $this->line('');

        return self::SUCCESS;
    }

    /**
     * Clear the cached route collection.
     */
    private function clearRouteCache(): void
    {
        $this->line('Clearing route cache…');

        try {
            Artisan::call('route:clear');
            $this->line('  <fg=green>✔</> Route cache cleared.');
        } catch (\Throwable $e) {
            $this->line('  <fg=yellow>⚠</> Route cache could not be cleared: '.$e->getMessage());
        }
    }

    /**
     * Clear the cached configuration.
     */
    private function clearConfigCache(): void
    {
        $this->line('Clearing config cache…');

        try {
            Artisan::call('config:clear');
            $this->line('  <fg=green>✔</> Config cache cleared.');
        } catch (\Throwable $e) {
            $this->line('  <fg=yellow>⚠</> Config cache could not be cleared: '.$e->getMessage());
        }
    }

    /**
     * Clear compiled Blade view files.
     */
    private function clearViewCache(): void
    {
        $this->line('Clearing view cache…');

        try {
            Artisan::call('view:clear');
            $this->line('  <fg=green>✔</> View cache cleared.');
        } catch (\Throwable $e) {
            $this->line('  <fg=yellow>⚠</> View cache could not be cleared: '.$e->getMessage());
        }
    }

    /**
     * Clear the application (data) cache store.
     */
    private function clearApplicationCache(): void
    {
        $this->line('Clearing application cache…');

        try {
            Artisan::call('cache:clear');
            $this->line('  <fg=green>✔</> Application cache cleared.');
        } catch (\Throwable $e) {
            $this->line('  <fg=yellow>⚠</> Application cache could not be cleared: '.$e->getMessage());
        }
    }
}
