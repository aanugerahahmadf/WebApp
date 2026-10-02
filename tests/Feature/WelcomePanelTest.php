<?php

use App\Enums\RuntimePlatform\RuntimePlatform;
use App\Http\Middleware\AuthenticateWelcome\AuthenticateWelcome;
use App\Models\User\User;
use App\Support\AppPlatform\AppPlatform;
use Database\Factories\HistoryFactory;
use Filament\Facades\Filament;
use App\Http\Middleware\EnsureProfileComplete\EnsureProfileComplete;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

test('the welcome panel dashboard renders on a desktop', function (): void {
    AppPlatform::fake(RuntimePlatform::WebsiteWindows);

    welcomePage($this, '/welcome')->assertOk();
});

test('the welcome panel dashboard renders in the mobile shell', function (): void {
    AppPlatform::fake(RuntimePlatform::MobileAppAndroid);

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

    expect($leaked)->toBe([]);
});

/*
 * The excluded block. The Welcome panel must NOT inherit the User panel's OTP
 * auth pages or its custom user menu: those items point at App\Filament\User
 * classes, so they would resolve to /user URLs and navigate out of /welcome.
 * The absence is asserted positively rather than by comparing page classes,
 * because Filament exposes has*() rather than a getter that can return null.
 */
test('the welcome panel keeps its own auth pages, not the user panels', function (): void {
    $welcome = Filament::getPanel('welcome');
    $user = Filament::getPanel('user');

    expect($welcome->hasLogin())->toBeFalse()
        ->and($welcome->hasRegistration())->toBeFalse()
        ->and($welcome->hasPasswordReset())->toBeFalse()
        ->and($welcome->hasEmailVerification())->toBeFalse()
        ->and($user->hasLogin())->toBeTrue()
        ->and($user->hasRegistration())->toBeTrue()
        ->and($user->hasPasswordReset())->toBeTrue()
        ->and($user->hasEmailVerification())->toBeTrue();
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
 * The one deliberate difference left in the panel configuration. Filament's
 * Authenticate would 403 a guest with nowhere to send them, because this panel
 * registers no login page, so the gate is AuthenticateWelcome instead.
 */
test('the welcome panel gates guests differently from the user panel', function (): void {
    $welcome = Filament::getPanel('welcome');
    $user = Filament::getPanel('user');

    expect($welcome->getAuthMiddleware())->toBe([AuthenticateWelcome::class])
        ->and($user->getAuthMiddleware())->toBe([FilamentAuthenticate::class, EnsureProfileComplete::class])
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
