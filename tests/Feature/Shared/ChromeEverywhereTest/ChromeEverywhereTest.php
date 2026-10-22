<?php

use App\Models\Admin\Admin;
use App\Models\User\User;
use Database\Factories\CatalogFactory;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

/*
 * Chrome di SEMUA halaman.
 *
 * Panel Welcome dan User dikonfigurasi `->topNavigation(TopNavigation::always())`
 * dan `->topbar(TopNavigation::always())`, sidebar dijaga dari override view,
 * dan bottom nav HP dari MobileNav::shouldRender(). Semuanya membaca helper yang
 * sama.
 *
 * DULU panel-panel itu hanya menampilkan chrome di halaman Home. Aturan itu
 * dibalik untuk aplikasi desktop (Capacitor): topbar itu tempat pencarian
 * global, user menu, switcher bahasa/tema, dan tombol kembali -- semuanya
 * dibutuhkan di setiap layar, bukan cuma di Home. Panel admin sudah begitu
 * sejak awal.
 *
 * Test ini mengunci aturan dari LUAR (HTML hasil render), bukan dari dalam
 * (assertion atas helper-nya), karena dua hal yang mudah hilang dan tidak akan
 * tertangkap kalau hanya menguji helper:
 *
 *   1. Override view sidebar bisa tidak ter-serve untuk panel tertentu -- lalu
 *      Filament merender sidebar aslinya dan helper-nya tetap hijau. AppServiceProvider
 *      mendaftarkan folder vendor per panel, jadi pemetaan 'panel -> folder
 *      override' ikut teruji di sini lewat marker yang HANYA ada di override.
 *   2. hasTopbar() dievaluasi saat render, bukan saat panel dibangun. Kalau
 *      closure-nya diubah jadi bool, semua halaman dapat jawaban yang sama.
 *
 * CARA MENGHITUNG SIDEBAR: yang dihitung adalah ELEMEN <aside class="fi-sidebar">,
 * dan marker `fi-sidebar-switchers` yang hanya ada di override kita.
 *
 * Jangan pernah memakai string 'fi-sidebar-nav' sebagai penanda: string itu
 * muncul di dalam <script> pada layout override (selector scroll-ke-item-aktif),
 * jadi menghitungnya akan salah baca halaman tanpa sidebar sebagai halaman yang
 * masih punya sidebar.
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // `super_admin` dibuat di KEDUA guard di sini.
    //
    // User yang dipakai test ini adalah `App\Models\User\User` (panel user dan
    // welcome), dan `assignRole()` mencari role di guard model itu sendiri --
    // yaitu `web`. Jadi role `super_admin` versi `web` harus ada juga, selain
    // yang versi `admin` milik tabel `admins`. Dua baris dengan nama sama bukan
    // duplikasi: itu memang cara Spatie memisahkan identitas admin dari identitas
    // belanja.
    seedRoles(adminRoles: ['super_admin'], webRoles: ['super_admin', 'customer']);
});

function topbarElement(string $html): bool
{
    return (bool) preg_match('/<div[^>]*class="[^"]*\bfi-topbar\b/', $html);
}

function sidebarElement(string $html): bool
{
    return (bool) preg_match('/<aside[^>]*class="[^"]*\bfi-sidebar\b/', $html);
}

function breadcrumbsElement(string $html): bool
{
    return str_contains($html, 'fi-breadcrumbs');
}

function signInUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    actingAs($user, 'web');

    return $user;
}

// ─── WELCOME -- tamu, tanpa login ────────────────────────────────────────────

test('welcome home renders the full chrome', function (): void {
    $html = $this->get('/home')->assertOk()->getContent();

    expect(topbarElement($html))->toBeTrue('topbar missing on /home');
    expect(sidebarElement($html))->toBeTrue('sidebar missing on /home');
    expect($html)->toContain('fi-sidebar-switchers');
});

test('welcome home renders no breadcrumb trail', function (): void {
    $html = $this->get('/home')->assertOk()->getContent();

    // Di Home crumb 'Beranda' hanya mengulang isi heading, jadi tidak dirender.
    expect(breadcrumbsElement($html))->toBeFalse();
});

test('welcome non-home page renders the full chrome too', function (): void {
    $catalog = CatalogFactory::new()->create();

    $html = $this->get('/flowerdecorationscatalog/' . $catalog->getKey())
        ->assertOk()
        ->getContent();

    expect(topbarElement($html))->toBeTrue('topbar missing outside home');
    expect(sidebarElement($html))->toBeTrue('sidebar missing outside home');
    expect($html)->toContain('fi-sidebar-switchers');
});

test('welcome non-home page leads with a Beranda crumb pointing at welcome home', function (): void {
    $catalog = CatalogFactory::new()->create();

    $html = $this->get('/flowerdecorationscatalog/' . $catalog->getKey())
        ->assertOk()
        ->getContent();

    expect(breadcrumbsElement($html))->toBeTrue();
    expect($html)->toContain(route('filament.welcome.pages.home'));
});

test('sidebar overlay and scroll script are present outside Home too', function (): void {
    $catalog = CatalogFactory::new()->create();

    $html = $this->get('/flowerdecorationscatalog/' . $catalog->getKey())
        ->assertOk()
        ->getContent();

    // Overlay dan script scroll-ke-item-aktif ikut.sidebar, jadi mereka juga
    // muncul di luar Home sekarang.
    expect($html)->toContain('fi-sidebar-close-overlay');
    expect($html)->toContain('.fi-main-sidebar .fi-sidebar-nav');
});

test('the page content still renders outside Home', function (): void {
    // Guard terhadap "chrome hilang" yang dialokasikan dengan memotong halaman.
    //
    // `name_translations`, bukan `name`: accessor `Catalog::getNameAttribute()`
    // membaca translations lebih dulu, jadi override kolom `name` tidak
    // terlihat di halaman dan assertion ini akan gagal tanpa alasan yang
    // sebenarnya. Lihat catatan di `CatalogFactory`.
    //
    // Nama probe-nya juga sengaja tidak lagi berbunyi "Package Probe" -- kata
    // itu sisa waktu halaman paket masih ada, dan hanya menambah kebingungan
    // saat test gagal.
    $catalog = CatalogFactory::new()->create([
        'name_translations' => [
            'id' => 'Probe Konten Katalog',
            'en' => 'Catalog Content Probe',
        ],
    ]);

    // Locale dikunci ke `id` supaya assertion tidak bergantung pada `.env` mesin.
    //
    // Tanpa ini, assertion membaca `__()` dari locale aktif -- dan nilai itu
    // ikut `APP_LOCALE` di `.env`, yang berbeda antar mesin. Test yang benar
    // bisa gagal hanya karena file lokal.
    $html = $this->get('/flowerdecorationscatalog/' . $catalog->getKey() . '?locale=id')
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Probe Konten Katalog');
});

// ─── USER -- sudah login ─────────────────────────────────────────────────────

test('user home renders the full chrome', function (): void {
    signInUser();

    $html = $this->get('/home')->assertOk()->getContent();

    expect(topbarElement($html))->toBeTrue('topbar missing on /home');
    expect(sidebarElement($html))->toBeTrue('sidebar missing on /home');
    expect($html)->toContain('fi-sidebar-switchers');
});

test('user non-home page renders the full chrome too', function (): void {
    signInUser();

    $html = $this->get('/settings')->assertOk()->getContent();

    expect(topbarElement($html))->toBeTrue('topbar missing on /settings');
    expect(sidebarElement($html))->toBeTrue('sidebar missing on /settings');
});

test('user non-home resource page renders the full chrome too', function (): void {
    signInUser();

    // Halaman resource punya bentuk JS yang berbeda dari Page biasa, jadi
    // ditolak secara terpisah agar tidak hanya Page yang ikut teruji.
    $html = $this->get('/orders')->assertOk()->getContent();

    expect(topbarElement($html))->toBeTrue('topbar missing on a resource page');
    expect(sidebarElement($html))->toBeTrue('sidebar missing on a resource page');
});

test('user non-home page leads with a Beranda crumb pointing at user home', function (): void {
    signInUser();

    $html = $this->get('/settings')->assertOk()->getContent();

    expect(breadcrumbsElement($html))->toBeTrue();
    expect($html)->toContain(route('filament.welcome.pages.home'));
});

// ─── ADMIN -- harus tetap utuh; aturan ini sebenarnya sudah begitu sejak awal ──

test('admin keeps its chrome on every page', function (): void {
    seed();

    // Panel admin berjalan di guard `admin` (tabel `admins`), bukan guard `web`.
    //
    // Test lama memakai `User::factory()` + `actingAs($user, 'web')`, yang
    // selalu berakhir di halaman masuk panel admin: `Authenticate` Filament
    // menolak karena guard `admin` kosong, dan responsnya 200 dengan halaman
    // login -- yang memang punya topbar tapi tidak punya sidebar. Assertion
    // `sidebarElement()` itu gagal karena request-nya salah, bukan karena chrome
    // admin hilang.
    $admin = Admin::factory()->superAdmin()->create([
        'email_verified_at' => now(),
        'active_status'     => true,
    ]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $response = actingAs($admin, 'admin')->get('/admin');

    // Ikuti redirect, seperti AdminAccessTest: panel admin bisa memindahkan
    // super_admin ke landing page-nya sendiri lebih dulu.
    if ($response->isRedirect()) {
        $response = actingAs($admin, 'admin')->get($response->headers->get('Location'));
    }

    $html = $response->assertOk()->getContent();

    expect(topbarElement($html))->toBeTrue();
    expect(sidebarElement($html))->toBeTrue();
});
