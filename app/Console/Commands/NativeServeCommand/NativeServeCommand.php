<?php

namespace App\Console\Commands\NativeServeCommand;

use App\Enums\PlatformMode\PlatformMode;
use App\Support\Platform\PlatformDependencyValidator\PlatformDependencyValidator;
use Illuminate\Console\Command;

/**
 * Validation wrapper for `php artisan native:serve` in Desktop platform mode.
 *
 * Checks that all required NativePHP Electron dependencies are installed
 * before delegating to the real `native:serve` command. If dependencies are
 * missing, displays a human-readable error with installation instructions.
 *
 * Requirements: 7.2, 7.3, 7.5, 7.6
 */
class NativeServeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:native:serve
        {--without-queue : Do not run the queue worker}
        {--without-schedule : Do not run the scheduler}
        {--quiet-logs : Suppress Laravel logs from console output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate Desktop dependencies, then serve the NativePHP Desktop app (delegates to `native:serve`)';

    /**
     * Execute the console command.
     *
     * Validates that NativePHP Electron dependencies are installed before
     * passing control to `native:serve`. Returns exit code 1 with an
     * informative error message when dependencies are missing.
     */
    public function handle(PlatformDependencyValidator $validator): int
    {
        $missing = $validator->validateDependencies(PlatformMode::Desktop);

        if (! empty($missing)) {
            $this->displayMissingDependenciesError(PlatformMode::Desktop, $missing);

            return self::FAILURE;
        }

        return $this->call('native:serve', array_filter([
            '--without-queue' => $this->option('without-queue'),
            '--without-schedule' => $this->option('without-schedule'),
            '--quiet-logs' => $this->option('quiet-logs'),
        ], fn ($v) => $v !== false));
    }

    /**
     * Display a formatted error message listing missing dependencies and
     * providing installation instructions.
     *
     * @param  string[]  $missing
     */
    protected function displayMissingDependenciesError(PlatformMode $mode, array $missing): void
    {
        $this->error("Error: Required dependencies for {$mode->label()} mode are not installed.");
        $this->line('');
        $this->line('Missing packages:');

        foreach ($missing as $package) {
            $this->line("  - {$package}");
        }

        $this->line('');
        $this->line('To install, run:');
        $this->line('  composer require '.implode(' ', $missing));
    }
}
