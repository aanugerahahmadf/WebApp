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
 * /user/auth -- the guest auth landing page.
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
 *      two doors, the form lives on /user/signin, and repeating that name here
 *      made heading, crumb and <title> all point at the other page;
 *   5. Sign Up stays disabled without losing its code: the class and view
 *      remain in the repo (only `->registration()` is commented out), the
 *      register route does not exist, and the old /user/signup +
 *      /user/register urls redirect here instead of 404.
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

test('its url is the /user/auth route, not the sign in form', function (): void {
    expect(Auth::getUrl())->toContain('/user/auth')
        ->and(Auth::getUrl())->not->toBe(Filament::getPanel('user')->getLoginUrl());
});

test('the route is registered outside the panel auth middleware', function (): void {
    // Guard against the failure mode described in the file header: ->pages()
    // would wrap the page in Authenticate + EnsureProfileComplete, so a guest
    // would be bounced off it. Asserted on the route's own middleware rather
    // than on the panel's, because that is where the wrapping actually shows up.
    $route = collect(app('router')->getRoutes()->getRoutes())
        ->first(fn ($route) => $route->getName() === 'filament.user.auth.index');

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
    get(Auth::getUrl())
        ->assertOk()
        ->assertSee(__('Masuk Dengan Google'), escape: false)
        // Remember Me and the policy consents are deliberately NOT here: this is a
        // door, not a consent form. Both of them are still required by Sign In /
        // Sign Up (asserted in authenticate() / handleRegistration() there).
        ->assertDontSee(__('Ingat Saya'))
        ->assertDontSee(__('Perjanjian Pengguna'))
        ->assertDontSee(__('Kebijakan Privasi'))
        ->assertDontSee(__('Kebijakan Aplikasi'));
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

test('it does not repeat the register link, because there is no register page', function (): void {
    // Halaman Sign Up dinonaktifkan, jadi halaman ini tidak punya apa pun untuk
    // diulang: satu-satunya pintu masuk dari sini adalah tombol Google dan link
    // ke form Sign In.
    get(Auth::getUrl())
        ->assertOk()
        ->assertDontSee(__('Belum memiliki akun'), escape: false)
        ->assertDontSee(__('Daftar dengan Google'), escape: false)
        ->assertDontSee('/user/signup', escape: false)
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

test('the sign up code is still in the repo, it is only unregistered', function (): void {
    // Pendaftaran dimatikan tanpa menghapus kode: class SignUp dan view-nya
    // masih ada supaya bisa dihidupkan lagi tanpa menulis ulang form, tapi
    // panel tidak mendaftarkannya -- jadi tidak ada route dan tidak ada URL
    // pendaftaran yang bisa dibuka.
    expect(class_exists(SignUp::class))->toBeTrue()
        ->and(View::exists('User.auth.sign-up.sign-up'))->toBeTrue()
        ->and(Filament::getPanel('user')->getRegistrationUrl())->toBeNull();

    // Kode yang menyalakannya harus tetap dalam keadaan dikomentari, kalau tidak
    // "dimatikan" hanya berlaku untuk file ini saja. Ketiganya diperiksa
    // terpisah karena menghidupkan pendaftaran berarti menghapus semua `//` itu:
    // import saja tidak cukup, dan `->registrationRouteSlug('signup')` yang
    // terlewat membuat URL `/user/register` muncul tanpa disengaja.
    $provider = (string) file_get_contents(
        dirname(__DIR__, 4).'/app/Providers/Filament/UserPanelProvider/UserPanelProvider.php'
    );

    expect($provider)->toContain('// use App\Filament\User\Auth\SignUp\SignUp;')
        ->toContain('// ->registration(SignUp::class)')
        ->toContain('// ->registrationRouteSlug(\'signup\')')
        // Alias Livewire-nya dikomentari di AppServiceProvider, dengan bentuk
        // aslinya (`as UserSignUp`) -- bukan alias baru yang harus dicari lagi.
        ->and((string) file_get_contents(dirname(__DIR__, 4).'/app/Providers/AppServiceProvider/AppServiceProvider.php'))
        ->toContain('// use App\Filament\User\Auth\SignUp\SignUp as UserSignUp;')
        ->and(Route::has('filament.user.auth.register'))->toBeFalse();

    foreach (['/user/signup', '/user/register'] as $url) {
        get($url)->assertRedirect(Auth::getUrl());
    }
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
    // This page only offers the two doors; the form itself is /user/signin, which
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
        ->and($indonesia)->toContain('(+62)')
        ->and($indonesia)->not->toContain('<img');
});