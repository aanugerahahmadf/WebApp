<?php

namespace App\Support\AdminAccess;

use App\Models\Admin\Admin;
use Illuminate\Support\Facades\Auth;

/**
 * Satu-satunya tempat yang tahu "role mana boleh apa" di panel admin.
 *
 * Kenapa perlu kelas ini, bukan sekadar `hasPermissionTo()` yang disebar di
 * setiap resource:
 *
 * 1. Filament sudah punya titik gate yang benar. `Resource::canViewAny()` +
 *    `canEdit()/canCreate()/canDelete()` dan `Page::canAccess()` dipakai
 *    Filament untuk dua hal sekaligus: menyembunyikan entri navigasi DAN
 *    memblokir URL yang diketik langsung. Jadi gate cukup ditulis di resource,
 *    tidak perlu `navigationItems()` manual di panel provider.
 *
 * 2. Manual navigation item di panel provider rapuh. Navigation item menuntut
 *    FQCN class halaman, dan project ini memakai konvensi folder-per-class --
 *    jadi FQCN halaman selalu berakhir dengan nama class-nya
 *    (`...\Pages\ListCatalogs\ListCatalogs`, bukan `...\Pages\ListCatalogs`).
 *    Salah tulis satu karakter =&gt; `Class not found` =&gt; 500 di seluruh
 *    panel, karena navigasi dirender di layout utama. Resource::getUrl() dan
 *    canViewAny() tidak punya masalah itu.
 *
 * 3. super_admin selalu lolos, jadi dia tidak perlu permission satu pun --
 *    kode seeding boleh fokus pada role lain saja.
 */
class AdminAccess
{
    public const SUPER_ADMIN = 'super_admin';

    /**
     * Role yang boleh masuk ke panel admin.
     *
     * Daftar ini di sini BUKAN satu-satunya -- User::canAccessPanel() dan
     * middleware SuperAdmin punya daftarnya masing-masing supaya dua file itu
     * tidak bergantung pada class Support. Kalau menambah role admin, perbarui
     * ketiganya.
     *
     * @var list<string>
     */
    public const ADMIN_ROLES = [
        self::SUPER_ADMIN,
        'data_lens',
        'data_analyst',
        'cyber_security',
        'database',
        'content_manager',
        'order_manager',
        'finance_manager',
        'data_architect',
        'data_engineer',
        'data_mining',
        'data_scientist',
        'security_emergency',
        'security',
        'devops_engineer',
        'data_manager',
        'data_development',
        'data_storage',
        'data_specialist',
        'data_annotation_staff',
        'etl_engineer',
        'database_administrator',
        'system_analyst',
        'network_engineer',
        'site_reliability_engineer',
        'cloud_engineer',
        'cloud_computing',
        'system_security',
        'systems_manager',
        'it_support',
        'technical_writer',
        'technical_support_engineer',
    ];

    /**
     * Label jabatan yang boleh dipilih di panel admin.
     *
     * Nama teknis role tetap stabil untuk permission dan database; label ini
     * adalah teks yang ditampilkan kepada administrator.
     *
     * @var array<string, string>
     */
    public const ROLE_LABELS = [
        'super_admin' => 'Super Admin',
        'data_lens' => 'Data Lens',
        'data_analyst' => 'Analis Data',
        'cyber_security' => 'Cyber Security / Keamanan Siber',
        'database' => 'Database',
        'content_manager' => 'Pengelola Konten',
        'order_manager' => 'Pengelola Pesanan',
        'finance_manager' => 'Pengelola Keuangan',
        'data_architect' => 'Arsitek Data',
        'data_engineer' => 'Engineer Data',
        'data_mining' => 'Penambangan Data',
        'data_scientist' => 'Ilmuan Data',
        'security_emergency' => 'Security Emergency',
        'security' => 'Security',
        'devops_engineer' => 'DevOps Engineer',
        'data_manager' => 'Pengelola Data',
        'data_development' => 'Pengembangan Data',
        'data_storage' => 'Penyimpanan Data',
        'data_specialist' => 'Posisi Data Lainnya',
        'data_annotation_staff' => 'Staf Anotasi Data',
        'etl_engineer' => 'ETL Engineer',
        'database_administrator' => 'Database Administrator',
        'system_analyst' => 'Analis Sistem',
        'network_engineer' => 'Engineer Jaringan',
        'site_reliability_engineer' => 'Site Reliability Engineer',
        'cloud_engineer' => 'Insinyur Komputasi Awan',
        'cloud_computing' => 'Komputasi Awan',
        'system_security' => 'Keamanan Sistem',
        'systems_manager' => 'Pengelola Sistem',
        'it_support' => 'IT Support',
        'technical_writer' => 'Technical Writer',
        'technical_support_engineer' => 'Technical Support Engineer',
    ];

