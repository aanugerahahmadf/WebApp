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
 *                  item "Sign In To Account" / "Masuk ke Akun" menuju `/user/auth`
 *   sudah login -> ikon yang sama, langsung jadi link ke `/user/home`
 *
 * Test lama (`WelcomeStorefrontGuestAccessTest`) hanya memeriksa bahwa label dan
 * URL `/user/auth` ADA di halaman. Itu lemah: kedua string itu bisa muncul dari
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
    $html = $this->get('/welcome/home')->assertOk()->getContent();

    // Trigger harus tombol (memang butuh JS untuk membuka dropdown), bukan
    // <a>: kalau jadi link, tidak ada dropdown-nya.
    $trigger = openingTag($html, 'fi-welcome-auth-actions-account');

    expect($trigger)->toContain('<button')
        ->toContain('type="button"')
        ->toContain('aria-label="'.e(__('Sign In To Account')).'"');
});

test('the guest dropdown item links to the auth landing page', function (): void {
    $html = $this->get('/welcome/home')->assertOk()->getContent();

    // Item dropdown harus <a> dengan href ke /user/auth -- inilah pintu auth
    // tunggal storefront. Kalau href-nya berubah ke /user/signin langsung,
    // halaman auth (pilihan dua pintu) jadi tidak terpakai.
    expect($html)->toContain('href="'.UserAuth::getUrl().'"')
        ->and($html)->toContain(e(__('Sign In To Account')))
        // Dan memang halaman auth, bukan form login.
        ->and(UserAuth::getUrl())->not->toBe(route('filament.user.auth.login'));
});

test('the guest control never links to registration', function (): void {
    // Tidak ada tombol daftar di topbar: pendaftaran lewat Google ada di halaman
    // auth, dan dua tombol auth di topbar memakan lebar yang dibutuhkan kotak
    // pencarian. Pendaftaran dinonaktifkan, jadi tidak ada route register untuk
    // ditunjuk -- yang dikunci di sini adalah ketiadaan link, bukan URL.
    $html = $this->get('/welcome/home')->assertOk()->getContent();

    expect($html)
        ->not->toContain('href="'.route('filament.user.auth.login').'"')
        ->not->toContain('/user/signup')
        ->not->toContain('/user/register');
});

test('there is exactly one account control in the topbar', function (): void {
    // Dua kontrol = dua tempat untuk keadaan yang sama, dan topbar
    // yang melebar. Hitung kemunculan class kontrol, bukan substring label.
    $html = $this->get('/welcome/home')->assertOk()->getContent();

    expect(substr_count($html, 'fi-welcome-auth-actions-account'))->toBe(1);
});

test('a signed in visitor gets the same icon, linking straight to the account home', function (): void {
    actingAs($this->customer, 'web');

    $html = $this->get('/welcome/flowerdecorationscatalog')->assertOk()->getContent();

    $control = openingTag($html, 'fi-welcome-auth-actions-account');

    // Satu tujuan, satu kontrol: ikon jadi link, tanpa dropdown dan tanpa
    // tombol "Beranda" terpisah.
    expect($control)
        ->toContain('<a')
        ->toContain('href="'.route('filament.user.pages.home').'"')
        ->toContain('aria-label="'.e(__('Beranda')).'"');

    // Tidak ada dropdown akun untuk user yang sudah login.
    expect(substr_count($html, 'fi-welcome-auth-actions-account'))->toBe(1)
        ->and($html)->not->toContain(__('Sign In To Account'));
});

test('the signed in control is never the auth landing page', function (): void {
    // Menu topbar untuk user login tidak boleh menawarkan "Masuk ke Akun" --
    // itu ajakan untuk log out dan masuk lagi.
    actingAs($this->customer, 'web');

    $html = $this->get('/welcome/flowerdecorationscatalog')->assertOk()->getContent();

    expect(openingTag($html, 'fi-welcome-auth-actions-account'))
        ->not->toContain('href="'.UserAuth::getUrl().'"');
});

test('the guest dropdown label follows the language switcher', function (): void {
    // Label keluar dari `__()`, jadi ia harus berubah bersama locale. Kalau
    // kunci terjemahannya hilang dari salah satu file, halaman Inggris akan
    // diam-diam menampilkan teks Indonesia tanpa ada test yang gagal.
    $this->get('/welcome/home?locale=id')
        ->assertOk()
        ->assertSee(e(__('Sign In To Account')));

    $this->get('/welcome/home?locale=en')
        ->assertOk()
        ->assertSee(e(__('Sign In To Account')));
});

test('the guest dropdown shows the indonesian wording in indonesian', function (): void {
    // Dipisah dari test di atas supaya kedua wording-nya dikunci sebagai literal:
    // inilah yang membuat language switcher terlihat berfungsi.
    $this->get('/welcome/home?locale=id')
        ->assertOk()
        ->assertSee('Masuk ke Akun', escape: false);
});

test('filaments own user menu never leaks into this panel', function (): void {
    // Override panel-scoped di user-menu.blade.php yang membuang markup bawaan.
    // Kalau override itu hilang dari registrasi AppServiceProvider, user menu
    // Filament muncul kembali -- dengan avatar yang tidak bisa diklik untuk tamu.
    foreach (['/welcome/home', '/welcome/flowerdecorationscatalog'] as $url) {
        expect($this->get($url)->assertOk()->getContent())
            ->not->toContain('fi-user-menu', escape: false);
    }
});

test('the theme switcher survives next to the account control', function (): void {
    // Keduanya berbagi baris topbar. Switcher tema diambil dari user menu
    // Filament, jadi di panel ini harus di-render sendiri -- kalau ikut hilang
    // bersama user menu, tamu kehilangan kontrol terang/gelap.
    $html = $this->get('/welcome/home')->assertOk()->getContent();

    expect($html)->toContain('fi-theme-switcher', escape: false)
        ->and($html)->toContain('fi-welcome-auth-actions', escape: false);
});