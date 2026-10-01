<?php

use App\Enums\RuntimePlatform\RuntimePlatform;
use App\Filament\Welcome\Resources\PackageResource\PackageResource;
use App\Filament\Welcome\Resources\ProductResource\ProductResource;
use App\Http\Middleware\AuthenticateWelcome\AuthenticateWelcome;
use App\Models\Package\Package;
use App\Models\Product\Product;
use App\Models\User\User;
use App\Support\AppPlatform\AppPlatform;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/*
 * The welcome panel is the public storefront: '/' redirects into it, and a guest
 * is expected to browse the landing page and the catalog before signing in, the
 * way the reference site behaves. Filament's own Authenticate cannot do that --
 * it would 403 a guest with nowhere to send them, because this panel registers
 * no login page -- which is why AuthenticateWelcome exists.
 *
 * These tests pin down the line: the storefront and the catalog are public,
 * everything account-shaped is not, and the account actions a guest can see on
 * a public detail page must not reach for a user id that is not there.
 *
 * Labels are asserted through __() rather than as literal Indonesian: the
 * suite runs under the 'en' locale, where lang/en.json translates them, so a
 * hardcoded string would assert against a translation this test does not own.
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'super_admin']);
    Role::firstOrCreate(['name' => 'customer']);

    AppPlatform::fake(RuntimePlatform::WebsiteWindows);

    $this->customer = User::factory()->create();
    $this->customer->assignRole('customer');

    $this->product = Product::factory()->create(['name' => 'Bunga Mawar Merah', 'stock' => 10]);
    $this->package = Package::factory()->create(['name' => 'Paket Dekorasi Lengkap', 'stock' => 5]);
});

/*
 * The landing page the old marketing view used to serve is gone, so '/' has to
 * land somewhere real. Asserting the whole chain matters: a bare 302 would also
 * satisfy a test that only checked for a redirect.
 */
test('the root url lands a guest on the storefront', function (): void {
    get('/')->assertRedirect('/welcome/home');
    get('/welcome')->assertRedirect('/welcome/home');

    get('/welcome/home')->assertOk();
});

test('a guest can browse the storefront and the catalog', function (string $path): void {
    get(strtr($path, [
        '{product}' => (string) $this->product->getKey(),
        '{package}' => (string) $this->package->getKey(),
    ]))->assertOk();
})->with([
    '/welcome/home',
    '/welcome/products',
    '/welcome/packages',
    '/welcome/products/{product}',
    '/welcome/packages/{package}',
]);

test('a guest is sent to the user panel login from an account page', function (string $path): void {
    get($path)->assertRedirect(route(AuthenticateWelcome::LOGIN_ROUTE));
})->with([
    '/welcome/carts',
    '/welcome/orders',
    '/welcome/wishlists',
    '/welcome/reviews',
    '/welcome/vouchers',
    '/welcome/histories',
    '/welcome/settings',
]);

test('the login route the gate redirects to exists', function (): void {
    // Otherwise every guest redirect above lands on a 404.
    expect(AuthenticateWelcome::LOGIN_ROUTE)->toBe('filament.user.auth.login')
        ->and(route(AuthenticateWelcome::LOGIN_ROUTE))->toContain('/user/login');

    get('/user/login')->assertOk();
});

/*
 * The checkout wizard writes an order against a user, so it cannot run for a
 * guest -- but its URL sits under the storefront's public path, so the panel
 * middleware waves it through and the page itself has to turn the guest away.
 * Left unguarded it is a silent no-op (handleCheckout returns null on a null
 * user), which reads as "your order was placed" and is not.
 */
test('a guest cannot reach the checkout wizard', function (): void {
    get("/welcome/products/{$this->product->getKey()}/checkout")
        ->assertRedirect(route(AuthenticateWelcome::LOGIN_ROUTE));

    get("/welcome/packages/{$this->package->getKey()}/checkout")
        ->assertRedirect(route(AuthenticateWelcome::LOGIN_ROUTE));
});

test('a signed in customer can still reach the checkout wizard', function (): void {
    actingAs($this->customer, 'web');

    get("/welcome/products/{$this->product->getKey()}/checkout")->assertOk();
    get("/welcome/packages/{$this->package->getKey()}/checkout")->assertOk();
});

/*
 * The topbar used to be an avatar linking to the profile. On a public storefront
 * that is useless to exactly the people most likely to be there, so it becomes
 * Masuk -- pointing at the user panel, which is where the auth pages live -- and
 * the avatar goes away. Registration is deliberately not linked from here; the
 * login page carries a "Belum memiliki akun? Daftar" link, so the flow stays
 * reachable without spending topbar width on a second button.
 */
test('the topbar offers Masuk to a guest instead of an avatar', function (): void {
    get('/welcome/home')
        ->assertOk()
        ->assertSee(__('Masuk'))
        ->assertSee(route(AuthenticateWelcome::LOGIN_ROUTE), escape: false)
        ->assertDontSee(route('filament.user.auth.register'), escape: false)
        // No avatar / user menu for a guest to click through to.
        ->assertDontSee('fi-user-menu', escape: false);
});

