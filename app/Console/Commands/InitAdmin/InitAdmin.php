<?php

namespace App\Console\Commands\InitAdmin;

use App\Models\User\User;
use App\Support\PasswordPolicy\PasswordPolicy;
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

        // Command ini tidak lewat HTTP, jadi tidak ada validator yang jalan
        // diotomatiskan. Tanpa cek di sini, admin pertama bisa dibuat
        // dengan kata sandi 1 karakter lewat CLI.
        $violations = PasswordPolicy::violations($password);
        if ($violations !== []) {
            foreach ($violations as $violation) {
                $this->error($violation);
            }
            $this->line('  Contoh yang memenuhi: @Superadmin123');
            $this->line('  (huruf besar + huruf kecil + angka + simbol, minimal '.PasswordPolicy::MIN_LENGTH.' karakter)');

            return self::FAILURE;
        }

        if (User::where('email', $email)->exists()) {
            $this->error("User with email {$email} already exists.");

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'full_name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'admin',
        ]);

        $this->info("Successfully created admin user: {$email}");

        return self::SUCCESS;
    }
}
