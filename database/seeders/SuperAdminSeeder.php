<?php

namespace Database\Seeders;

use App\Models\Admin\Admin;
use Illuminate\Database\Seeder;

/**
 * Seed akun super admin di tabel `admins`.
 *
 * Dulu seeder ini menulis ke tabel `users`, sekarang ke `admins`. Itu pemisahan
 * yang sama sekali bukan kosmetik: `admins` dibaca guard `admin`, tidak punya
 * token Sanctum, dan tidak punya kolom KYC/KTP/budget/wishlist/cart. Akun
 * belanja tidak akan pernah bisa membuka panel admin karena tidak ada baris
 * di `users` yang punya role ber-guard 'admin'.
 *
 * Idempoten: aman dijalankan berulang kali. Kalau baris sudah ada, yang di-update
 * hanya field yang memang milik seed (nama, username, email, kata sandi,
 * verifikasi email, status aktif) -- kolom jejak login dan `user_id` tidak
 * disentuh supaya tidak menghapus data operasional.
 *
 * Kolom `user_id` SENGAJA tidak diisi di sini. Hubungan ke identitas belanja
 * bersifat opsional dan harus diminta secara eksplisit lewat
 * `php artisan admin:grant-storefront`, supaya tabel `users` tetap khusus
 * shopper dan tamu secara default.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('--- Seeding Super Admin ---');

        $email = env('SUPER_ADMIN_EMAIL', 'devimakeup.wo@gmail.com');
        $username = env('SUPER_ADMIN_USERNAME', 'superadmin');
        $password = env('SUPER_ADMIN_PASSWORD', '@Superadmin123');

        $attributes = [
            'full_name' => 'Super Admin',
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'username' => $username,
            'email' => $email,
            // Password DISINI mentah (bukan Hash::make) karena model Admin
            // punya cast 'password' => 'hashed' yang akan hash otomatis.
            // JANGAN Hash::make di sini — akan jadi double-hash dan login gagal.
            'password' => $password,
            'email_verified_at' => now(),
            'avatar_url' => 'https://i.pravatar.cc/300?u=superadmin',
            'phone' => '6281234567890',
            'whatsapp' => '6281234567890',
            'position' => 'Super Administrator',
            'department' => 'Engineering',
            'active_status' => true,
        ];

        // Dicari per-username DAN per-email, karena keduanya unique di tabel
        // `admins`. Kalau seed lama pernah menulis email berbeda, akun itu
        // ditemukan lewat username lalu email-nya dikoreksi di sini -- bukan
        // membuat baris kedua yang bentrok dengan unique index.
        $admin = Admin::withTrashed()
            ->where('username', $username)
            ->orWhere('email', $email)
            ->first();

        if ($admin) {
            // Restore dulu: kalau pernah di-soft-delete, akunnya harus hidup
            // lagi, bukan sekadar di-update (update tidak menghidupkan row).
            if ($admin->trashed()) {
                $admin->restore();
            }

            $admin->update($attributes);

            $this->command->line("  <info>✓</info> Super Admin diperbarui: {$admin->email}");
        } else {
            $admin = Admin::create($attributes);

            $this->command->line("  <info>✓</info> Super Admin dibuat: {$admin->email}");
        }

        // Role harus sudah ada. RolesAndPermissionsSeeder dipanggil lebih dulu
        // oleh DatabaseSeeder; kalau dijalankan sendiri (`db:seed --class`),
        // buatkan di sini supaya tidak gagal diam-diam.
        if (! \Spatie\Permission\Models\Role::findByName('super_admin', 'admin')) {
            $this->command->warn('  <comment>!</comment> Role super_admin belum ada -- jalankan RolesAndPermissionsSeeder lebih dulu.');
        } elseif (! $admin->hasRole('super_admin')) {
            // Guard eksplisit: role super_admin hidup di guard 'admin'.
            $admin->assignRole('super_admin');
            $this->command->line('  <info>✓</info> Role: super_admin');
        }

        $this->command->line('  <info>→</info> Login: /admin/signin');
        $this->command->line('  <info>→</info> Email/username: ' . $username . ' / ' . $email);
        $this->command->line('  <info>→</info> Kata sandi: ' . $password);
        $this->command->line('  <info>→</info> Ganti kata sandi ini sebelum dipakai di lingkungan nyata.');

        $this->command->info('--- Super Admin Seeding Complete ---');
    }
}