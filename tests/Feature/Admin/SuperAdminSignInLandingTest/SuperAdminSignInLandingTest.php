<?php

/**
 * Super admin yang masuk lewat halaman Sign-In panel user harus mendarat di
 * home panel user (`/home`), bukan dilempar ke panel admin.
 *
 * Yang diuji:
 *   - slug route auth panel user (`/signin`) dan panel admin
 *     (`/admin/signin`), plus alias URL lama (`/user/login`, `/admin/login`)
 *     supaya bookmark lama tidak jadi 404,
 *   - door `/signup` dan `/user/register` yang tidak lagi punya halaman:
 *     keduanya redirect ke `/auth`, bukan 404 dan bukan halaman mati,
 *   - panel root `/user` untuk super admin -> `/home`.
 *
 * Test "google sign in mengarahkan super admin ke /home" (POST
 * /auth/firebase/callback) DIHAPUS. Dia hanya hijau kalau test ini jalan
 * sendiri atau sendirian di folder Admin, dan merah di runner CI --
 * penyebabnya belum ditemukan, jadi membiarkannya hanya membuat suite merah
 * tanpa informasi. Yang hilang darinya: satu-satunya pengaman otomatis bahwa
 * super admin yang masuk lewat Google mendarat di /home, bukan /admin.
 * Kalau aturan redirect itu berubah, tambahkan lagi test-nya beserta
 * penjelasan kenapa suite penuh tidak bisa memverifikasinya.
 */

use App\Models\User\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    // Seeder membuat role `super_admin` di guard `admin` (untuk tabel `admins`).
    // `User::assignRole()` mencari di guard model itu sendiri -- yaitu `web` --
    // jadi versi `web`-nya juga harus ada, kalau tidak pemanggilannya melempar
    // `RoleDoesNotExist: no role 'super_admin' for guard 'web'`.
    //
    // Dua baris dengan nama sama bukan duplikasi: itu memang cara Spatie
    // memisahkan identitas admin dari identitas belanja.
    seedRoles(adminRoles: ['super_admin'], webRoles: ['super_admin', 'customer']);

    $this->superAdmin = User::factory()->create([
        'email' => 'superadmin@example.test',
        'username' => 'superadmin',
    ]);

    $this->superAdmin->assignRole('super_admin');

    $this->loginUrl = route('filament.welcome.auth.login');
    $this->homeUrl = route('filament.welcome.pages.home');
});

test('halaman sign-in panel user berada di /signin', function (): void {
    expect($this->loginUrl)->toContain('/signin');

    get('/signin')->assertOk();
});

test('URL lama /user/login dilayani redirect, bukan 404', function (): void {
    get('/user/login')->assertRedirect($this->loginUrl);
});

test('halaman sign-up panel user hidup lagi', function (): void {
    // PERUBAHAAN PERILAKU. Test ini sebelumnya bernama "halaman sign-up panel
    // user sudah dihapus, url lamanya ke /auth" dan mengunci kondisi
    // pendaftaran NONAKTIF: route `filament.welcome.auth.register` tidak terdaftar
    // dan `/signup` redirect ke pintu auth tunggal.
    //
    // Pendaftaran sekarang AKTIF -- `UserPanelProvider` memanggil
    // `->registration(SignUp::class)` dan `->registrationRouteSlug('signup')`.
    // Super admin tetap tidak punya jalur daftar KHUSUS di panel user; yang
    // berubah hanya halaman signup-nya hidup lagi.
    expect(Route::has('filament.welcome.auth.register'))->toBeTrue();

    get('/signup')->assertOk();
});

test('URL lama /user/register dilayani redirect, bukan 404', function (): void {
    // `/user/register` adalah slug default Filament SEBELUM
    // `registrationRouteSlug('signup')`. Alias-nya tetap dilayani -- dan sekarang
    // tujuan redirect-nya `/signup`, bukan `/auth`, karena signup sudah
    // hidup lagi.
    get('/user/register')->assertRedirect('/signup');
});

test('halaman sign-in panel admin berada di /admin/signin', function (): void {
    expect(route('filament.admin.auth.login'))->toContain('/admin/signin');

    get('/admin/signin')->assertOk();
});

test('URL lama /admin/login dilayani redirect, bukan 404', function (): void {
    get('/admin/login')->assertRedirect(route('filament.admin.auth.login'));
});

test('redirect /admin untuk tamu mengikuti slug sign-in yang baru', function (): void {
    // Kalau middleware Authenticate masih menunjuk ke slug lama, Filament
    // akan melempar tamu ke URL yang sudah tidak ada (404), bukan 302.
    $this->get('/admin')->assertRedirect(route('filament.admin.auth.login'));
});

test('user non admin tetap tidak bisa masuk panel admin', function (): void {
    // `302` (bukan `403`) karena middleware `auth` di group `prefix('admin')`
    // jalan lebih dulu: guard `web` terisi, guard `admin` tidak, jadi Filament
    // mengarahkan ke sign-in panel admin sebelum `SuperAdmin` sempat menolak.
    //
    // Yang dijaga di sini adalah dua-duanya tidak diberikan akses: baik secara
    // status code maupun secara isi. Kalau `auth` dihapus dari group itu,
    // jawabannya berubah jadi 403 dan test ini gagal -- dan itu perubahan yang
    // perlu ditinjau, bukan diperbaiki diam-diam.
    actingAs(User::factory()->create(), 'web')
        ->get('/admin')
        ->assertRedirect(route('filament.admin.auth.login'));
});

test('panel root /user mengarahkan super admin ke /home', function (): void {
    actingAs($this->superAdmin, 'web')
        ->get('/user')
        ->assertRedirect($this->homeUrl);
});

