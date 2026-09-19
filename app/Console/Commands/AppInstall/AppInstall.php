<?php

namespace App\Console\Commands\AppInstall;

use Illuminate\Console\Command;

class AppInstall extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Performs initial application installation and setup (database, storage, etc).';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Application Installation Wizard...');
        $this->newLine();

        if ($this->confirm('Run migrations and seeders?', true)) {
            $this->call('migrate:fresh', ['--seed' => true]);
        }

        if ($this->confirm('Create storage link?', true)) {
            $this->call('storage:link');
        }

        if ($this->confirm('Generate application key?', true)) {
            $this->call('key:generate');
        }

        if ($this->confirm('Create initial admin user?', true)) {
            $email = $this->ask('Admin Email', 'admin@example.com');
            $password = $this->secret('Admin Password (min 8 chars)');
            $name = $this->ask('Admin Name', 'Super Admin');

            if (strlen($password) < 8) {
                $this->error('Password needs to be at least 8 characters.');
            } else {
                $this->call('app:init-admin', [
                    'email' => $email,
                    'password' => $password,
                    'name' => $name,
                ]);
            }
        }

        $this->newLine();
        $this->info('Application Installation Complete!');
    }
}
