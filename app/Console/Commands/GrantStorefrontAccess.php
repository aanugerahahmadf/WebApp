<?php

namespace App\Console\Commands;

use App\Models\Admin\Admin;
use App\Models\User\User;
use App\Support\ChatStaff\ChatStaff;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Beri super admin identitas BELANJA opsional.
 *
 * Kenapa perintah ini ada, dan kenapa tidak otomatis di seeder:
 *
 *   Panel `user` (dan fitur chat) bekerja dengan baris `users`: panel user
 *   menyamar lewat guard `web`, dan `Inbox::user_ids` menyimpan `users.id`.
 *   Akun di tabel `admins` tidak bisa menggantikan itu -- tidak ada session
 *   guard `web` yang mengenali model `Admin`, dan `users.id` di inbox tidak
 *   mungkin menunjuk ke baris di tabel lain.
 *
 *   Jadi kalau seorang super admin perlu BELAJAN (untuk preview panel user,
 *   atau supaya bisa membalas chat sebagai akun yang punya `users.id`),
 *   dia butuh satu baris `users`. Baris itu adalah identitas kedua yang
 *   disengaja, bukan penggabungan: ia tetap shopper, tidak punya role admin,
 *   dan tidak memberi akses ke panel admin apa pun.
 *
 *   Kalau tidak diperlukan, jangan jalankan perintah ini -- tabel `users`
 *   tetap khusus shopper dan tamu.
 *
 * Pemisahan tetap utuh setelah perintah ini dijalankan:
 *   - akses admin tetap READ dari `admins` + guard `admin`;
 *   - `admins.user_id` hanya pointer, dan `user_id` di `users` tidak ada;
 *   - tidak ada role ber-guard 'admin' yang menempel pada baris `users`.
 */
class GrantStorefrontAccess extends Command
{
    protected $signature = 'admin:grant-storefront
                            {admin? : username ATAU email admin di tabel admins (default: superadmin)}
                            {--email= : Email untuk identitas belanja (default: sama dengan email admin)}
                            {--password= : Kata sandi untuk identitas belanja (default: di-generate)}
                            {--revoke : Cabut tautan dan hapus identitas belanja yang tertaut}';

    protected $description = 'Buat (atau cabut) identitas belanja opsional untuk sebuah akun admin';

    public function handle(): int
    {
        $identifier = $this->argument('admin') ?: 'superadmin';

        $admin = Admin::query()
            ->where('username', $identifier)
            ->orWhere('email', $identifier)
            ->first();

        if (! $admin) {
            $this->error("Admin tidak ditemukan: {$identifier}");
            $this->line('  Cari dengan username atau email di tabel <info>admins</info>.');

            return self::FAILURE;
        }

        return $this->option('revoke')
            ? $this->revoke($admin)
            : $this->grant($admin);
    }

    protected function grant(Admin $admin): int
    {
        // Sudah tertaut? Jangan buat baris kedua.
        if ($admin->user()->exists()) {
            $this->info("Sudah punya identitas belanja: {$admin->user->email}");
            $this->line('  Gunakan <info>--revoke</info> lebih dulu kalau ingin mengganti.');

            return self::SUCCESS;
        }

        $email = $this->option('email') ?: $admin->email;
        $password = $this->option('password') ?: Str::password(16);

        // Email-nya sudah dipakai shopper lain? Jangan menabrak -- identitas
        // belanja harus unik dan tidak boleh diam-diam merebut akun orang.
        if ($user = User::where('email', $email)->first()) {
            if ($user->id === $admin->user_id) {
                return self::SUCCESS;
            }

            $this->error("Email sudah dipakai akun lain: {$email}");
            $this->line('  Pakai <info>--email=</info> yang berbeda.');

            return self::FAILURE;
        }

        $user = User::create([
            'full_name' => $admin->full_name,
            'first_name' => $admin->first_name,
            'mid_name' => $admin->mid_name,
            'last_name' => $admin->last_name,
            // Username dibuat unik dengan sufiks: `admin.username` sudah unik
            // di tabel `admins`, tapi `users` punya baris lain yang mungkin
            // memakai nama sama, dan dua username tidak boleh kembar.
            'username' => $this->uniqueUsername($admin->username),
            'email' => $email,
            // Foto yang sama dengan akun admin -- tanpa ini topbar
            // storefront hanya menampilkan inisial ("SA").
            //
            // `getRawOriginal`, bukan `$admin->avatar_url`: accessor
            // avatarUrl() menormalkan URL ke origin request yang sedang
            // berjalan, dan yang harus tersimpan adalah nilai mentahnya.
            'avatar_url' => $admin->getRawOriginal('avatar_url'),
            // Password MENTAH — model User punya cast 'password' => 'hashed'
            // yang akan hash otomatis. JANGAN Hash::make di sini (double-hash).
            'password' => $password,
            'email_verified_at' => now(),
            'active_status' => true,
            // Ditandai supaya baris ini bisa dibedakan dari shopper biasa di
            // panel user. BUKAN penentu akses: role tetap tidak ada di sini.
            'occupation' => $admin->position ?: 'Admin',
        ]);

        $admin->forceFill(['user_id' => $user->id])->save();

        // Chat menyimpan hasil "cari admin" di cache. Tanpa Forget di sini,
        // grant baru tetap terlihat tidak berhasil sampai cache dibersihkan --
        // dan karena gejalanya hanya "chat tidak jalan", penyebabnya sulit
        // ketahuan.
        ChatStaff::forget();

        $this->info('Identitas belanja dibuat dan ditautkan.');
        $this->table(
            ['Panel', 'URL', 'Kredensial'],
            [
                ['Admin', '/admin/signin', $admin->username . ' / ' . $admin->email],
                ['User', '/signin', $user->username . ' / ' . $user->email],
            ]
        );
        $this->line('  Kata sandi identitas belanja: <info>'.$password.'</info>');
        $this->line('  <comment>Akses panel admin tetap dibaca dari tabel admins, bukan dari akun ini.</comment>');

        return self::SUCCESS;
    }

    protected function revoke(Admin $admin): int
    {
        $user = $admin->user;

        if (! $user) {
            $this->info('Tidak ada identitas belanja yang tertaut.');

            return self::SUCCESS;
        }

        $admin->forceFill(['user_id' => null])->save();
        ChatStaff::forget();

        $this->info("Tautan dilepas dari admin: {$admin->username}");

        if ($this->confirm("Hapus juga baris users-nya ({$user->email})?", false)) {
            $user->delete();
            $this->line('  Baris users dihapus (soft delete).');
        } else {
            $this->line('  Baris users dipertahankan; tautan sudah dilepas.');
        }

        return self::SUCCESS;
    }

    /**
     * `admin.superadmin` -> `superadmin.staff`, dan seterusnya sampai bebas.
     */
    protected function uniqueUsername(string $base): string
    {
        $base = Str::slug($base, '.') ?: 'staff';
        $candidate = $base.'.staff';
        $i = 2;

        while (User::where('username', $candidate)->exists()) {
            $candidate = $base.'.staff'.$i++;
        }

        return $candidate;
    }
}