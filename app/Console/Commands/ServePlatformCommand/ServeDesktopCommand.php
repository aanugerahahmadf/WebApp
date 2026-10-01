<?php

namespace App\Console\Commands\ServePlatformCommand;

use App\Enums\PlatformMode\PlatformMode;
use Illuminate\Console\Command;

/**
 * Serves the app in Desktop mode so the Capacitor desktop shell can point at it.
 *
 * Loads `.env.desktop` and serves the `public/build/desktop` bundle.
 * See DelegatesToServeCommand for the full rationale.
 */
class ServeDesktopCommand extends Command
{
    use DelegatesToServeCommand;

    protected $signature = 'serve:desktop
        {--host= : The host address to serve the application on}
        {--port= : The port to serve the application on}
        {--tries= : The max number of ports to attempt to serve from}';

    protected $description = 'Serve the application in Desktop platform mode (for the Capacitor desktop shell)';

    public function handle(): int
    {
        return $this->serveInMode(PlatformMode::Desktop);
    }
}
