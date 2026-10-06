<?php

use App\Enums\RuntimePlatform\RuntimePlatform;
use App\Http\Middleware\AuthenticateWelcome\AuthenticateWelcome;
use App\Http\Middleware\EnsureProfileComplete\EnsureProfileComplete;
use App\Models\User\User;
use App\Support\AppPlatform\AppPlatform;
use Database\Factories\HistoryFactory;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MixCode\FilamentMulti2fa\Middleware\CheckTrustedDevice;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/*
 * The welcome panel is a second entry point to the same storefront: everything
 * under app/Filament/Welcome started as a byte-for-byte copy of
 * app/Filament/User, namespace included. It was also never registered --
 * bootstrap/providers.php pointed at App\Providers\Filament\WelcomePanelProvider,
 * a namespace rather than a class -- so /welcome answered 404 and nothing in it
 * was ever exercised.
 *
 * These tests exist because a panel that 404s cannot fail a test. They assert
 * the copy actually resolves under its own namespace now, and that mirroring
 * UserPanelProvider did not quietly make the two panels share state.
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'super_admin']);

    $this->user = User::factory()->create();
    $this->user->assignRole('super_admin');

    actingAs($this->user, 'web');
});

/**
 * Follows the panel's own redirect chain, so asserting on /welcome and on
 * /welcome/home give the same page rather than one being a 302.
 *
 * Takes the test case explicitly: a file-scope function has no $this.
 */
function welcomePage(Tests\TestCase $test, string $path)
{
    $response = $test->get($path);

    while ($response->isRedirect() && $response->headers->get('Location')) {
        $response = $test->get($response->headers->get('Location'));
    }

    return $response;
}

/**
 * Filament prepends a "panel:<id>" route-group marker to getMiddleware(); it is
 * the only part of the middleware list that may differ between the two panels.
 */
function stripPanelMarker($panel): array
{
    return array_values(array_filter(
        $panel->getMiddleware(),
        fn ($middleware) => ! str_starts_with($middleware, 'panel:'),
    ));
}

test('the welcome panel is registered', function (): void {
    expect(Filament::getPanel('welcome')->getId())->toBe('welcome')
        ->and(Filament::getPanel('welcome')->getPath())->toBe('welcome');
});

/*
 * Every one of these previously answered 404 (panel never registered) or 403
 * (canAccessPanel() only recognised 'admin' and 'user'), so reaching a 200 is
 * the assertion that matters -- a redirect would mean the panel bounced the
 * user somewhere else.
 */
test('the welcome panel is routed', function (string $path): void {
    welcomePage($this, $path)->assertOk();
})->with([
    '/welcome',
    '/welcome/orders',
    '/welcome/histories',
    '/welcome/flowerdecorationscatalog',
    '/welcome/vouchers',
    '/welcome/wishlists',
    '/welcome/carts',
    '/welcome/reviews',
    '/welcome/flowerdecorationspackagecatalog',
]);

/*
 * Beranda storefront milik tamu. beforeEach() di file ini login sebagai
 * super_admin, jadi dua test di bawah harus menguji kondisi TUAU, bukan
 * user yang sudah Sign In -- kalau tidak, keduanya diam-diam hanya menguji
 * halaman panel user setelah redirect.
 *
 * Test untuk user yang sudah login ada di test 'a signed-in visitor is sent
 * to the user panel home' pada WelcomeStorefrontGuestAccessTest.
 */
test('the welcome panel dashboard renders on a desktop', function (): void {
    AppPlatform::fake(RuntimePlatform::WebsiteWindows);

    auth()->logout();

    welcomePage($this, '/welcome')->assertOk();
});

test('the welcome panel dashboard renders in the mobile shell', function (): void {
    AppPlatform::fake(RuntimePlatform::MobileAppAndroid);

    auth()->logout();

    welcomePage($this, '/welcome')->assertOk();
});

test('a welcome list page renders', function (): void {
    AppPlatform::fake(RuntimePlatform::WebsiteWindows);

    HistoryFactory::new()->count(3)->create(['user_id' => $this->user->id]);

    $this->get('/welcome/histories')->assertOk();
});

