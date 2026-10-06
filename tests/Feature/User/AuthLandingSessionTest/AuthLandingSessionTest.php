<?php

/**
 * Sesi dan guard pada `/user/auth` (halaman auth landing untuk tamu).
 *
 * Dua hal yang dijaga di sini, keduanya mudah lolos karena halamannya tetap 200:
 *
 *   1. `Auth::mount()` tidak boleh menulis session. Halaman ini hanya
 *      mengarahkan tamu yang sudah punya sesi; selama tidak ada penulisan
 *      session, `url.intended` yang disimpan Laravel (mis. dari tombol Add to
 *      Cart di katalog) tetap utuh dan dipakai setelah login berhasil. Satu
 *      `session()->put()` yang tidak sengaja di sini membuat pengguna mendarat
 *      di beranda setelah login, padahal ia sedang menuju detail produk -- dan
 *      tidak ada test lain yang akan menangkapnya.
 *
 *   2. Halaman auth harus terbuka untuk tamu dan TERTUTUP untuk pengguna yang
 *      sudah login. Yang menutupnya adalah `Auth::mount()` (redirect ke
 *      `/user/home`), bukan middleware panel: route-nya didaftarkan lewat
 *      `->routes()` sehingga tidak terbungkus `authMiddleware`. Kalau
 *      `mount()` dihapus, `/user/auth` terbuka untuk semua orang -- termasuk
 *      sesi yang sudah login, yang di sana tidak punya gunanya.
 */

use App\Filament\User\Auth\Auth\Auth;
use App\Filament\User\Pages\Home\Home;
use App\Models\User\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->customer = User::factory()->create();
    $this->customer->assignRole(Role::firstOrCreate(['name' => 'customer']));
});

test('a guest keeps the intended url untouched while visiting the auth landing page', function (): void {
    // Alur nyata: tamu menekan Add to Cart di katalog -> middleware
    // AuthenticateWelcome menyimpan tujuan -> tamu diarahkan ke /user/auth ->
    // login -> harus kembali ke detail produk, bukan ke beranda.
    $intended = '/welcome/flowerdecorationspackagecatalog/5';

    $this->withSession(['url.intended' => $intended])
        ->get(Auth::getUrl())
        ->assertOk();

    expect(session('url.intended'))->toBe($intended);
});

test('a guest who has no intended url does not gain one', function (): void {
    // Kebalikannya juga penting: halaman ini tidak boleh MENGISI url.intended
    // dengan URL-nya sendiri, karena setelah login pengguna akan diarahkan
    // kembali ke halaman auth yang baru saja ditinggalkannya.
    $this->get(Auth::getUrl())->assertOk();

    expect(session('url.intended'))->toBeNull();
});

test('the session locale is left alone by the landing page', function (): void {
    // SetLocale menulis `session('locale')`; halaman auth tidak boleh
    // menimpanya, karena switcher bahasa dan auth page boleh dipanggil dalam
    // urutan apa pun.
    $this->withSession(['locale' => 'id'])->get(Auth::getUrl())->assertOk();

    expect(session('locale'))->toBe('id');
});

test('a signed in visitor is sent to the user panel home instead', function (): void {
    // Guard di Auth::mount(). Tujuan harus home panel USER, bukan dashboard
    // storefront: middleware panel sudah melempar siapa pun yang sudah login
    // ke sana, dan halaman auth tidak boleh menjadi jalan mundur.
    actingAs($this->customer, 'web');

    $this->get(Auth::getUrl())->assertRedirect(Home::getUrl(panel: 'user'));
});

test('the redirect for a signed in visitor happens before anything is rendered', function (): void {
    // Salah satu yang perlu dijaga: halaman untuk tamu tidak boleh ikut
    // ter-render (mis. crumb dan logo) sebelum redirect, karena pada panel SPA
    // render sekejap sudah terlihat sebagai kedipan konten.
    actingAs($this->customer, 'web');

    $response = $this->get(Auth::getUrl());

    expect($response->status())->toBe(302)
        ->and($response->getContent())->not->toContain('<form');
});

test('a signed in visitor keeps their session while being redirected', function (): void {
    // Redirect bukan logout: kalau mount() menyentuh session, pengguna akan
    // diminta login lagi tepat setelah login.
    actingAs($this->customer, 'web');

    $this->get(Auth::getUrl())->assertRedirect(Home::getUrl(panel: 'user'));

    expect(auth()->check())->toBeTrue()
        ->and(auth()->id())->toBe($this->customer->id);
});

test('the sign in form is reachable for a guest, so the landing page is not a dead end', function (): void {
    // Tombol pertama di /user/auth mengarah ke sini. Kalau form menolak tamu,
    // halaman auth tidak berguna lagi -- dan tidak ada test lain yang akan
    // sampai ke sana lewat UI.
    $this->get(Auth::getUrl())->assertOk();

    $this->get(Filament::getPanel('user')->getLoginUrl())->assertOk();
});

test('the landing page is outside the panel auth middleware', function (): void {
    // Yang membuat test di atas mungkin adalah `->routes()` (bukan
    // `->pages()`). Kalau registrannya diubah, tamu justru dialihkan dan test
    // ini gagal -- itu memang sinyal yang benar untuk memperbaiki.
    $route = collect(app('router')->getRoutes()->getRoutes())
        ->first(fn ($route): bool => $route->getName() === 'filament.user.auth.index');

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())
        ->not->toContain(\Filament\Http\Middleware\Authenticate::class)
        ->and($route->gatherMiddleware())
        ->not->toContain(\App\Http\Middleware\EnsureProfileComplete\EnsureProfileComplete::class);
});
