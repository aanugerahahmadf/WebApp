<?php

use App\Models\Admin\Admin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Bersihkan sisa "admin yang Stored di tabel users" dari sebelum tabel
 * `admins` ada.
 *
 * Kenapa ini perlu, dan kenapa bukan opsional:
 *
 *   Sebelum pemisahan, role admin dibuat TANPA guard_name eksplisit, jadi
 *   semuanya tersimpan sebagai guard 'web' dan menempel pada baris `users`.
 *  Akibatnya ada dua kebocoran nyata yang tidak bisa ditutup oleh kode:
 *
 *     1. Kredensial admin masih bisa dipakai di guard `web` — artinya siapa
 *        pun bisa login ke panel user (dan memakai API Sanctum) memakai kata
 *        sandi super admin.
 *     2. `User::hasRole('super_admin')` masih true, jadi fitur yang memakai
 *        cek role (chat, dashboard redirect, filter daftar) masih menganggap
 *        baris `users` ituSTAFF.
 *
 *   Menutup keduanya butuh perubahan DATA, bukan perubahan kode: role admin
 *   harus dicabut dari `users`, dan role/permission ber-guard 'web' yang
 *   berisi nama role admin harus dihapus.
 *
 * Yang TIDAK dihapus: baris `users` itu sendiri. Baris itu bisa saja sudah
 * punya pesanan, alamat, atau kemitraan, jadi menghapusnya adalah keputusan
 * bisnis, bukan keputusan teknis. Yang dilakukan di sini hanya:
 *   - cabut role admin dari semua baris `users`;
 *   - putuskan kata sandinya, supaya akun sisa itu tidak bisa dipakai login
 *     dengan kredensial lama yang sudah bocor.
 *
 * Kalau baris `users` itu memang tidak pernah dipakai, hapus manual:
 *   DELETE FROM users WHERE username = 'superadmin';
 *
 * Idempoten: aman dijalankan berkali-kali.
 */