/*
 * app/Filament/Welcome is a copy of app/Filament/User, so the easy mistake is a
 * resource that still points at the User classes and quietly duplicates data
 * across two panels. These assert the copy is genuinely independent.
 */
test('welcome resources resolve to the welcome namespace', function (): void {
    $resource = \App\Filament\Welcome\Resources\OrderResource\OrderResource::class;

    expect(class_exists($resource))->toBeTrue()
        ->and($resource)->toStartWith('App\\Filament\\Welcome\\')
        ->and($resource)->not->toBe(\App\Filament\User\Resources\OrderResource\OrderResource::class);
});

test('every welcome class is namespaced under welcome', function (): void {
    $leaked = [];

    foreach (glob(app_path('Filament/Welcome/{,*/,*/*/,*/*/*/,*/*/*/*/}*.php'), GLOB_BRACE) as $file) {
        $source = file_get_contents($file);

        if (preg_match('/App\\\\Filament\\\\User\\\\/', $source)) {
            $leaked[] = str_replace(app_path('Filament/Welcome/'), '', $file);
        }
    }

    /*
     * Pengecualian yang disengaja, dan hanya satu: Welcome\Pages\Home
     * mengarahkan pengunjung yang sudah login ke home panel USER
     * (`UserHome::getUrl(panel: 'user')`). Tanpa itu, panel storefront akan
     * memantulkan user yang login ke halaman yang sama dengan `redirect()`, dan
     * Laravel membatalkan rantai itu sebagai loop.
     *
     * Aturan di sini tetap ketat: daftar di bawah HARUS persis sama dengan
     * daftar rujukan yang bocor. Rujukan silang baru di file lain akan
     * menggagalkan test ini -- itulah gunanya pengecualian ini.
     */
    $allowed = [
        'Pages/Home/Home.php' => 'redirect user yang sudah login ke home panel user',
    ];

    expect($leaked)->toEqual(array_keys($allowed));
});

/*
 * The excluded block. The Welcome panel must NOT inherit the User panel's OTP
 * auth pages or its custom user menu: those items point at App\Filament\User
 * classes, so they would resolve to /user URLs and navigate out of /welcome.
 * The absence is asserted positively rather than by comparing page classes,
 * because Filament exposes has*() rather than a getter that can return null.
 *
 * Catatan: `hasRegistration()` FALSE untuk panel user juga bagian dari kontrak
 * ini, bukan kebetulan -- pendaftaran dinonaktifkan (hanya lewat
 * tombol Google di /user/auth), jadi tidak ada satu pun panel yang punya route
 * register.
 */
test('the welcome panel keeps its own auth pages, not the user panels', function (): void {
    $welcome = Filament::getPanel('welcome');
    $user = Filament::getPanel('user');
    $admin = Filament::getPanel('admin');

    expect($welcome->hasLogin())->toBeFalse()
        ->and($welcome->hasPasswordReset())->toBeFalse()
        ->and($welcome->hasEmailVerification())->toBeFalse()
        ->and($user->hasLogin())->toBeTrue()
        ->and($user->hasPasswordReset())->toBeTrue()
        ->and($user->hasEmailVerification())->toBeTrue()
        // Tidak ada panel yang punya pendaftaran: Sign Up dinonaktifkan, dan admin
        // memang tidak pernah punya route publik.
        ->and($welcome->hasRegistration())->toBeFalse()
        ->and($user->hasRegistration())->toBeFalse()
        ->and($admin->hasRegistration())->toBeFalse();
});

/*
 * "profile" is the one user-menu entry that renders as a labelled Profile item
 * in Filament's top navigation. It is part of the excluded block, so Welcome
 * keeps the stock menu and the top nav shows only the stock entries.
 */
