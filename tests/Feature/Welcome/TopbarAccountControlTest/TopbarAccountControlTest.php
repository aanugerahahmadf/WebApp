<?php

/**
 * Kontrol akun di topbar panel Welcome (`Welcome.components.topbar-auth-actions`).
 *
 * Topbar storefront tidak memakai user menu bawaan Filament: Filament hanya
 * merender user menu untuk user yang sudah login, sedangkan halaman ini justru
 * milik tamu. Karena itu panelnya me-render kontrol sendiri lewat render hook
 * `panels::global-search.after`, dan markup user menu bawaan dihapus lewat
 * override panel-scoped (lihat
 * `Welcome/panel-overrides/filament-panels/components/user-menu.blade.php`).
 *
 * Bentuk kontrolnya:
 *
 *   tamu        -> tombol ikon (user profile) yang membuka dropdown, isinya satu
 *                  item "Sign In To Account" / "Masuk ke Akun" menuju `/auth`
 *   sudah login -> ikon yang sama, langsung jadi link ke `/home`
 *
 * Test lama (`WelcomeStorefrontGuestAccessTest`) hanya memeriksa bahwa label dan
 * URL `/auth` ADA di halaman. Itu lemah: kedua string itu bisa muncul dari
 * mana saja di markup, termasuk dari elemen yang tidak bisa diklik. File ini
 * karena itu mengunci ATRIBUT-nya -- `href`, `aria-label`, `type`, dan
 * `x-tooltip` -- sehingga kesalahan di `WelcomePanelProvider` (URL yang salah
 * diteruskan ke view) langsung ketahuan.
 *
 * Yang juga dikunci: hanya ada SATU kontrol akun di topbar, dan tidak ada user
 * menu/avatar bawaan Filament yang bocor di panel ini.
 */

use App\Filament\User\Auth\Auth\Auth as UserAuth;
use App\Models\User\User;
use App\Support\AppPlatform\AppPlatform;
use App\Enums\RuntimePlatform\RuntimePlatform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Sidebar/topbar berbeda per platform; dikunci ke web agar render-nya
    // deterministik dan yang diuji memang topbar.
    AppPlatform::fake(RuntimePlatform::WebsiteWindows);

    $this->customer = User::factory()->create();
    $this->customer->assignRole(Role::firstOrCreate(['name' => 'customer']));
});

afterEach(function (): void {
    AppPlatform::fake(RuntimePlatform::WebsiteWindows);
});

/**
 * Potong markup dari tag pembuka yang memuat `$needle` sampai `>` berikutnya,
 * supaya assertion bisa memeriksa atribut sebuah elemen -- bukan sekadar
 * substring di seluruh halaman.
 *
 * Batas kirinya dicari sebagai "<" TERAKHIR sebelum needle, bukan dengan jendela
 * karakter tetap: elemen ini punya atribut `x-tooltip` yang beberapa ratus
 * karakter, sehingga jendela 200 karakter bisa mulai di tengah tooltip dan
 * tag pembukanya terpotong -- persis bentuk kegagalan yang membuat test ini
 * salah membaca markup.
 */
function openingTag(string $html, string $needle): string
{
    $needlePosition = strpos($html, $needle);

    if ($needlePosition === false) {
        return '';
    }

    $start = strrpos(substr($html, 0, $needlePosition), '<');
    $end = strpos($html, '>', $needlePosition);

    return ($start === false || $end === false) ? '' : substr($html, $start, $end - $start + 1);
}

test('a guest gets an icon button that opens a dropdown, not a bare link', function (): void {
    $html = $this->get('/home')->assertOk()->getContent();

    // Trigger harus tombol (memang butuh JS untuk membuka dropdown), bukan
    // <a>: kalau jadi link, tidak ada dropdown-nya.
    $trigger = openingTag($html, 'fi-welcome-auth-actions-account');

    expect($trigger)->toContain('<button')
        ->toContain('type="button"')
        ->toContain('aria-label="'.e(__('Sign In To Account')).'"');
});

test('the guest dropdown item links to the auth landing page', function (): void {
    $html = $this->get('/home')->assertOk()->getContent();

    // Item dropdown harus <a> dengan href ke /auth -- inilah pintu auth
    // tunggal storefront. Kalau href-nya berubah ke /signin langsung,
    // halaman auth (pilihan dua pintu) jadi tidak terpakai.
    expect($html)->toContain('href="'.UserAuth::getUrl().'"')
        ->and($html)->toContain(e(__('Sign In To Account')))
        // Dan memang halaman auth, bukan form login.
        ->and(UserAuth::getUrl())->not->toBe(route('filament.welcome.auth.login'));
});

