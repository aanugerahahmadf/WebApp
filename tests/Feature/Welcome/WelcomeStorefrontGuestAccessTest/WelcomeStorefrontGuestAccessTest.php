<?php

use App\Enums\RuntimePlatform\RuntimePlatform;
use App\Filament\User\Auth\Auth\Auth as AuthLanding;
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
 *
 * The storefront's sign-in entry point is /user/auth -- the picker page holding
 * a Sign In button and Continue With Google -- not /user/signin directly. That is
 * what the topbar and the guest-action assertions below expect.
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

test('a signed-in visitor is sent to the user panel home', function (string $path): void {
    // Beranda storefront milik tamu. User yang sudah Sign In -- termasuk
    // super_admin yang juga punya panel admin -- harus mendarat di home panel
    // user, tidak berhenti di storefront.
    //
    // Rantai redirect diikuti sampai habis: `/welcome` lebih dulu diarahkan ke
    // item navigasi pertama panel (RedirectToHomeController -> /welcome/home),
    // baru halaman itu yang mengarahkan ke home user. Dua hop, bukan satu.
    //
    // Catatan: Home::mount() pernah memanggil UserHome::getUrl() TANPA
    // `panel: 'user'`, jadi Filament menyusun route name dari panel yang sedang
    // aktif ('welcome') dan mengarahkan user kembali ke /welcome/home -- halaman
    // itu sendiri, berulang tanpa henti. Karena itu URL akhir ikut di-assert:
    // hop yang salah akan gagal sebagai "tidak sama dengan /user/home", bukan
    // menyisakan tebakan. Batas hop di bawah hanya jaring pengaman.
    actingAs($this->customer, 'web');

    $current = $path;
    $response = get($current);
    $hops = 0;

    while ($response->isRedirect() && $response->headers->get('Location')) {
        $current = $response->headers->get('Location');
        $response = get($current);
        $hops++;

        expect($hops)->toBeLessThan(5, "rantai redirect dari {$path} tidak berhenti (kemungkinan loop)");
    }

    expect($hops)->toBeGreaterThan(0, "{$path} tidak mengarahkan sama sekali")
        ->and(parse_url($current, PHP_URL_PATH))->toBe('/user/home')
        ->and($response->getStatusCode())->toBe(200);
})->with([
    '/welcome/home',
    '/welcome',
    '/',
]);

test('a signed-in visitor is still allowed on the catalog', function (string $path): void {
    // Cuma Beranda yang milik tamu. Katalog harus tetap bisa dibuka user yang
    // sudah login -- Browse-from-storefront-after-SignIn tetap berlaku.
    actingAs($this->customer, 'web');

    get(strtr($path, [
        '{product}' => (string) $this->product->getKey(),
        '{package}' => (string) $this->package->getKey(),
    ]))->assertOk();
})->with([
    '/welcome/flowerdecorationscatalog',
    '/welcome/flowerdecorationspackagecatalog',
    '/welcome/flowerdecorationscatalog/{product}',
    '/welcome/flowerdecorationspackagecatalog/{package}',
]);

test('a guest can browse the storefront and the catalog', function (string $path): void {
    get(strtr($path, [
        '{product}' => (string) $this->product->getKey(),
        '{package}' => (string) $this->package->getKey(),
    ]))->assertOk();
})->with([
    '/welcome/home',
    '/welcome/flowerdecorationscatalog',
    '/welcome/flowerdecorationspackagecatalog',
    '/welcome/flowerdecorationscatalog/{product}',
    '/welcome/flowerdecorationspackagecatalog/{package}',
]);