test('the welcome panel drops the user panels custom user menu', function (): void {
    $welcome = Filament::getPanel('welcome');
    $user = Filament::getPanel('user');

    expect($user->getUserMenuItems())->toHaveKey('profile')
        ->and($welcome->getUserMenuItems())->not->toHaveKey('profile')
        ->and($welcome->getUserMenuItems())->not->toHaveKey('pengaturan')
        ->and($welcome->getUserMenuItems())->not->toHaveKey('riwayat')
        ->and($welcome->getUserMenuItems())->not->toHaveKey('ulasan')
        ->and($welcome->getUserMenuItems())->not->toHaveKey('privacy')
        ->and($welcome->getUserMenuItems())->not->toHaveKey('bantuan');
});

test('the welcome panel mirrors the user panels layout configuration', function (): void {
    $welcome = Filament::getPanel('welcome');
    $user = Filament::getPanel('user');

    expect($welcome->getBrandName())->toBe($user->getBrandName())
        ->and($welcome->getBrandLogo())->toBe($user->getBrandLogo())
        ->and($welcome->getBrandLogoHeight())->toBe($user->getBrandLogoHeight())
        ->and($welcome->hasTopNavigation())->toBe($user->hasTopNavigation())
        ->and($welcome->hasSpaMode())->toBe($user->hasSpaMode())
        ->and($welcome->hasUnsavedChangesAlerts())->toBe($user->hasUnsavedChangesAlerts())
        ->and($welcome->hasCollapsibleNavigationGroups())->toBe($user->hasCollapsibleNavigationGroups())
        ->and($welcome->getMaxContentWidth())->toBe($user->getMaxContentWidth())
        ->and($welcome->getFontFamily())->toBe($user->getFontFamily())
        ->and($welcome->getDefaultThemeMode())->toBe($user->getDefaultThemeMode())
        ->and($welcome->hasDatabaseNotifications())->toBe($user->hasDatabaseNotifications())
        ->and($welcome->getAuthGuard())->toBe($user->getAuthGuard())
        ->and($welcome->getColors())->toBe($user->getColors())
        ->and(array_map(fn ($g) => $g->getLabel(), $welcome->getNavigationGroups()))
        ->toBe(array_map(fn ($g) => $g->getLabel(), $user->getNavigationGroups()))
        // getMiddleware() already yields class-name strings, so no ::class map.
        // Its first entry is Filament's own "panel:<id>" route-group marker,
        // which is expected to differ because the ids differ.
        ->and(stripPanelMarker($welcome))->toBe(stripPanelMarker($user));
});

/*
 * Perbedaan yang disengaja pada gate. Dua, dan keduanya wajib ada:
 *
 *   1. Filament\Authenticate akan 403 tamu tanpa tempat dikirim, karena panel
 *      ini tidak mendaftarkan halaman login, jadi gate-nya AuthenticateWelcome.
 *   2. `CheckTrustedDevice` milik plugin 2FA hanya di panel user: panel Welcome
 *      tidak punya halaman 2FA sama sekali, jadi middleware itu tidak boleh ikut
 *      memblokir tamu yang sedang menjelajah storefront.
 *
 * Daftar ditulis persis (bukan "mengandung"), dalam urutan yang sama seperti saat
 * didaftarkan di UserMulti2faPlugin::register() + UserPanelProvider, supaya
 * perubahan urutan diam-diam ikut terlihat di sini.
 */
test('the welcome panel gates guests differently from the user panel', function (): void {
    $welcome = Filament::getPanel('welcome');
    $user = Filament::getPanel('user');

    expect($welcome->getAuthMiddleware())->toBe([AuthenticateWelcome::class])
        ->and($user->getAuthMiddleware())->toBe([
            CheckTrustedDevice::class,
            FilamentAuthenticate::class,
            EnsureProfileComplete::class,
        ])
        ->and($welcome->getAuthMiddleware())->not->toBe($user->getAuthMiddleware());
});

test('the welcome panel renders without the mobile pagination pill', function (): void {
    AppPlatform::fake(RuntimePlatform::MobileAppAndroid);

    // The pill restyled Filament's paginator and the user panel no longer has
    // one, so nothing may re-inject it.
    $this->get('/welcome/histories')
        ->assertOk()
        ->assertDontSee('fi-mobile-pagination-root', escape: false);
});
