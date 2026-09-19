<?php

namespace App\Console\Commands\NativePlatformRunCommand;

use App\Enums\PlatformMode\PlatformMode;
use App\Support\Platform\PlatformDependencyValidator\PlatformDependencyValidator;
use Illuminate\Console\Command;

/**
 * Validation wrapper for `php artisan native:run` in Mobile platform mode.
 *
 * Checks that all required NativePHP Mobile dependencies are installed before
 * delegating to the real `native:run` command. If dependencies are missing,
 * displays a human-readable error with installation instructions.
 *
 * Requirements: 7.2, 7.3, 7.5, 7.6
 */
class NativePlatformRunCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:native:run
        {os? : Platform to run (android/a or ios/i)}
        {udid? : ADB device serial (auto-detects physical device if omitted)}
        {--build=debug : debug|release|bundle}
        {--W|watch : Enable hot reloading during development}
        {--start-url= : Set the initial URL/path to load on app start}
        {--no-tty : Disable TTY mode for non-interactive environments}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate Mobile dependencies, then run the NativePHP Mobile app (delegates to `native:run`)';

    /**
     * Execute the console command.
     *
     * Validates that NativePHP Mobile dependencies are installed before
     * passing control to `native:run`. Returns exit code 1 with an
     * informative error message when dependencies are missing.
     */
    public function handle(PlatformDependencyValidator $validator): int
    {
        $missing = $validator->validateDependencies(PlatformMode::Mobile);

        if (! empty($missing)) {
            $this->displayMissingDependenciesError(PlatformMode::Mobile, $missing);

            return self::FAILURE;
        }

        return $this->call('native:run', array_filter([
            'os' => $this->argument('os'),
            'udid' => $this->argument('udid'),
            '--build' => $this->option('build'),
            '--watch' => $this->option('watch'),
            '--start-url' => $this->option('start-url'),
            '--no-tty' => $this->option('no-tty'),
        ], fn ($v) => $v !== null && $v !== false));
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