test('the topbar offers Beranda to a signed in visitor', function (): void {
    actingAs($this->customer, 'web');

    get('/welcome/home')
        ->assertOk()
        ->assertSee(__('Beranda'))
        // Points at the user panel's account home, not the storefront page the
        // visitor is already standing on.
        ->assertSee(route('filament.user.pages.home'), escape: false)
        ->assertDontSee('fi-user-menu', escape: false);
});

test('the topbar keeps the theme switcher reachable for a guest', function (): void {
    // The switcher lives inside the user menu in stock Filament, and the welcome
    // panel does not render a user menu, so it has to be re-anchored or guests
    // lose light / dark / system entirely.
    get('/welcome/home')
        ->assertOk()
        ->assertSee('fi-theme-switcher', escape: false);
});

test('the theme switcher is a dropdown offering all three modes', function (): void {
    // Filament's stock switcher is a three-button strip, which crowded the
    // topbar next to Masuk and wrapped on a phone. It was replaced with a
    // dropdown, so the three modes have to still be individually reachable --
    // a dropdown that only opened an empty panel would pass the test above.
    get('/welcome/home')
        ->assertOk()
        ->assertSee('fi-dropdown', escape: false)
        ->assertSee(__('filament-panels::layout.actions.theme_switcher.light.label'), escape: false)
        ->assertSee(__('filament-panels::layout.actions.theme_switcher.dark.label'), escape: false)
        ->assertSee(__('filament-panels::layout.actions.theme_switcher.system.label'), escape: false)
        // One trigger, not the stock strip of three buttons.
        ->assertSee('fi-theme-switcher-btn', escape: false);
});

/**
 * The detail pages are public. Every button is visible to guests -- cart,
 * wishlist, review -- and redirects to the user panel login on submit, since
 * each writes to user-owned state. Chat Admin is the exception: it works for
 * guests through a guest identity, because customer service has to be reachable
 * before anyone registers. A member who already reviewed an item has the review
 * button hidden instead.
 */
test('a guest sees every product detail button, and each redirects to login', function (): void {
    get("/welcome/products/{$this->product->getKey()}")
        ->assertOk()
        ->assertSee(__('Masukkan ke Keranjang'))
        ->assertSee(__('Tambah ke Favorit'))
        ->assertSee(__('Tulis Ulasan'))
        ->assertSee(__('Chat Admin')) // the one that works without an account
        ->assertSee(route(AuthenticateWelcome::LOGIN_ROUTE), escape: false)
        ->assertDontSee("/welcome/products/{$this->product->getKey()}/checkout", escape: false);
});

test('a guest sees every package detail button, and each redirects to login', function (): void {
    get("/welcome/packages/{$this->package->getKey()}")
        ->assertOk()
        ->assertSee(__('Masukkan ke Keranjang'))
        ->assertSee(__('Tambah ke Favorit'))
        ->assertSee(__('Tulis Ulasan'))
        ->assertSee(__('Chat Admin'))
        ->assertSee(route(AuthenticateWelcome::LOGIN_ROUTE), escape: false)
        ->assertDontSee("/welcome/packages/{$this->package->getKey()}/checkout", escape: false);
});

test('a signed in customer still gets the account actions', function (): void {
    actingAs($this->customer, 'web');

    get("/welcome/products/{$this->product->getKey()}")
        ->assertOk()
        ->assertSee(__('Masukkan ke Keranjang'))
        ->assertSee(__('Chat Admin'));
});

/*
 * The catalog cards are rendered by views under resources/views/User (the
 * welcome resources point at those, not at their own copies), and those build
 * their hrefs from the App\Filament\User resources -- which looks wrong for a
 * public storefront. It is not: Filament resolves Resource::getUrl() against
 * the *current* panel, so the class namespace is not what decides the prefix,
 * and on /welcome/products the hrefs come out as /welcome/products/{id}.
 *
 * This asserts the rendered outcome rather than the reason, so the storefront
 * cannot quietly start linking out to /user/ and bouncing guests off a login
 * wall in the middle of the public catalog.
 */
test('catalog cards link within the welcome panel, not out to the user panel', function (): void {
    get('/welcome/products')
        ->assertOk()
        ->assertSee("/welcome/products/{$this->product->getKey()}", escape: false)
        ->assertDontSee('/user/products', escape: false);
});

test('the dashboard catalog grid links within the welcome panel too', function (): void {
    // CombinedCatalogWidget is lazy-loaded (x-intersect), so the dashboard's
    // initial HTML never contains the grid. Render the view the widget uses
    // instead of driving the lazy-load request.
    Filament::setCurrentPanel(Filament::getPanel('welcome'));

    $html = view('User.components.combined-catalog-grid.combined-catalog-grid', [
        'records' => Package::query()->with('category')->get(),
    ])->render();

    expect($html)->toContain('/welcome/packages/')
        ->and($html)->toContain('/welcome/products/')
        ->and($html)->not->toContain('/user/packages')
        ->and($html)->not->toContain('/user/products');
});

test('the welcome panel resource urls are panel relative', function (): void {
    // getUrl() resolves against the current panel, so one has to be selected.
    Filament::setCurrentPanel(Filament::getPanel('welcome'));

    expect(ProductResource::getUrl('view', ['record' => $this->product]))
        ->toContain('/welcome/products/')
        ->and(PackageResource::getUrl('view', ['record' => $this->package]))
        ->toContain('/welcome/packages/');
});
