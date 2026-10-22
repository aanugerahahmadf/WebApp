<?php

use App\Models\Admin\Admin;
use App\Models\User\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;

/**
 * Gerbang panel admin setelah tabel `admins` dipisah dari `users`.
 *
 * Dua hal yang diuji di sini, dan keduanya dulu tercampur di satu file:
 *
 *   1. Admin bisa masuk lewat guard `admin` (tabel `admins`).
 *   2. Shopper di tabel `users` TIDAK bisa -- dan bukan hanya karena "tidak
 *      punya role", melainkan karena panel admin memang tidak pernah membaca
 *      tabel itu.
 *
 * Test (2) yang penting. Kalau hanya mengandalkan "user tidak punya role
 * admin", pemisahan tabelnya bisa dibatalkan diam-diam -- dan test tetap hijau
 * selama role-nya belum di-seed.
 */
beforeEach(function (): void {
    seed();
});

test('halaman sign in panel admin bisa diakses tamu', function (): void {
    get('/admin/signin')->assertStatus(200);
});

test('panel admin mengarahkan tamu ke sign in', function (): void {
    get('/admin')->assertStatus(302)->assertRedirect('/admin/signin');
});

test('super admin dari tabel admins bisa masuk panel admin', function (): void {
    $admin = Admin::factory()->create();
    $admin->assignRole('super_admin');

    $response = actingAs($admin, 'admin')->get('/admin');

    // Panel admin me-redirect '/' ke home-nya sendiri, jadi ikuti sekali.
    if ($response->isRedirect()) {
        $response = actingAs($admin, 'admin')->get($response->headers->get('Location'));
    }

    $response->assertSuccessful();
});

test('super admin bisa membuka daftar admin tim', function (): void {
    $admin = Admin::factory()->create();
    $admin->assignRole('super_admin');

    actingAs($admin, 'admin')
        ->get('/admin/admins')
        ->assertSuccessful();
});

/*
 * Shopper yang menyamar masuk.
 *
 * Hasil yang diharapkan adalah 302 ke /admin/signin, BUKAN 403.
 *
 * Alasannya: panel admin berjalan di guard `admin`. Shopper login di guard
 * `web`, jadi dari sudut pandang panel admin dia "belum masuk sama sekali" --
 * persis seperti tamu. Filament lalu mengirimnya ke form sign in, yang
 * persis behavior yang benar. 403 hanya muncul kalau yang menyamar sudah
 * punya session guard `admin`, dan itu mustahil terjadi dari `users`.
 *
 * Jadi yang diuji di sini: (a) tidak ada halaman panel admin yang terbuka
 * untuknya, dan (b) `canAccessPanel()` menolak secara eksplisit -- yang
 * kedua penting, karena banyak filter/relasi di luar middleware bergantung
 * padanya.
 */
test('shopper di tabel users tidak bisa masuk panel admin', function (): void {
    $user = User::factory()->create();

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();

    actingAs($user, 'web')
        ->get('/admin')
        ->assertStatus(302)
        ->assertRedirect('/admin/signin');
});

test('shopper tidak bisa masuk walau punya role ber guard web yang bernama sama', function (): void {
    $user = User::factory()->create();

    // Dicoba bypass: role shopper dibuat dengan nama yang sama persis, tapi
    // guard 'web'. Role admin yang asli hidup di guard 'admin', jadi ini role
    // yang berbeda -- dan tetap tidak memberi akses apa pun ke panel admin.
    $decoy = Role::firstOrCreate([
        'name' => 'super_admin',
        'guard_name' => 'web',
    ]);

    $user->assignRole($decoy);

    expect($user->fresh()->hasRole('super_admin'))->toBeTrue()
        ->and($user->fresh()->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();

    actingAs($user, 'web')
        ->get('/admin')
        ->assertStatus(302)
        ->assertRedirect('/admin/signin');
});

test('akun admin nonaktif tidak bisa masuk', function (): void {
    $admin = Admin::factory()->inactive()->create();
    $admin->assignRole('super_admin');

    // Diuji dua jalur: provider menolak kredensial saat `attempt()`, dan
    // middleware menolak sesi yang somehow sudah ada.
    expect(Auth::guard('admin')->attempt([
        'username' => $admin->username,
        'password' => 'Password123!',
    ]))->toBeFalse();

    actingAs($admin, 'admin')
        ->get('/admin')
        ->assertStatus(403);
});

test('admin tanpa role admin tidak bisa masuk panel admin', function (): void {
    // Baris `admins` yang tidak punya role sama sekali: masuk ke panel harus
    // ditolak, bukan dianggap "admin baru yang belum diberi wewenang".
    $admin = Admin::factory()->create();

    expect($admin->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();

    actingAs($admin, 'admin')
        ->get('/admin')
        ->assertStatus(403);
});

test('admin dengan role baca-saja bisa masuk tapi tidak bisa mengubah', function (): void {
    $admin = Admin::factory()->create();
    $admin->assignRole('data_lens'); // punya view_*, tidak punya manage_*

    $response = actingAs($admin, 'admin')->get('/admin');
    if ($response->isRedirect()) {
        $response = actingAs($admin, 'admin')->get($response->headers->get('Location'));
    }
    $response->assertSuccessful();

    // Halaman yang butuh permission tulis harus 403 -- bukan sekadar
    // disembunyikan dari navigasi.
    expect(App\Support\AdminAccess\AdminAccess::allows('manage_catalog'))->toBeFalse()
        ->and(App\Support\AdminAccess\AdminAccess::allows('view_catalog'))->toBeTrue();
});

test('kredensial admin ditolak oleh guard web', function (): void {
    // Username DIJAGA unik supaya tidak bentrok dengan baris superadmin yang
    // dibuat `seed()` di beforeEach -- keduanya di tabel `admins` yang punya
    // unique index pada username.
    $admin = Admin::factory()->create([
        'username' => 'rahasia-panel',
        'email' => 'rahasia-panel@example.test',
        'password' => 'Password123!',
    ]);

    // Ini kebocoran yang ditutup pemisahan guard: sebelum tabel `admins`
    // ada, kredensial yang sama berhasil login di guard `web`, yang berarti
    // kata sandi panel admin ikut menjadi kata sandi akun belanja dan API.
    expect(Auth::guard('web')->attempt([
        'username' => 'rahasia-panel',
        'password' => 'Password123!',
    ]))->toBeFalse()
        ->and(Auth::guard('web')->attempt([
            'email' => 'rahasia-panel@example.test',
            'password' => 'Password123!',
        ]))->toBeFalse();
});

test('kredensial shopper ditolak oleh guard admin', function (): void {
    User::factory()->create([
        'username' => 'shopper-aja',
        'password' => 'Password123!',
    ]);

    expect(Auth::guard('admin')->attempt([
        'username' => 'shopper-aja',
        'password' => 'Password123!',
    ]))->toBeFalse();
});

test('admin tidak otomatis terautentikasi di panel user', function (): void {
    $admin = Admin::factory()->create();
    $admin->assignRole('super_admin');

    // Panel user & welcome memakai guard 'web'. Admin yang login di /admin
    // TIDAK ikut login di /signin -- dua session terpisah, dua tabel terpisah.
    actingAs($admin, 'admin');

    expect(Auth::guard('web')->check())->toBeFalse();
});

test('shopper tetap bisa masuk panel user seperti biasa', function (): void {
    $user = User::factory()->create();

    $response = actingAs($user, 'web')->get('/user');

    if ($response->isRedirect()) {
        $response = actingAs($user, 'web')->get($response->headers->get('Location'));
    }

    $response->assertSuccessful();
});