<?php

use App\Filament\User\Auth\Auth\Auth;
use App\Filament\User\Auth\SignIn\SignIn;
use App\Filament\User\Auth\SignUp\SignUp;
use App\Filament\Welcome\Pages\Home\Home as WelcomeHome;
use App\Support\Phone\CountryCallingCodeOptions;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/*
 * /auth -- the guest auth landing page.
 *
 * It exists so the storefront has ONE sign-in entry point that offers both doors
 * (email/password and Google) instead of scattering the Google button across
 * Sign In and Sign Up. These tests pin the five things that can silently rot:
 *
 *   1. the page stays reachable by guests -- it is registered through
 *      ->routes(), not ->pages(), so it is the one thing that could accidentally
 *      land behind the panel's authMiddleware and 302 away the very guests it is
 *      for;
 *   2. the Google button moved, i.e. it is here and NOT on Sign In;
 *   3. the breadcrumb chain, which is the only navigation back up: Sign In
 *      points at this page, this page points at the storefront home;
 *   4. the heading stays "Welcome Back" and not "Sign In" -- this page offers the
 *      two doors, the form lives on /signin, and repeating that name here
 *      made heading, crumb and <title> all point at the other page;
 *   5. Sign Up stays reachable without duplicating the door: the class and view
 *      remain in the repo, the register route exists, and the old /signup +
 *      /user/register urls redirect to the same page instead of 404.
 *
 * CATATAN SOAL NAMA PANEL DI FILE INI
 *
 * Panel 'user' sudah dilebur ke panel 'welcome' (path '/'), jadi panel 'user'
 * tidak terdaftar lagi. `Filament::getPanel('user')` TIDAK melempar error --
 * `PanelRegistry::get()` mengembalikan panel default kalau id-nya tidak
 * terdaftar, dan default-nya sekarang 'welcome'. Jadi `getPanel('user')` di
 * file ini sebenarnya berarti panel 'welcome'. Yang boleh jadi rujukan adalah
 * `Route::has('filament.welcome.auth.register')`, bukan `filament.user.*`
 * (route latter sudah tidak ada sama sekali -- dan `route('filament.user.*')`
 * melempar RouteNotFoundException, bukan mengembalikan panel default).
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->customer = \App\Models\User\User::factory()->create();
    $this->customer->assignRole(
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'customer'])
    );
});

test('a guest can open the auth landing page', function (): void {
    get(Auth::getUrl())->assertOk();
});

test('its url is the /auth route, not the sign in form', function (): void {
    expect(Auth::getUrl())->toContain('/auth')
        ->and(Auth::getUrl())->not->toBe(Filament::getPanel('user')->getLoginUrl());
});

test('the route is registered outside the panel auth middleware', function (): void {
    // Guard against the failure mode described in the file header: ->pages()
    // would wrap the page in Authenticate + EnsureProfileComplete, so a guest
    // would be bounced off it. Asserted on the route's own middleware rather
    // than on the panel's, because that is where the wrapping actually shows up.
    $route = collect(app('router')->getRoutes()->getRoutes())
        ->first(fn ($route) => $route->getName() === 'filament.welcome.auth.index');

    expect($route)->not->toBeNull();

    $middleware = $route->gatherMiddleware();

    expect($middleware)->not->toContain(\Filament\Http\Middleware\Authenticate::class)
        ->and($middleware)->not->toContain(\App\Http\Middleware\EnsureProfileComplete\EnsureProfileComplete::class)
        // Panel middleware still applies -- SetLocale in particular, or the page
        // would render in the default locale regardless of the switcher.
        ->and($middleware)->toContain(\App\Http\Middleware\SetLocale\SetLocale::class);
});

test('it offers a sign in button pointing at the sign in form', function (): void {
    $response = get(Auth::getUrl())->assertOk();

    // The button is labelled "Sign In To Account" (id: "Masuk ke Akun"), not the
    // bare "Sign In": the form it opens has its own name, and the Google button
    // below already reads "Continue With Google", so a button called just "Sign In"
    // says nothing about where it goes. Compare
    // against __() AFTER the request, so the expectation follows whatever locale
    // the panel rendered the page in.
    $response->assertSee(__('Sign In To Account'), escape: false);
    $response->assertSee(Filament::getPanel('user')->getLoginUrl(), escape: false);
});

test('the sign in button label follows the language switcher locale', function (): void {
    // A missing key makes __() echo the English source string, so the Indonesian
    // page would quietly show "Sign In To Account" and nothing else would fail.
    // SetLocale reads ?locale= first, which is how the switcher's locale reaches
    // the page, so this pins both wordings the two locales actually offer.
    get(Auth::getUrl() . '?locale=id')
        ->assertOk()
        ->assertSee('Masuk ke Akun', escape: false);

    get(Auth::getUrl() . '?locale=en')
        ->assertOk()
        ->assertSee('Sign In To Account', escape: false);
});

test('it offers the google button, with no agreement gate in front of it', function (): void {
    // Locale TIDAK dikunci di sini.
    //
    // ssertSee/ssertDontSee membandingkan string hasil __(), jadi nilainya
    // ikut locale aktif. Memaksa ?locale=id membuat expectations-nya tidak cocok
    // dengan apa yang benar-benar dirender -- label Indonesia tidak muncul, dan
    // label Inggris yang muncul tidak diperiksa sama sekali. Test itu akan hijau
    // untuk alasan yang salah.
    //
    // Yang benar: __() dihitung SESUDAH request, di locale yang sama dengan
    // yang dipakai request itu.
    //
    // "Kebijakan Privasi" SENGAJA TIDAK dikunci di sini sebagai "tidak boleh
    // muncul". String itu juga dipakai cookie consent banner -- yang merender
    // link ke `/privacy-policy` dan memang harus ada di setiap halaman. Kalau
    // dikunci, test gagal dengan `Not to contain: Kebijakan Privasi` sementara
    // tidak ada satu pun checkbox persetujuan yang bocor. Test di bawah
    // (`the agreement gate is the checkbox partial ...`) menjaga hal yang
    // sebenarnya berbahaya.
    get(Auth::getUrl())
        ->assertOk()
        ->assertSee(__('Masuk Dengan Google'), escape: false)
        // Remember Me and the policy consents are deliberately NOT here: this is a
        // door, not a consent form. Both of them are still required by Sign In /
        // Sign Up (asserted in authenticate() / handleRegistration() there).
        ->assertDontSee(__('Ingat Saya'))
        ->assertDontSee(__('Perjanjian Pengguna'))
        ->assertDontSee(__('Kebijakan Aplikasi'));
});

test('the agreement gate is the checkbox partial, not the cookie banner link', function (): void {
    // PERHATIAN SOAL APA YANG DIJAGA DI TEST DI ATAS.
    //
    // Label "Kebijakan Privasi" TIDAK ikut dikunci sebagai "tidak boleh muncul",
    // padahal itu yang dilakukan test lama. Alasannya: string itu juga dipakai
    // cookie consent banner yang merender link ke `/privacy-policy`, dan banner
    // itu memang HARUS ada di setiap halaman -- termasuk di `/auth`.
    //
    // Gejalanya kalau label itu dikunci: test gagal dengan
    // `Not to contain: Kebijakan Privasi`, sementara tidak ada satu pun checkbox
    // persetujuan yang bocor ke halaman. Yang ditemukan hanya link di banner.
    //
    // Yang dikunci di sini adalah hal yang sebenarnya berbahaya: checkbox dan
    // pemicu `open-agreement` milik form persetujuan, bukan kemunculan label di
    // mana pun.
    get(Auth::getUrl() . '?locale=id')
        ->assertOk()
        // Komponen checkbox persetujuan tidak dirender di halaman ini
        // (`hideCheckboxes => true`), jadi pemicunya juga tidak boleh ada.
        ->assertDontSee('open-agreement', escape: false)
        ->assertDontSee('agreement-modal', escape: false);
});

test('the google button reads Continue With Google in english and Lanjutkan Dengan Google in indonesian', function (): void {
    // The button both signs people in and creates the account for people who do
    // not have one, so "Continue With Google" describes it honestly where
    // "Sign in with Google" only described half of it. Pinned per locale because a
    // missing key falls back to the Indonesian source string, which would render
    // "Masuk Dengan Google" on the English page without failing anything.
    get(Auth::getUrl() . '?locale=en')
        ->assertOk()
        ->assertSee('Continue With Google', escape: false);

    get(Auth::getUrl() . '?locale=id')
        ->assertOk()
        ->assertSee('Lanjutkan Dengan Google', escape: false);
});

test('it does not repeat the register link, because the register form has its own page', function (): void {
    // Halaman ini punya dua pintu: Sign In (formnya di /signin) dan Google.
    // Pendaftaran punya halamannya sendiri (/signup), jadi link ke sana tidak
    // diulang di sini -- supaya "/auth" tetap jadi "pilih pintu masuk", bukan
    // "halaman yang menautkan ke semua form auth".
    get(Auth::getUrl())
        ->assertOk()
        ->assertDontSee(__('Belum memiliki akun'), escape: false)
        ->assertDontSee(__('Daftar dengan Google'), escape: false)
        ->assertDontSee('/signup', escape: false)
        ->assertDontSee('/user/register', escape: false);
});

test('the google button is no longer on sign in', function (): void {
    get(Filament::getPanel('user')->getLoginUrl())
        ->assertOk()
        ->assertDontSee(__('Masuk Dengan Google'), escape: false)
        // The form itself is untouched. Field login hanya menerima Email atau
        // Username, jadi labelnya tidak lagi menjanjikan nomor dokumen.
        ->assertSee(__('Email / Username'), escape: false)
        ->assertDontSee(__('KTP / Passport'), escape: false)
        ->assertSee(__('Kata Sandi'), escape: false);
});

test('the sign up page is registered again', function (): void {
    // PERUBAHAAN PERILAKU. Test ini sebelumnya bernama "the sign up code is still
    // in the repo, it is only unregistered" dan mengunci kondisi pendaftaran
    // NONAKTIF: `getRegistrationUrl()` null, route
    // `filament.welcome.auth.register` tidak terdaftar, dan ketiga baris pemicunya
    // di `UserPanelProvider` masih dikomentari dengan `//`.
    //
    // Pendaftaran sekarang AKTIF. Ketiga baris itu dipanggil tanpa komentar,
    // dan `/signup` adalah halaman utuh.
    //
    // Yang dikunci sekarang adalah sisi yang sama-sama penting: pendaftaran
    // hidup LENGKAP, bukan setengah. Kalau `->registrationRouteSlug('signup')`
    // terlewat, URL `/user/register` (slug default Filament) muncul
    // tanpa disengaja -- dan `routes/web/web.php` mengarahkan ke
    // `/signup`, jadi yang hilang bukan lifeline-nya, tapi kejelasan
    // slug mana yang jadi kanonik.
    expect(class_exists(SignUp::class))->toBeTrue()
        ->and(View::exists('User.auth.sign-up.sign-up'))->toBeTrue()
        ->and(Filament::getPanel('user')->getRegistrationUrl())->not->toBeNull()
        ->and(Route::has('filament.welcome.auth.register'))->toBeTrue();

    // Sumber kebenaran pendaftaran adalah provider yang BENAR-BENAR
    // terdaftar, yaitu `WelcomePanelProvider`. `UserPanelProvider` masih ada
    // sebagai file, tapi provider itu sudah TIDAK didaftarkan di
    // `bootstrap/providers.php` sejak panel 'user' dilebur ke panel 'welcome'
    // -- jadi membacanya hanya membandingkan string dengan file mati, dan
    // assertion-nya tetap hijau walau pendaftaran dihapus dari panel yang
    // sebenarnya melayani halaman ini.
    $provider = (string) file_get_contents(
        dirname(__DIR__, 4).'/app/Providers/Filament/WelcomePanelProvider/WelcomePanelProvider.php'
    );

    expect($provider)->toContain('->registration(SignUp::class)')
        ->toContain("->registrationRouteSlug('signup')");

    // Dan provider yang mati itu tidak boleh daftarkan diri lagi: dua panel
    // di path '/' akan saling menimpa route-nya.
    //
    // Yang dicek adalah `::class`-nya, bukan namanya: `bootstrap/providers.php`
    // masih menyebut `UserPanelProvider` di komentarnya, untuk menjelaskan
    // kenapa provider itu tidak lagi didaftarkan.
    $registered = (string) file_get_contents(dirname(__DIR__, 4).'/bootstrap/providers.php');

    expect($registered)->not->toContain('UserPanelProvider::class');

    // Ketiga door harus melayani halaman yang sama: slug kanonik, slug alias
    // bawaan Filament, dan route yang terdaftar.
    get('/signup')->assertOk();

    get('/user/register')->assertRedirect('/signup');
});

test('sign in breadcrumbs up to this page, with no home crumb', function (): void {
    $crumbs = (new ReflectionClass(SignIn::class))
        ->newInstanceWithoutConstructor()
        ->getBreadcrumbs();

    expect(array_keys($crumbs))
        ->toHaveCount(2)
        ->and(array_keys($crumbs)[0])->toBe(Auth::getUrl())
        ->and(array_values($crumbs)[0])->toBe(Auth::crumbLabel())
        // The home crumb that used to lead to the storefront is gone: reaching
        // this page went through the picker, so the crumb has to show that.
        ->and(implode(' ', array_keys($crumbs)))->not->toContain(WelcomeHome::getUrl(panel: 'welcome'));
});

test('the parent crumb label names this page and is not doubled on the children', function (): void {
    // The parent crumb HAS to match this page's own heading ("Welcome Back"): it is
    // the name of the page being pointed at. The label that used to live here was
    // "Masuk" / "Log in", which in English rendered "Log in / Sign In" -- it read
    // as if the visitor were already on the form.
    expect(Auth::crumbLabel())->toBe(__('Welcome Back'))
        ->and(Auth::crumbLabel())->toBe(
            (new ReflectionClass(Auth::class))->newInstanceWithoutConstructor()->getHeading()
        )
        ->and(Auth::crumbLabel())->not->toBe(Auth::getUrl());

    // ...and it still has to differ from the children's own headings, or their
    // crumb reads the same word twice.
    expect(Auth::crumbLabel())->not->toBe(__('Sign In'))
        ->and(Auth::crumbLabel())->not->toBe(__('Sign Up'));
});

test('the page welcomes instead of repeating the sign in form name', function (): void {
    // This page only offers the two doors; the form itself is /signin, which
    // keeps its own "Sign In" heading. Calling this one "Sign In" too made the
    // heading, the breadcrumb's last crumb and the <title> all name the OTHER page.
    $page = (new ReflectionClass(Auth::class))->newInstanceWithoutConstructor();

    expect((string) $page->getHeading())->toBe(__('Welcome Back'))
        ->and((string) $page->getHeading())->not->toBe(__('Sign In'))
        // <title> follows the heading, so it changes with it.
        ->and((string) $page->getTitle())->toBe(__('Welcome Back'));
});

test('the welcome heading follows the language switcher locale', function (): void {
    // A missing "Welcome Back" key would leave the Indonesian page on the English
    // heading without anything failing.
    get(Auth::getUrl() . '?locale=id')
        ->assertOk()
        ->assertSee('Selamat Datang Kembali', escape: false);

    get(Auth::getUrl() . '?locale=en')
        ->assertOk()
        ->assertSee(__('Welcome Back'), escape: false);
});

test('the page itself still breadcrumbs up to the storefront home', function (): void {
    $crumbs = (new ReflectionClass(Auth::class))
        ->newInstanceWithoutConstructor()
        ->getBreadcrumbs();

    expect(array_keys($crumbs)[0])->toBe(WelcomeHome::getUrl(panel: 'welcome'))
        ->and(array_keys($crumbs))->toHaveCount(2);
});

test('a signed in user is sent to their account home instead', function (): void {
    actingAs($this->customer, 'web');

    get(Auth::getUrl())->assertRedirect(\App\Filament\User\Pages\Home\Home::getUrl());
});

test('country flags carry their emoji so the picker needs no network', function (): void {
    // The auth pages are reachable from an app shell and from a cold cache, so the
    // flag cannot depend on an external CDN being up.
    $indonesia = collect(CountryCallingCodeOptions::all())
        ->first(fn ($label) => str_contains((string) $label, 'Indonesia'));

    expect($indonesia)->not->toBeNull()
        ->and($indonesia)->toContain('🇮🇩')
        ->and($indonesia)->toContain('🇮🇩')
        ->and($indonesia)->not->toContain('<img');
});

