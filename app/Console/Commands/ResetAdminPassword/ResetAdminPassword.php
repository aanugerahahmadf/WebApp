<?php

namespace App\Console\Commands\ResetAdminPassword;

use App\Models\Admin\Admin;
use App\Support\PasswordPolicy\PasswordPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Set ulang kata sandi admin (tabel `admins`).
 *
 * Kenapa perlu command ini: `app:init-admin` membuat admin di tabel
 * `users` -- itu versi LAMA, sebelum tabel `admins` dipisah. Akun admin
 * yang sekarang tidak bisa dibuat lewat command itu, dan kalau kata
 * sandinya lupa/berubah, tidak ada jalan lain selain menyunting database
 * secara manual.
 *
 * Command iniHANYA mengubah kata sandi; role, status aktif, dan data
 * lain tidak disentuh.
 */
class ResetAdminPassword extends Command
{
    protected $signature = 'admin:reset-password {username} {password}';

    protected $description = 'Set ulang kata sandi admin (tabel admins) berdasarkan username.';

    public function handle(): int
    {
        $username = (string) $this->argument('username');
        $password = (string) $this->argument('password');

        $violations = PasswordPolicy::violations($password);

        if ($violations !== []) {
            foreach ($violations as $violation) {
                $this->error($violation);
            }
            $this->line('  Contoh yang memenuhi: @Superadmin123');
            $this->line('  (huruf besar + huruf kecil + angka + simbol, minimal '.PasswordPolicy::MIN_LENGTH.' karakter)');

            return self::FAILURE;
        }

        $admin = Admin::query()
            ->where('username', $username)
            ->orWhere('email', $username)
            ->first();

        if (! $admin) {
            $this->error("Admin dengan username/email '{$username}' tidak ada.");

            return self::FAILURE;
        }

        $admin->forceFill(['password' => Hash::make($password)])->save();

        $this->info("Kata sandi admin '{$admin->username}' ({$admin->email}) berhasil diganti.");

        if (! $admin->isActive()) {
            $this->warn('  PERHATIAN: akun ini NONAKTIF -- sign in tetap ditolak.');
        }

        if (! $admin->hasRole('super_admin')) {
            $this->warn('  PERHATIAN: akun ini tidak punya role super_admin.');
        }

        return self::SUCCESS;
    }
}
