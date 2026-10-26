<?php

namespace App\Console\Commands\SecurityBackup;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SecurityBackup extends Command
{
    protected $signature = 'security:backup {--only-db : Backup database saja}';

    protected $description = 'Menjalankan backup recovery yang terkontrol dan dapat diaudit';

    public function handle(): int
    {
        if (! config('security.backup_enabled', env('SECURITY_BACKUP_ENABLED', false))) {
            $this->warn('Backup keamanan belum diaktifkan. Set SECURITY_BACKUP_ENABLED=true.');
            return self::SUCCESS;
        }

        $arguments = $this->option('only-db') ? ['--only-db' => true] : [];
        $exitCode = Artisan::call('backup:run', $arguments);
        $this->output->write(Artisan::output());

        return $exitCode;
    }
}