    /** @return array<string, string> */
    public static function roleOptions(): array
    {
        return array_intersect_key(self::ROLE_LABELS, array_flip(self::ADMIN_ROLES));
    }

    /**
     * Permission "lihat saja" -- cukup untuk masuk daftar dan membuka detail.
     *
     * @var list<string>
     */
    public const VIEW_PERMISSIONS = [
        'view_catalog',
        'view_categories',
        'view_packages',
        'view_orders',
        'view_transactions',
        'view_users',
        'view_wishlists',
        'view_vouchers',
        'view_reviews',
        'view_reports',
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
    ];

    /**
     * Permission "boleh mengubah" -- create / edit / delete.
     *
     * @var list<string>
     */
    public const WRITE_PERMISSIONS = [
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
        'manage_security',
        'manage_database',
        'manage_migrations',
        'manage_seeders',
        'manage_factories',
        'manage_api_keys',
        'manage_customizations',
        'manage_settings',
        'manage_backups',
        'export_data',
        'import_data',
    ];

    /**
     * Capabilities for the operational positions. These are deliberately
     * separate from CRUD permissions: a data engineer must be able to run an
     * ETL job without becoming a catalog editor, and an SRE must be able to
     * inspect health/incident data without receiving database-write access.
     *
     * @var array<string, list<string>>
     */
    public const OPERATIONAL_PERMISSIONS = [
        'data_architecture' => ['view_data_dictionary', 'manage_data_contracts'],
        'data_engineering' => ['view_data_dictionary', 'run_data_pipelines', 'manage_data_quality'],
        'data_mining' => ['view_analytics', 'run_data_queries', 'export_analytical_data'],
        'data_science' => ['view_analytics', 'run_data_queries', 'manage_experiments'],
        'security_incident_response' => ['view_security_events', 'manage_security_incidents', 'manage_security'],
        'cyber_security' => ['view_security_events', 'manage_security_incidents', 'manage_security_policies'],
        'data_management' => ['view_data_dictionary', 'manage_data_quality', 'export_data'],
        'data_development' => ['view_data_dictionary', 'manage_data_contracts', 'run_data_pipelines'],
        'data_storage' => ['view_storage', 'manage_storage', 'view_database'],
        'data_specialist' => ['view_data_dictionary', 'run_data_queries', 'export_analytical_data'],
        'data_annotation' => ['view_annotation_jobs', 'manage_annotation_jobs'],
        'etl' => ['run_data_pipelines', 'manage_pipeline_schedules', 'view_pipeline_runs'],
        'database_administration' => ['view_database', 'manage_database', 'manage_database_backups', 'manage_database_migrations'],
        'system_analysis' => ['view_system_inventory', 'view_audit_logs', 'export_analytical_data'],
        'network_engineering' => ['view_network_health', 'manage_network_configuration'],
        'sre' => ['view_system_health', 'view_system_inventory', 'manage_incidents', 'manage_deployments'],
        'cloud_engineering' => ['view_cloud_resources', 'manage_cloud_resources'],
        'cloud_operations' => ['view_cloud_resources', 'manage_cloud_deployments'],
        'system_security' => ['view_security_events', 'manage_security_policies', 'manage_security'],
        'systems_management' => ['view_system_inventory', 'manage_system_configuration', 'manage_incidents'],
        'it_support' => ['view_support_tickets', 'manage_support_tickets'],
        'technical_writing' => ['view_documentation', 'manage_documentation'],
        'technical_support' => ['view_support_tickets', 'manage_support_tickets', 'view_system_health'],
    ];

    /**
     * Least-privilege capability assignment for every operational position.
     * The seeder consumes this map, so adding a position without granting a
     * real capability is impossible to miss during review.
     *
     * @var array<string, list<string>>
     */
    public const OPERATIONAL_ROLE_CAPABILITIES = [
        'data_architect' => ['data_architecture'],
        'data_engineer' => ['data_engineering'],
        'data_mining' => ['data_mining'],
        'data_scientist' => ['data_science'],
        'security_emergency' => ['security_incident_response'],
        'security' => ['cyber_security'],
        'devops_engineer' => ['sre'],
        'data_manager' => ['data_management'],
        'data_development' => ['data_development'],
        'data_storage' => ['data_storage'],
        'data_specialist' => ['data_specialist'],
        'data_annotation_staff' => ['data_annotation'],
        'etl_engineer' => ['etl'],
        'database_administrator' => ['database_administration'],
        'system_analyst' => ['system_analysis'],
        'network_engineer' => ['network_engineering'],
        'site_reliability_engineer' => ['sre'],
        'cloud_engineer' => ['cloud_engineering'],
        'cloud_computing' => ['cloud_operations'],
        'system_security' => ['system_security'],
        'systems_manager' => ['systems_management'],
        'it_support' => ['it_support'],
        'technical_writer' => ['technical_writing'],
        'technical_support_engineer' => ['technical_support'],
    ];

