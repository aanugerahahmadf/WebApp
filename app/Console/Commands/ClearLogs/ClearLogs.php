<?php

namespace App\Console\Commands\ClearLogs;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ClearLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clear-logs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clears the application logs directory.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Clearing application logs...');

        $logPath = storage_path('logs');
        $files = File::glob($logPath.'/*.log');

        if (empty($files)) {
            $this->info('No log files to clear.');

            return;
        }

        foreach ($files as $file) {
            File::delete($file);
        }

        $this->info('Successfully cleared all logs.');
    }
}
