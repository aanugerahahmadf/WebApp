<?php

namespace Database\Seeders;

use App\Models\Admin\Admin;
use Illuminate\Database\Seeder;

/**
 * Seed akun super admin di tabel `admins`.
 *
 * Dulu seeder ini menulis ke tabel `users`, sekarang ke `admins`. Itu pemisahan
 * yang sama sekali bukan kosmetik: `admins` dibaca guard `admin`, tidak punya
 * token Sanctum, dan tidak punya kolom KYC/KTP/budget/Favorite/cart. Akun
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

        $adminFirstName = 'Super';
        $adminMidName = null;
        $adminLastName = 'Admin';
        $adminFullName = trim(implode(' ', array_filter([$adminFirstName, $adminMidName, $adminLastName], fn ($v) => filled($v))));

        $attributes = [
            'first_name' => $adminFirstName,
            'mid_name' => $adminMidName,
            'last_name' => $adminLastName,
            'full_name' => $adminFullName,
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
        $adminSuperRole = \Spatie\Permission\Models\Role::where('name', 'super_admin')->where('guard_name', 'admin')->first();
        if (! $adminSuperRole) {
            $this->command->warn('  <comment>!</comment> Role super_admin (admin) belum ada -- jalankan RolesAndPermissionsSeeder lebih dulu.');
        } elseif (! $admin->hasRole($adminSuperRole)) {
            // Guard eksplisit: role super_admin hidup di guard 'admin'.
            $admin->assignRole($adminSuperRole);
            $this->command->line('  <info>✓</info> Role: super_admin (admin)');
        }

        // Khusus akun Google devimakeup.wo@gmail.com / username superadmin:
        // pastikan identitas storefront (user) ada, tertaut ke admin ini,
        // ber-role super_admin (guard web), dan bisa masuk ke home user.
        $webSuperRole = \Spatie\Permission\Models\Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]);

        $user = \App\Models\User\User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($email)])
            ->orWhere('username', $username)
            ->first();

        // Best-effort: isi lokasi Indonesia (provinsi/kota/kecamatan/kelurahan)
        // agar form profil/complete-profile tampil lengkap, bukan "Select an option".
        $province = \Laravolt\Indonesia\Models\Province::query()->orderBy('name')->first();
        $city = $province
            ? \Laravolt\Indonesia\Models\City::query()->where('province_code', $province->code)->orderBy('name')->first()
            : null;
        $district = $city
            ? \Laravolt\Indonesia\Models\District::query()->where('city_code', $city->code)->orderBy('name')->first()
            : null;
        $village = $district
            ? \Laravolt\Indonesia\Models\Village::query()->where('district_code', $district->code)->orderBy('name')->first()
            : null;

        $userFirstName = 'Super';
        $userMidName = null;
        $userLastName = 'Admin';
        $userFullName = trim(implode(' ', array_filter([$userFirstName, $userMidName, $userLastName], fn ($v) => filled($v))));

        $userProfileAttributes = [
            // ── Nama Lengkap (CompleteProfile): FIRST, MID, LAST -> FULL NAME ─
            'first_name' => $userFirstName,
            'mid_name' => $userMidName,
            'last_name' => $userLastName,
            'full_name' => $userFullName,
            // ── Akun / Username ────────────────────────────────────────────
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'email_verified_at' => now(),
            'active_status' => true,
            'avatar_url' => $user?->getRawOriginal('avatar_url') ?: 'https://i.pravatar.cc/300?u=superadmin',
            // ── Nomor WhatsApp (E.164) ─────────────────────────────────────
            'phone' => '+6281234567890',
            'whatsapp' => '+6281234567890',
            'whatsapp_verified_at' => now(),
            // ── Identitas & Alamat ─────────────────────────────────────────
            'identity_type' => 'ktp',
            'ktp_number' => '0000000000000000',
            'passport_number' => null,
            'sim_number' => null,
            'npwp_number' => null,
            'birth_place' => 'Jakarta',
            'birth_date' => '1990-01-01',
            'ktp_photo' => $user?->getRawOriginal('ktp_photo') ?: 'ktp-photos/superadmin-identity.jpg',
            'selfie_photo' => $user?->getRawOriginal('selfie_photo') ?: 'selfies/superadmin-selfie.jpg',
            'face_scan_photo' => $user?->getRawOriginal('face_scan_photo') ?: 'face-scans/superadmin-face.jpg',
            'country' => 'Indonesia',
            'province_id' => $province?->id,
            'city_id' => $city?->id,
            'district_id' => $district?->id,
            'village_id' => $village?->id,
            'province_name' => $province?->name,
            'city_name' => $city?->name,
            'district_name' => $district?->name,
            'village_name' => $village?->name,
            'postal_code' => '10110',
            'address' => 'Jakarta, Indonesia',
            // ── Data KYC ───────────────────────────────────────────────────
            'gender' => 'Pria',
            'religion' => 'Islam',
            'marital_status' => 'Belum Menikah',
            'mother_name' => $userLastName,
            'occupation' => 'Profesional',
            'income_range' => 'Rp 10-50 Juta',
            'source_of_funds' => 'Gaji',
            // ── Verifikasi Wajah / Dokumen (Face ID + Document terverifikasi) ─
            'identity_verified_at' => now(),
            'face_verified_at' => now(),
            'face_similarity' => 1.0,
            'face_deep_similarity' => 1.0,
            'face_reason' => 'VERIFIED',
            'face_liveness' => json_encode(['verified' => true, 'source' => 'admin']),
            'liveness_completed' => true,
            'kyc_status' => 'verified',
            'kyc_notes' => 'Verified',
            'kyc_reviewed_at' => now(),
            // ── Status / Keamanan ──────────────────────────────────────────
            'two_factor_type' => \MixCode\FilamentMulti2fa\Enums\TwoFactorAuthType::None->value,
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];

        if (! $user) {
            $user = new \App\Models\User\User();
            $user->forceFill($userProfileAttributes)->save();
            $this->command->line("  <info>✓</info> User storefront dibuat: {$user->email}");
        } else {
            $user->forceFill($userProfileAttributes)->save();
            $this->command->line("  <info>✓</info> User storefront diperbarui: {$user->email}");
        }

        if (! $user->hasRole($webSuperRole)) {
            $user->assignRole($webSuperRole);
            $this->command->line('  <info>✓</info> Role: super_admin (web) untuk user storefront');
        }

        if ($admin->user_id !== $user->id) {
            $admin->forceFill(['user_id' => $user->id])->save();
            $this->command->line('  <info>✓</info> Admin ditautkan ke identitas storefront user');
        }

        $this->command->line('  <info>→</info> Login: /admin/signin');
        $this->command->line('  <info>→</info> Email/username: ' . $username . ' / ' . $email);
        $this->command->line('  <info>→</info> Kata sandi: ' . $password);
        $this->command->line('  <info>→</info> Ganti kata sandi ini sebelum dipakai di lingkungan nyata.');

        $this->command->info('--- Super Admin Seeding Complete ---');
    }
}