    /** @return list<string> */
    public static function operationalPermissions(): array
    {
        return array_values(array_unique(array_merge(...array_values(self::OPERATIONAL_PERMISSIONS))));
    }

    /** @return list<string> */
    public static function permissionsForOperationalRole(string $role): array
    {
        $capabilities = self::OPERATIONAL_ROLE_CAPABILITIES[$role] ?? [];

        return array_values(array_unique(array_merge(...array_map(
            fn (string $capability): array => self::OPERATIONAL_PERMISSIONS[$capability] ?? [],
            $capabilities,
        ))));
    }

    /** @return list<string> */
    public static function capabilitiesForRole(string $role): array
    {
        return self::permissionsForOperationalRole($role);
    }

    /** @return list<string> */
    public static function currentCapabilities(): array
    {
        $admin = static::user();
        if ($admin === null) {
            return [];
        }

        if ($admin->hasRole(static::SUPER_ADMIN)) {
            return array_values(array_unique(array_merge(
                self::VIEW_PERMISSIONS,
                self::WRITE_PERMISSIONS,
                self::operationalPermissions(),
            )));
        }

        return array_values(array_filter(
            array_values(array_unique(array_merge(
                self::VIEW_PERMISSIONS,
                self::WRITE_PERMISSIONS,
                self::operationalPermissions(),
            ))),
            fn (string $permission): bool => $admin->checkPermissionTo($permission),
        ));
    }

    /**
     * Gate generik: true kalau user punya SATU SAJA dari permission yang
     * diberikan.
     *
     * Pakai `checkPermissionTo()`, bukan `hasPermissionTo()`: yang pertama
     * mengembalikan false kalau permission-nya belum di-seed, yang kedua
     * melempar `PermissionDoesNotExist`. Di environment yang seeder-nya belum
     * dijalankan (misalnya seedRole.php), yang kedua akan membuat seluruh
     * panel 500.
     *
     * Signature variadic supaya bisa dipanggil `allows('a', 'b')` tanpa array.
     */
    public static function allows(string ...$permissions): bool
    {
        $admin = static::user();

        if ($admin === null) {
            return false;
        }

        if ($admin->hasRole(static::SUPER_ADMIN)) {
            return true;
        }

        foreach ($permissions as $permission) {
            if ($admin->checkPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sama seperti allows(), tapi menolak kalau admin belum masuk sama sekali --
     * dipakai untuk keputusan navigasi yang boleh tampil apa adanya.
     */
    public static function requiresAuth(): bool
    {
        return static::user() !== null;
    }

    /**
     * Admin yang sedang masuk di panel ini, atau null.
     *
     * Membaca guard `admin`, bukan guard default `web`.
     *
     * Kenapa tidak `Auth::user()`: sejak tabel `admins` dipisah dari
     * `users` dan panel admin memakai `->authGuard('admin')`, guard default
     * `web` tidak pernah berisi admin di dalam panel admin. `Auth::user()`
     * di sini akan selalu null -- dan karena setiap `canViewAny()` /
     * `canEdit()` di seluruh resource memanggil `allows()`, hasilnya seluruh
     * entri navigasi panel admin hilang dan URL yang diketik langsung kena 403,
     * dengan hilangnya DENY tanpa jejak.
     *
     * Tipe balik diketatkan ke `Admin` (bukan Authenticatable) supaya static
     * analyzer dan IDE langsung menunjukkan kalau ada yang masih
     * menganggap model ini `User` di sini.
     */
    public static function user(): ?Admin
    {
        $admin = Auth::guard('admin')->user();

        return $admin instanceof Admin ? $admin : null;
    }

    /**
     * Nama role milik admin, untuk ditampilkan di UI.
     *
     * @return list<string>
     */
    public static function roleNames(): array
    {
        return static::user()?->roles->pluck('name')->values()->all() ?? [];
    }
}