test('a guest is sent to the user panel auth landing page from an account page', function (string $path): void {
    // Ke /user/auth, bukan /user/signin: middleware meneruskan tamu ke
    // AuthenticateWelcome::LOGIN_ROUTE, yang kini menunjuk halaman auth
    // pertama (Sign In + Continue With Google). Dicek lewat konstanta yang
    // sama supaya test ini tidak diam-diam lulus kalau suatu saat ada kelas
    // Welcome lain yang menulis slug "/user/signin" langsung di sini.
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

test('the route the gate redirects to is the auth landing page', function (): void {
    // Otherwise every guest redirect above lands on a 404, or worse: on the
    // form, which would silently take the Continue With Google door away.
    expect(AuthenticateWelcome::LOGIN_ROUTE)->toBe('filament.user.auth.index')
        ->and(route(AuthenticateWelcome::LOGIN_ROUTE))->toBe(AuthLanding::getUrl())
        ->and(route(AuthenticateWelcome::LOGIN_ROUTE))->toContain('/user/auth');

    get('/user/auth')->assertOk();

    // The form itself still exists and stays reachable -- it is one click from
    // the landing page, so the gate moving does not remove it.
    get('/user/signin')->assertOk();

    // URL lama tidak boleh jadi 404 (bookmark lama), cukup redirect. Ke form,
    // bukan ke gate: /user/login adalah alias dari halaman Sign In, bukan pintu
    // masuk tamu dari storefront.
    get('/user/login')->assertRedirect(Filament::getPanel('user')->getLoginUrl());
});

/*
 * The checkout wizard writes an order against a user, so it cannot run for a
 * guest -- but its URL sits under the storefront's public path, so the panel
 * middleware waves it through and the page itself has to turn the guest away.
 * Left unguarded it is a silent no-op (handleCheckout returns null on a null
 * user), which reads as "your order was placed" and is not.
 */
test('a guest cannot reach the checkout wizard', function (): void {
    get("/welcome/flowerdecorationscatalog/{$this->product->getKey()}/checkout")
        ->assertRedirect(route(AuthenticateWelcome::LOGIN_ROUTE));

    get("/welcome/flowerdecorationspackagecatalog/{$this->package->getKey()}/checkout")
        ->assertRedirect(route(AuthenticateWelcome::LOGIN_ROUTE));
});

test('a signed in customer can still reach the checkout wizard', function (): void {
    actingAs($this->customer, 'web');

    get("/welcome/flowerdecorationscatalog/{$this->product->getKey()}/checkout")->assertOk();
    get("/welcome/flowerdecorationspackagecatalog/{$this->package->getKey()}/checkout")->assertOk();
});

/*
 * The topbar used to be an avatar linking to the profile. On a public storefront
 * that is useless to exactly the people most likely to be there, so it becomes a
 * user icon whose dropdown holds Sign In To Account (id: Masuk ke Akun) --
 * pointing at the user panel, which is where the auth pages live -- and the
 * avatar goes away. Registration is deliberately not linked from here; the
 * login page carries a "Belum memiliki akun? Daftar" link, so the flow stays
 * reachable without spending topbar width on a second button.
 */
test('the topbar offers Sign In to a guest instead of an avatar', function (): void {
    get('/welcome/home')
        ->assertOk()
        // Inside the dropdown, which is why the wording is the long one here and
        // the icon carries the shortcut.
        ->assertSee(__('Sign In To Account'))
        // Goes to the auth picker (/user/auth: Sign In button + Google), not
        // straight to the email/password form.
        ->assertSee(AuthLanding::getUrl(), escape: false)
        // Pendaftaran dinonaktifkan, jadi tidak ada door pendaftaran yang
        // bisa ditunjuk dari sini maupun dari halaman mana pun.
        ->assertDontSee('/user/signup', escape: false)
        // No avatar / user menu for a guest to click through to.
        ->assertDontSee('fi-user-menu', escape: false);
});

test('the guest topbar dropdown item is translated for both switcher locales', function (): void {
    // The item label goes through __(), so a missing key would leave the
    // Indonesian storefront showing the English wording.
    get('/welcome/home?locale=id')
        ->assertOk()
        ->assertSee('Masuk ke Akun', escape: false);

    get('/welcome/home?locale=en')
        ->assertOk()
        ->assertSee(__('Sign In To Account'), escape: false);
});

test('the topbar sends a signed in visitor straight to their account home', function (): void {
    actingAs($this->customer, 'web');

    // Diuji di katalog, bukan /welcome/home: home storefront milik tamu, jadi user
    // yang sudah login dialihkan ke /user/home (lihat test di atas), jadi topbar
    //nya harus diuji di halaman yang memang boleh dibuka user login.
    get('/welcome/flowerdecorationscatalog')
        ->assertOk()
        // The icon itself is the link -- no separate "Beranda" button, one
        // destination gets one control. "Beranda" survives only as the
        // aria-label / tooltip so the icon is readable.
        ->assertSee(route('filament.user.pages.home'), escape: false)
        ->assertSee('fi-welcome-auth-actions-account', escape: false)
        // No avatar / user menu to click through to on this panel.
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
    get("/welcome/flowerdecorationscatalog/{$this->product->getKey()}")
        ->assertOk()
        ->assertSee(__('Masukkan ke Keranjang'))
        ->assertSee(__('Tambah ke Favorit'))
        ->assertSee(__('Tulis Ulasan'))
        ->assertSee(__('Chat Admin')) // the one that works without an account
        // The sign-in entry point in the topbar, which is the only place a guest
        // on this page can be sent to login: the Add-to-Cart submit action inside
        // its modal is Livewire state, so it is not in the initial HTML.
        // Points at /user/auth (the picker: Sign In button + Google), not straight
        // at /user/signin.
        ->assertSee(AuthLanding::getUrl(), escape: false)
        ->assertDontSee("/welcome/flowerdecorationscatalog/{$this->product->getKey()}/checkout", escape: false);
});

test('a guest sees every package detail button, and each redirects to login', function (): void {
    get("/welcome/flowerdecorationspackagecatalog/{$this->package->getKey()}")
        ->assertOk()
        ->assertSee(__('Masukkan ke Keranjang'))
        ->assertSee(__('Tambah ke Favorit'))
        ->assertSee(__('Tulis Ulasan'))
        ->assertSee(__('Chat Admin'))
        // See the product-detail test above: /user/auth, the auth picker.
        ->assertSee(AuthLanding::getUrl(), escape: false)
        ->assertDontSee("/welcome/flowerdecorationspackagecatalog/{$this->package->getKey()}/checkout", escape: false);
});

test('a signed in customer still gets the account actions', function (): void {
    actingAs($this->customer, 'web');

    get("/welcome/flowerdecorationscatalog/{$this->product->getKey()}")
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
 * and on /welcome/flowerdecorationscatalog the hrefs come out as /welcome/flowerdecorationscatalog/{id}.
 *
 * This asserts the rendered outcome rather than the reason, so the storefront
 * cannot quietly start linking out to /user/ and bouncing guests off a login
 * wall in the middle of the public catalog.
 */
test('catalog cards link within the welcome panel, not out to the user panel', function (): void {
    get('/welcome/flowerdecorationscatalog')
        ->assertOk()
        ->assertSee("/welcome/flowerdecorationscatalog/{$this->product->getKey()}", escape: false)
        ->assertDontSee('/user/flowerdecorationscatalog', escape: false);
});

test('the dashboard catalog grid links within the welcome panel too', function (): void {
    // CombinedCatalogWidget is lazy-loaded (x-intersect), so the dashboard's
    // initial HTML never contains the grid. Render the view the widget uses
    // instead of driving the lazy-load request.
    Filament::setCurrentPanel(Filament::getPanel('welcome'));

    $html = view('User.components.combined-catalog-grid.combined-catalog-grid', [
        'items' => Package::query()->with('category')->get()
            ->each(fn ($package) => $package->setAttribute('catalog_type', 'package'))
            ->concat(Product::query()->with('category')->get()
                ->each(fn ($product) => $product->setAttribute('catalog_type', 'product'))),
    ])->render();

    expect($html)->toContain('/welcome/flowerdecorationspackagecatalog/')
        ->and($html)->toContain('/welcome/flowerdecorationscatalog/')
        ->and($html)->not->toContain('/user/flowerdecorationspackagecatalog')
        ->and($html)->not->toContain('/user/flowerdecorationscatalog');
});

test('the welcome panel resource urls are panel relative', function (): void {
    // getUrl() resolves against the current panel, so one has to be selected.
    Filament::setCurrentPanel(Filament::getPanel('welcome'));

    expect(ProductResource::getUrl('view', ['record' => $this->product]))
        ->toContain('/welcome/flowerdecorationscatalog/')
        ->and(PackageResource::getUrl('view', ['record' => $this->package]))
        ->toContain('/welcome/flowerdecorationspackagecatalog/');
});