test('the guest control links to the auth landing, never to the sign in form', function (): void {
    // PERUBAHAAN PERILAKU. Test ini sebelumnya bernama "the guest control never
    // links to registration" dan melarang `/signup` muncul di topbar,
    // dengan alasan pendaftaran email/password dinonaktifkan sehingga tidak ada
    // route register untuk ditunjuk.
    //
    // Pendaftaran sekarang AKTIF dan item "Sign Up Sekarang" memang ada di
    // dropdown akun -- itu disengaja, bukan kebocoran. Yang tetap prohibitions
    // adalah link LANGSUNG ke form sign-in: pintu yang benar untuk tamu adalah
    // `/auth` (Sign In + Google), bukan `/signin`.
    //
    // Perbedaan ini penting: tanpa melarang `/signup`, test ini akan hijau
    // bahkan kalau topbar sudah menawarkan form langsung. Yang dijaga adalah
    // pintasan ke form, bukan ketiadaan item pendaftaran.
    $html = $this->get('/home')->assertOk()->getContent();

    expect($html)
        ->not->toContain('href="'.route('filament.welcome.auth.login').'"')
        // Pendaftaran ada sebagai ITEM DROPDOWN, bukan sebagai pintasan di
        // luar dropdown -- dua-duanya menunjuk halaman yang hidup.
        ->toContain('/signup')
        // Slug alias lama tidak boleh muncul: yang kanonik adalah `signup`.
        ->not->toContain('/user/register');
});

test('there is exactly one account control in the topbar', function (): void {
    // Dua kontrol = dua tempat untuk keadaan yang sama, dan topbar
    // yang melebar. Hitung kemunculan class kontrol, bukan substring label.
    $html = $this->get('/home')->assertOk()->getContent();

    expect(substr_count($html, 'fi-welcome-auth-actions-account'))->toBe(1);
});

/*
 * DUA TEST BERIKUTNYA DIJALANKAN DI HALAMAN KATALOG.
 *
 * `WelcomePanelProvider` memasang topbar dengan `->topbar(TopNavigation::always())`,
 * jadi baris topbar -- dan hook `panels::global-search.after` yang memasang ikon
 * akun di dalamnya -- dirender di SEMUA halaman, termasuk katalog.
 *
 * Tapi yang tetap benar: kontrol akun untuk TAMU (`fi-welcome-auth-actions-account`)
 * hanya dirender untuk pengunjung yang belum login. User yang sudah login
 * mendapat user menu bawaan Filament, bukan kontrol tamu itu.
 *
 * Test lama menginspeksi `/flowerdecorationscatalog` dengan alasan
 * "/home milik tamu". Dua-duanya benar: home memang milik tamu, dan
 * user yang sudah login memang dialihkan ke `/home`.
 *
 * Yang dikunci di sini:
 *   1. Di katalog, kontrol akun TAMU memang tidak ada -- user login
 *      mendapatkan user menu, bukan tombol "Masuk ke Akun".
 *   2. Di /home, ikon untuk tamu menunjuk ke halaman auth.
 */
test('the account control is absent outside the storefront home', function (): void {
    actingAs($this->customer, 'web');

    $html = $this->get('/flowerdecorationscatalog')->assertOk()->getContent();

    expect($html)->not->toContain('fi-welcome-auth-actions-account');
});

test('the signed in visitor keeps the storefront home and gets the account control there', function (): void {
    // Dulu /home mengarahkan user yang sudah sign in ke home panel user.
    // Panel user sudah dilebur ke panel welcome, jadi redirect itu dihapus:
    // halaman ini sengaja terbuka untuk tamu DAN yang sudah sign in, dan
    // topbar sekarang ada di semua halaman -- termasuk di sini.
    actingAs($this->customer, 'web');

    $this->get('/home')
        ->assertOk()
        ->assertSee('fi-user-menu', escape: false);
});

test('the guest dropdown label follows the language switcher', function (): void {
    // Label keluar dari `__()`, jadi ia harus berubah bersama locale. Kalau
    // kunci terjemahannya hilang dari salah satu file, halaman Inggris akan
    // diam-diam menampilkan teks Indonesia tanpa ada test yang gagal.
    $this->get('/home?locale=id')
        ->assertOk()
        ->assertSee(e(__('Sign In To Account')));

    $this->get('/home?locale=en')
        ->assertOk()
        ->assertSee(e(__('Sign In To Account')));
});

test('the guest dropdown shows the indonesian wording in indonesian', function (): void {
    // Dipisah dari test di atas supaya kedua wording-nya dikunci sebagai literal:
    // inilah yang membuat language switcher terlihat berfungsi.
    $this->get('/home?locale=id')
        ->assertOk()
        ->assertSee('Masuk ke Akun', escape: false);
});

test('filaments own user menu never leaks into this panel', function (): void {
    // Override panel-scoped di user-menu.blade.php yang membuang markup bawaan.
    // Kalau override itu hilang dari registrasi AppServiceProvider, user menu
    // Filament muncul kembali -- dengan avatar yang tidak bisa diklik untuk tamu.
    foreach (['/home', '/flowerdecorationscatalog'] as $url) {
        expect($this->get($url)->assertOk()->getContent())
            ->not->toContain('fi-user-menu', escape: false);
    }
});

test('the theme switcher survives next to the account control', function (): void {
    // Keduanya berbagi baris topbar. Switcher tema diambil dari user menu
    // Filament, jadi di panel ini harus di-render sendiri -- kalau ikut hilang
    // bersama user menu, tamu kehilangan kontrol terang/gelap.
    $html = $this->get('/home')->assertOk()->getContent();

    expect($html)->toContain('fi-theme-switcher', escape: false)
        ->and($html)->toContain('fi-welcome-auth-actions', escape: false);
});

