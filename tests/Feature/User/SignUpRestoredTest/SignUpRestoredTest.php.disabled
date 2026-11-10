<?php

use App\Filament\User\Auth\Auth\Auth;
use App\Filament\User\Pages\Home\Home;
use App\Models\User\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/*
|--------------------------------------------------------------------------
| Sign Up -- halaman pendaftaran email/password hidup lagi
|--------------------------------------------------------------------------
|
| Pendaftaran pernah DIMATIKAN dengan sengaja: `->registration()` di
| provider panel User dikomentari, route `filament.welcome.auth.register` tidak
| terdaftar, dan `/signup` cuma redirect ke `/auth`. Sekarang
| dihidupkan kembali, dan test ini mengunci konsekuensinya.
|
| Pemanggilnya sekarang ada di `WelcomePanelProvider` -- panel 'user' dilebur
| ke panel 'welcome' pada 2026-10-21, jadi `UserPanelProvider` (yang masih ada
| sebagai file, tapi tidak lagi terdaftar di `bootstrap/providers.php`) bukan
| lagi sumber kebenaran.
|
| Yang dikunci:
|
|   1. Route register terdaftar lagi dan halaman Sign Up benar-benar
|      terbuka untuk tamu. Ini yang paling mudah diam: route-nya bisa
|      terdaftar tapi tetap tidak terjangkau kalau ada route lain yang
|      lebih dulu cocok -- persis yang terjadi pada redirect `/signup`
|      yang menerima semua method.
|
|   2. Item menu "Sign Up Sekarang" muncul di topbar storefront dan
|      menunjuk ke /signup.
|
|   3. Alias lama `/user/register` tetap hidup dan sekarang mengarah ke
|      halaman Sign Up, bukan ke /auth.
|
|   4. Crumb auth tidak lagi 500 untuk referer /signup, dan menunjuk
|      ke halaman Sign Up yang sekarang benar-benar ada.
|
*/

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('user'));
});

function makeGuest(): User
{
    return User::factory()->create([
        'password' => bcrypt('senin-pagi-2026'),
        'social_type' => null,
        'email_verified_at' => now(),
    ]);
}

test('route register terdaftar lagi', function (): void {
    expect(Route::has('filament.welcome.auth.register'))->toBeTrue();
});

test('tamu bisa membuka halaman sign up', function (): void {
    // Kalau route-nya terdaftar tapi ada redirect yang lebih dulu cocok,
    // halaman ini diam-diam hanya balik ke /auth dan test "terbuka"
    // ini tetap hijau. Karena itu yang diperiksa status 200-nya, bukan
    // hanya route-nya ada.
    get('/signup')
        ->assertOk()
        ->assertSee(__('Sign Up'));
});

test('alias lama /user/register mengarah ke halaman sign up', function (): void {
    get('/user/register')->assertRedirect('/signup');
});

test('halaman sign up memuat form pendaftaran, bukan cuma heading', function (): void {
    $html = get('/signup')->assertOk()->getContent();

    // Heading saja tidak membuktikan form-nya hidup. `wire:submit="register"`
    // yang diperiksa, karena itu yang benar-benar mengikat form ke method
    // pendaftaran Filament.
    //
    // Nama field TIDAK diperiksa: di dalam snapshot Livewire semuanya
    // di-escape jadi `&quot;`, jadi `data.email` atau `&quot;email&quot;`
    // hanya menguji tebakan format -- bukan bentuk form-nya.
    expect($html)
        ->toContain('wire:submit="register"')
        // Link keluar ke Sign In juga dibuktikan merender, yang berarti blok
        // di dalam wrapper form ikut ter-render.
        ->toContain(__('Sudah memiliki akun?'));
});

test('halaman sign up menolak tamu yang sudah login', function (): void {
    actingAs(makeGuest());

    get('/signup')->assertRedirect(Home::getUrl(panel: 'user'));
});

test('link sign up di halaman sign in menunjuk ke halaman sign up', function (): void {
    // Default `assertSee` (escape: true) justru yang benar di sini: Blade
    // meng-escape output, jadi kutip di "Don't have an account yet?" jadi
    // `Don&#039;t` di HTML, dan assertSee meng-escape needle-nya dengan cara
    // yang sama. Memakai `escape: false` justru membuat keduanya tidak cocok.
    get('/signin')
        ->assertOk()
        ->assertSee(__('Belum memiliki akun?'))
        ->assertSee('/signup');
});

test('link sign in di halaman sign up menunjuk ke halaman sign in', function (): void {
    get('/signup')
        ->assertOk()
        ->assertSee(__('Sudah memiliki akun?'))
        ->assertSee('/signin');
});

test('item menu sign up sekarang ada di topbar storefront', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('welcome'));

    // Locale dikunci ke `id` supaya label yang diharapkan tidak bergantung pada
    // `APP_LOCALE` di `.env` mesin itu. Tanpa ini, assertion membaca terjemahan
    //Inggris ("Sign Up Now") di mesin yang locale-nya `en`, dan test yang benar
    // gagal hanya karena file lokal.
    $html = get('/home?locale=id')->assertOk()->getContent();

    expect($html)
        ->toContain(__('Sign Up Sekarang'))
        // Dan menunjuk ke halaman pendaftaran yang sungguhan, bukan ke
        // /auth yang cuma punya tombol Google.
        //
        // `getRegistrationUrl()` mengembalikan URL ABSOLUT, jadi yang dicek
        // adalah path-nya -- mencocokkan `href="/signup"` akan gagal
        // padahal itemnya benar-benar ada.
        ->toContain('/signup');
});

test('crumb sign up menunjuk ke beranda storefront, bukan ke halaman auth', function (): void {
    $html = get('/signup')->assertOk()->getContent();

    // Parent crumb Sign Up adalah Welcome Home. Sebelumnya menunjuk ke
    // `/auth` ("Welcome Back") -- halaman yang isinya Sign In + Google,
    // bukan induk pendaftaran sama sekali.
    expect($html)
        ->toContain(App\Filament\Welcome\Pages\Home\Home::getUrl(panel: 'welcome'))
        // Parent Auth tidak boleh muncul sebagai crumb.
        ->not->toContain('>'.Auth::crumbLabel().'</a>');
});

test('crumb auth dari referer sign up menunjuk ke halaman sign up tanpa error', function (): void {
    // Cabang crumb ini dulu dihapus karena route register tidak ada --
    // memanggil route() yang hilang membuat halaman auth 500. Sekarang
    // cabangnya hidup lagi, dan route-nya benar-benar ada.
    $response = $this->withHeaders(['referer' => 'http://localhost/signup'])
        ->get('/signin');

    $response->assertOk();
});

test('halaman auth dan sign up saling menaut', function (): void {
    // Pintu masuk dan pintu daftar tidak boleh saling menimpa: keduanya
    // hidup bersamaan dan keduanya harus punya door-nya sendiri.
    //
    // `SignUp::getUrl()` TIDAK dipakai di sini: SignUp extends
    // `Filament\Pages\Auth\Register`, yang tidak punya helper getUrl() statis,
    // jadi memanggilnya jatuh ke __callStatic dan melempar
    // BadMethodCallException. URL-nya diambil dari panel.
    expect(Auth::getUrl())->not->toBe(Filament::getPanel('user')->getRegistrationUrl())
        ->and(Auth::getUrl())->toContain('/auth')
        ->and(Filament::getPanel('user')->getRegistrationUrl())->toContain('/signup');
});

