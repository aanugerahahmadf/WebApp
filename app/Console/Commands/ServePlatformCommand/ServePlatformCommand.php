<?php

namespace App\Console\Commands\ServePlatformCommand;

use App\Enums\PlatformMode\PlatformMode;
use Illuminate\Console\Command;

/**
 * Wrapper command for `php artisan serve` in Web platform mode.
 *
 * Uses `serve:web` to avoid conflict with the built-in `serve` command.
 * See DelegatesToServeCommand for why all three modes just delegate to `serve`.
 */
class ServePlatformCommand extends Command
{
    use DelegatesToServeCommand;

    /**
     * @var string
     */
    protected $signature = 'serve:web
        {--host= : The host address to serve the application on}
        {--port= : The port to serve the application on}
        {--tries= : The max number of ports to attempt to serve from}';

    /**
     * @var string
     */
    protected $description = 'Serve the application in Web platform mode (delegates to `php artisan serve`)';

    public function handle(): int
    {
        return $this->serveInMode(PlatformMode::Web);
    }
}
