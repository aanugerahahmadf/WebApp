<?php

namespace App\Console\Commands\InitAdmin;

use App\Models\User\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class InitAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:init-admin {email} {password} {name=Administrator}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates a new administrator user if it does not already exist.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $password = $this->argument('password');
        $name = $this->argument('name');

        if (User::where('email', $email)->exists()) {
            $this->error("User with email {$email} already exists.");

            return;
        }

        $user = User::create([
            'name' => $name,
            'full_name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'admin',
        ]);

        $this->info("Successfully created admin user: {$email}");
    }
}
