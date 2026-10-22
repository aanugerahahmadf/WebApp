<?php

namespace Database\Seeders;

use App\Models\Admin\Admin;
use Illuminate\Database\Seeder;

/**
 * Pastikan SEMUA admin team bisa masuk ke panel USER (/user/home).
 *
 * Seeder ini:
 * 1. Memastikan setiap admin punya identitas belanja (users) yang tertaut.
 * 2. Mematikan 2FA untuk semua admin-linked users.
 * 3. Memastikan password admin-linked users sama dengan password admin.
 *
 * Jalankan setelah SuperAdminSeeder:
 *   php artisan db:seed --class=AdminUserPanelAccessSeeder
 */
class AdminUserPanelAccessSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('--- Ensuring Admin User Panel Access ---');

        $admins = Admin::all();

        foreach ($admins as $admin) {
            // Skip nonaktif
            if (! $admin->active_status) {
                $this->command->line("  <comment>⊘</comment> Skip (nonaktif): {$admin->username}");
                continue;
            }

            // Pastikan ada identitas belanja
            $user = $admin->ensureStorefrontIdentity($admin->password);

            // Matikan 2FA untuk admin team
            $user->forceFill([
                'two_factor_type' => \MixCode\FilamentMulti2fa\Enums\TwoFactorAuthType::None->value,
                'two_factor_enabled' => false,
            ])->save();

            $this->command->line("  <info>✓</info> {$admin->username} → {$user->email} (2FA: OFF)");
        }

        $this->command->info('--- Admin User Panel Access Complete ---');
    }
}
