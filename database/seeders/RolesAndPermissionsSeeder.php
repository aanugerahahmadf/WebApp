<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seed role + permission untuk DUA guard sekaligus.
 *
 * Kenapa guard_name sekarang eksplisit di setiap baris: admin pindah ke tabel
 * `admins` (App\Models\Admin\Admin) yang punya `$guard_name = 'admin'`, sedangkan
 * shopper tetap di tabel `users` dengan guard 'web'. Spatie menyimpan
 * `guard_name` di tabel `roles`, jadi role `super_admin` yang dibuat dengan
 * guard 'admin' TIDAK akan terlihat oleh `User::hasRole('super_admin')` --
 * yang selalu memfilter guard 'web'. Itulah yang membuat pemisahan tabel
 * benar-benar terkunci, dan bukan hanya tersisa konvensi.
 *
 * Konsekuensi yang perlu diperhatikan saat menambah role baru:
 *
 *   - Role tim internal  -> guard 'admin'  (lihat AdminAccess::ADMIN_ROLES)
 *   - Role shopper/tamu  -> guard 'web'
 *
 * Memberi role admin ke user biasa akan gagal diam-diam: `Role::findByName()`
 * dipakai dengan guard default, jadi pencarian tidak akan menemukan role admin.
 * Itu memang yang diinginkan.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Guard untuk role tim internal.
     */
    private const ADMIN_GUARD = 'admin';

    /**
     * Guard untuk shopper & tamu.
     */
    private const WEB_GUARD = 'web';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('--- Seeding Roles & Permissions ---');

        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ── Roles: tim internal (guard admin) ──────────────────────────
        //
        // Daftar ini harus sama dengan AdminAccess::ADMIN_ROLES. Kalau ditambah
        // di sana, tambahkan juga di sini.
        $adminRoles = [
            'super_admin',
            'data_lens',
            'data_analyst',
            'cyber_security',
            'database',
            'content_manager',
            'order_manager',
            'finance_manager',
        ];

        foreach ($adminRoles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => self::ADMIN_GUARD]);
            $this->command->line("  <info>✓</info> Role (admin): {$role}");
        }

        // ── Roles: shopper & tamu (guard web) ──────────────────────────
        $webRoles = [
            'user',
            'customer',
        ];

        foreach ($webRoles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => self::WEB_GUARD]);
            $this->command->line("  <info>✓</info> Role (web): {$role}");
        }

        // ── Permissions ────────────────────────────────────────────────────
        //
        // Permission milik guard 'admin', karena hanya admin yang memakai
        // panel admin. Tidak ada permission di guard 'web' -- shopper tidak
        // punya authorization granular, hanya "dia akun ini".
        $permissions = [
            // Data Lens — read-only access to catalog, orders, reports
            'view_catalog',
            'view_categories',
            'view_packages',
            'view_orders',
            'view_reports',
            'view_users',
            'view_transactions',
            'view_wishlists',
            'view_vouchers',
            'view_reviews',
            'view_discounts',
            'view_helps',
            'view_payment_methods',
            'view_payment_gateways',
            'view_legal',
            'view_reference_options',
            'view_admins',
            'view_audit_logs',
            'view_analytics',
            'view_database',
            'view_api_keys',
            'view_customizations',
            'view_backups',

            // Tulis / ubah / hapus
            'manage_catalog',
            'manage_categories',
            'manage_packages',
            'manage_orders',
            'manage_transactions',
            'manage_refunds',
            'manage_users',
            'manage_wishlists',
            'manage_vouchers',
            'manage_reviews',
            'manage_reports',
            'manage_discounts',
            'manage_helps',
            'manage_payment_methods',
            'manage_payment_gateways',
            'manage_legal',
            'manage_reference_options',
            'manage_admins',

            // Security & export
            'manage_security',
            'export_data',
            'import_data',
            'manage_database',
            'manage_migrations',
            'manage_seeders',
            'manage_factories',
            'manage_api_keys',
            'manage_customizations',
            'manage_settings',
            'manage_backups',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => self::ADMIN_GUARD,
            ]);
            $this->command->line("  <info>✓</info> Permission: {$permission}");
        }

        // ── Assign Permissions to Roles ────────────────────────────────────
        //
        // Daftar role di sini memakai guard 'admin', jadi permission di bawah
        // juga harus guard 'admin' -- dicocokkan lewat findByName() dengan
        // guard eksplisit.
        $rolePermissions = [
            'super_admin' => '*', // semua permission
            'data_lens' => [
                'view_catalog',
                'view_categories',
                'view_packages',
                'view_orders',
                'view_reports',
                'view_users',
                'view_transactions',
                'view_wishlists',
                'view_vouchers',
                'view_reviews',
                'view_discounts',
                'view_helps',
                'view_payment_methods',
                'view_payment_gateways',
                'view_legal',
                'view_reference_options',
            ],
            'data_analyst' => [
                'view_catalog',
                'view_categories',
                'view_packages',
                'view_orders',
                'view_reports',
                'view_users',
                'view_transactions',
                'view_wishlists',
                'view_vouchers',
                'view_reviews',
                'view_discounts',
                'view_helps',
                'view_payment_methods',
                'view_payment_gateways',
                'view_legal',
                'view_reference_options',
                'export_data',
                'view_analytics',
            ],
            'cyber_security' => [
                'manage_security',
                'view_audit_logs',
                'manage_users',
                'view_users',
                'view_catalog',
                'view_admins',
                'manage_admins',
                'view_api_keys',
                'manage_api_keys',
            ],
            'database' => [
                'manage_database',
                'view_database',
                'manage_migrations',
                'manage_seeders',
                'manage_factories',
                'manage_backups',
                'view_backups',
                'export_data',
                'import_data',
                'view_catalog',
                'view_orders',
                'view_reports',
                'view_users',
                'view_transactions',
                'manage_catalog',
                'manage_orders',
            ],
            'content_manager' => [
                'view_catalog',
                'manage_catalog',
                'view_categories',
                'manage_categories',
                'view_packages',
                'manage_packages',
                'view_discounts',
                'manage_discounts',
                'view_vouchers',
                'manage_vouchers',
                'view_reviews',
                'manage_reviews',
                'view_helps',
                'manage_helps',
                'view_legal',
                'manage_legal',
                'view_customizations',
                'manage_customizations',
                'manage_settings',
            ],
            'order_manager' => [
                'view_orders',
                'manage_orders',
                'view_transactions',
                'manage_transactions',
                'manage_refunds',
                'view_catalog',
                'view_packages',
                'view_users',
                'view_reports',
            ],
            'finance_manager' => [
                'view_transactions',
                'manage_transactions',
                'manage_refunds',
                'view_orders',
                'view_reports',
                'export_data',
                'view_analytics',
                'view_payment_methods',
                'manage_payment_methods',
                'view_payment_gateways',
                'manage_payment_gateways',
                'view_vouchers',
                'manage_vouchers',
                'view_discounts',
                'manage_discounts',
            ],
        ];

        foreach ($rolePermissions as $roleName => $perms) {
            $role = Role::findByName($roleName, self::ADMIN_GUARD);

            if ($perms === '*') {
                $role->syncPermissions(Permission::where('guard_name', self::ADMIN_GUARD)->get());
            } else {
                $role->syncPermissions($perms);
            }

            $this->command->line("  <info>✓</info> Permissions synced for role: {$roleName}");
        }

        $this->command->info('--- Roles & Permissions Seeding Complete ---');
    }
}