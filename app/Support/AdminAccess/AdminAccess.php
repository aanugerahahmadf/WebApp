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
    ];

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