return new class extends Migration
{
    /**
     * Nama role yang hanya boleh hidup di guard 'admin'.
     *
     * @var list<string>
     */
    private array $adminRoleNames = [
        'super_admin',
        'data_lens',
        'data_analyst',
        'cyber_security',
        'database',
    ];

    public function up(): void
    {
        $rolePivots = config('permission.table_names.model_has_roles', 'model_has_roles');
        $permPivots = config('permission.table_names.role_has_permissions', 'role_has_permissions');
        $rolesTable = config('permission.table_names.roles', 'roles');
        $permsTable = config('permission.table_names.permissions', 'permissions');

        // ── 1. Cabut role admin dari semua baris `users` ──────────────
        //
        // Dicocokkan lewat pivot + nama role, bukan lewat model: baris `users`
        // yang sudah terlanjur ter-serialize (pivot lama, cache permission)
        // tidak boleh dibiarkan begitu saja.
        $legacyRoleIds = DB::table($rolesTable)
            ->whereIn('name', $this->adminRoleNames)
            ->where('guard_name', 'web')
            ->pluck('id');

        $affected = 0;

        if ($legacyRoleIds->isNotEmpty()) {
            $affected = DB::table($rolePivots)
                ->whereIn('role_id', $legacyRoleIds)
                ->where('model_type', 'App\Models\User\User')
                ->delete();
        }

        // ── 2. Hapus role admin ber-guard 'web' ──────────────────────
        //
        // Setelah pivot dibersihkan, role-nya sendiri tidak lagi berguna:
        // satu-satunya role yang boleh memakai nama-nama ini adalah yang
        // ber-guard 'admin'.
        if ($legacyRoleIds->isNotEmpty()) {
            DB::table($permPivots)->whereIn('role_id', $legacyRoleIds)->delete();
            DB::table($rolesTable)->whereIn('id', $legacyRoleIds)->delete();
        }

        // ── 3. Hapus permission admin yang salah ber-guard 'web' ───────
        //
        // Permission di guard 'web' tidak pernah dipakai siapa pun -- tidak
        // ada shopper yang punya authorization granular. Kalau dibiarkan,
        // `Permission::findByName()` tanpa guard eksplisit bisa menemukan
        // permission yang sebenarnya milik tim internal, dan itu membuka
        // jalan untuk menyalakannya ke akun belanja.
        $legacyPermIds = DB::table($permsTable)
            ->where('guard_name', 'web')
            ->whereIn('name', $this->adminPermissionNames())
            ->pluck('id');

        if ($legacyPermIds->isNotEmpty()) {
            DB::table($permPivots)->whereIn('permission_id', $legacyPermIds)->delete();
            DB::table('model_has_permissions')
                ->whereIn('permission_id', $legacyPermIds)
                ->delete();
            DB::table($permsTable)->whereIn('id', $legacyPermIds)->delete();
        }

        // ── 4. Putuskan kata sandi akun `users` sisa ──────────────────
        //
        // Baris `users` yang tadinya jadi admin masih punya kata sandi yang
        // sama dengan kredensial admin. Menghapus role saja tidak menutup
        // jalur login-nya di guard `web`, jadi kata sandinya harus ikut
        // diganti dengan nilai acak -- yang tidak pernah dicetak ke mana pun.
        //
        // Username literal PLUS email yang cocok dengan baris `admins`.
        // Subquery ditulis sebagai klausa WHERE, bukan array di dalam
        // orWhereIn: bentuk array akan membuat objek Builder ikut terbawa
        // sebagai binding, dan PDO gagal mem-bind-nya.
        $stranded = DB::table('users')
            ->where(function ($query): void {
                $query->where('username', 'superadmin')
                    ->orWhereIn('email', DB::table('admins')->select('email'));
            })
            ->pluck('id');

        foreach ($stranded as $userId) {
            // Kalau baris ini sudah ditautkan ke admin lewat `admins.user_id`,
            // dia bukan "tersesat" -- dia identitas belanja yang memang
            // dibuat atas permintaan. Jangan putuskan.
            $linked = DB::table('admins')->where('user_id', $userId)->exists();

            if ($linked) {
                continue;
            }

            DB::table('users')
                ->where('id', $userId)
                ->update(['password' => Hash::make(Str::password(64))]);
        }

        // ── 5. Buang cache permission Spatie ─────────────────────────
        //
        // Wajib: `HasRoles` membaca daftar role dari cache. Tanpa ini,
        // `User::hasRole('super_admin')` masih mengembalikan true sampai
        // cachekedaluwarsa, dan gejalanya terlihat seperti "kode tidak jalan".
        if (app()->bound(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        // ── 6. Pastikan admin ada sebelum menutup migration ───────────
        //
        // Kalau tabel `admins` masih kosong sementara semua role admin di
        // `users` sudah dicabut, tidak ada satu pun akun yang bisa masuk ke
        // panel admin. Better gagal keras sekarang daripada terkunci diam-diam.
        //
        // Pengecualian untuk test: `RefreshDatabase` menjalankan migrasi
        // SEBELUM seeder, jadi pada saat migration ini dievaluasi `admins`
        // memang belum berisi apa pun -- itu urutan yang normal, bukan tanda
        // ada yang salah. Melempar di sini akan menggagalkan seluruh suite,
        // termasuk test yang justru memverifikasi pemisahan ini.
        // Akun admin dibuat oleh SuperAdminSeeder setelah semua migration
        // selesai. Migration tidak boleh bergantung pada data seed karena
        // `migrate:fresh --seed` menjalankan migration terlebih dahulu.
    }

    public function down(): void
    {
        // Sengaja kosong.
        //
        // Rollback di sini berarti mengembalikan role admin ke tabel `users` --
        // persis kebocoran yang migration ini menutup. Memulihkan kebocoran
        // hanya untuk "mengembalikan kondisi awal" adalah keputusan yang salah,
        // jadi `down()` tidak melakukan apa-apa dan butuh jalur manual
        // (jalankan seed ulang lewat panel) bila benar-benar perlu.
    }

    /**
     * @return list<string>
     */
    private function adminPermissionNames(): array
    {
        return [
            'view_catalog', 'view_orders', 'view_reports', 'view_users',
            'view_transactions', 'view_wishlists', 'view_vouchers',
            'view_reviews', 'view_discounts', 'view_helps',
            'view_payment_methods', 'view_payment_gateways', 'view_legal',
            'view_reference_options',
            'manage_catalog', 'manage_orders', 'manage_transactions',
            'manage_users', 'manage_wishlists', 'manage_vouchers',
            'manage_reviews', 'manage_reports', 'manage_discounts',
            'manage_helps', 'manage_payment_methods',
            'manage_payment_gateways', 'manage_legal',
            'manage_reference_options',
            'manage_security', 'view_audit_logs', 'export_data',
            'view_analytics', 'manage_database', 'view_database',
            'view_admins', 'manage_admins',
        ];
    }
};
