<?php

namespace App\Console\Commands\ServePlatformCommand;

use App\Enums\PlatformMode\PlatformMode;
use Illuminate\Console\Command;

/**
 * Serves the app in Mobile mode so the Capacitor shell can point at it.
 *
 * Loads `.env.mobile` and serves the `public/build/mobile` bundle.
 * See DelegatesToServeCommand for the full rationale.
 */
class ServeMobileCommand extends Command
{
    use DelegatesToServeCommand;

    protected $signature = 'serve:mobile
        {--host= : The host address to serve the application on}
        {--port= : The port to serve the application on}
        {--tries= : The max number of ports to attempt to serve from}';

    protected $description = 'Serve the application in Mobile platform mode (for the Capacitor mobile shell)';

    public function handle(): int
    {
        return $this->serveInMode(PlatformMode::Mobile);
    }
}
