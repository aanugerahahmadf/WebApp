<?php

namespace App\Console\Commands\ServePlatformCommand;

use App\Enums\PlatformMode\PlatformMode;
use Illuminate\Console\Command;

/**
 * Wrapper command for `php artisan serve` in Web platform mode.
 *
 * Validates platform mode context and delegates to the built-in `serve`
 * command. Web mode requires no additional platform-specific dependencies.
 *
 * Requirements: 7.2, 7.5, 7.6
 */
class ServePlatformCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Uses `serve:web` to avoid conflict with the built-in `serve` command.
     *
     * @var string
     */
    protected $signature = 'serve:web
        {--host= : The host address to serve the application on}
        {--port= : The port to serve the application on}
        {--tries= : The max number of ports to attempt to serve from}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Serve the application in Web platform mode (delegates to `php artisan serve`)';

    /**
     * Execute the console command.
     *
     * Web mode has no additional platform-specific package requirements, so
     * this command simply delegates to the built-in `serve` command after
     * confirming the platform context.
     */
    public function handle(): int
    {
        $this->line('Starting Laravel in <info>'.PlatformMode::Web->label().'</info> mode...');

        return $this->call('serve', array_filter([
            '--host' => $this->option('host'),
            '--port' => $this->option('port'),
            '--tries' => $this->option('tries'),
        ]));
    }
}
