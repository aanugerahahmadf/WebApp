<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `admins` -- identitas tim internal, DIPISAH dari `users`.
 *
 * Kenapa tabel terpisah, bukan satu tabel dengan role:
 *
 *   Tabel `users` = identitas PERONAL shoppers + tamu. Dipakai guard `web`,
 *   dipakai Sanctum untuk token HP, dan dipakai panel user + welcome.
 *   Kolomnya bercampur: KYC/KTP, budget pernikahan, wishlist, cart, app lock.
 *
 *   Tabel `admins` = identitas PEGAWAI. Dipakai guard `admin`, tidak pernah
 *   menyentuh Sanctum, tidak punya KYC/budget/wishlist/cart sama sekali.
 *   Kolomnya hanya yang memang dibutuhkan buat kerja: nama, akun, jabatan,
 *   divisi, status aktif, dan jejak login.
 *
 * Pemisahan ini yang membuat `User::canAccessPanel()` tidak pernah bisa
 * mengembalikan `true` untuk panel admin: tabel `users` tidak punya baris yang
 * bisa memegang role ber-guard `admin`, jadi tidak ada jalur dari akun
 * belanja ke panel admin.
 *
 * `user_id` di tabel ini = tautan OPSIONAL ke baris `users` milik admin yang
 * juga ingin punya identitas belanja (lihat `php artisan admin:grant-storefront`).
 * Nullable dan TIDAK diisi oleh seeder -- pemisahan tetap utuh secara default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table): void {
            $table->id();

            // ── Identitas ──────────────────────────────────────────────
            $table->string('full_name');
            $table->string('first_name')->nullable();
            $table->string('mid_name')->nullable();
            $table->string('last_name')->nullable();

            // ── Akun ───────────────────────────────────────────────────
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->string('avatar_url')->nullable();
            $table->string('avatar')->nullable();

            // ── Jabatan ────────────────────────────────────────────────
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            // Jabatan dilihat, bukan dipakai sebagai penentu akses.
            // Penentu akses tetap role Spatie (super_admin / data_lens / ...).
            $table->string('position')->nullable();
            $table->string('department')->nullable();
            $table->text('notes')->nullable();

            // ── Status ─────────────────────────────────────────────────
            // Menonaktifkan super_admin dicek di level aplikasi, bukan lewat
            // kolom, karena tidak ada satu nilai SQL yang bisa mencegah UPDATE
            // tanpa trigger.
            $table->boolean('active_status')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->string('last_login_user_agent')->nullable();

            // Tautan opsional ke identitas belanja (lihat docblock atas).
            //
            // Tanpa ->after(): modifier itu hanya valid di ALTER TABLE, dan
            // MySQL menolaknya di dalam CREATE TABLE dengan error sintaks.
            $table->unsignedBigInteger('user_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index('active_status');
        });

        // Tabel token reset kata sandi untuk tim internal.
        //
        // Dipisah dari `password_reset_tokens` (yang milik `users`) supaya
        // broker `admins` di config/auth.php tidak bisa salah mengirim token ke
        // email yang punya akun belanja -- dan sebaliknya.
        Schema::create('admin_password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_password_reset_tokens');
        Schema::dropIfExists('admins');
    }
};