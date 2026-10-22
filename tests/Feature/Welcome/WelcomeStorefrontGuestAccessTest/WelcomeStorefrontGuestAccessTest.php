<?php

use App\Enums\RuntimePlatform\RuntimePlatform;
use App\Filament\User\Auth\Auth\Auth as AuthLanding;
use App\Filament\Welcome\Resources\CatalogResource\CatalogResource;
use App\Http\Middleware\AuthenticateWelcome\AuthenticateWelcome;
use App\Models\Package\Package;
use App\Models\Catalog\Catalog;
use App\Models\User\User;
use App\Support\AppPlatform\AppPlatform;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/*
 * The welcome panel IS the public storefront at root '/'. A guest
 * is expected to browse the landing page and the catalog before signing in,
 * the way the reference site behaves. Filament's own Authenticate cannot do that --
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
 * The storefront's sign-in entry point is /auth -- the picker page holding
 * a Sign In button and Continue With Google -- not /signin directly. That is
 * what the topbar and the guest-action assertions below expect.
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();

    AppPlatform::fake(RuntimePlatform::WebsiteWindows);

    $this->customer = User::factory()->create();
    $this->customer->assignRole('customer');

    $this->catalog = Catalog::factory()->create(['name' => 'Bunga Mawar Merah', 'stock' => 10]);

    // The package is a TIER of the catalog (`Catalog::tiers()` ->
    // `hasMany(Package, 'catalog_id')`), not a standalone browsable record.
    //
    // `PackageResource` was folded into `CatalogResource`, whose slug is
    // `flowerdecorationscatalog`. So `catalog_id` is set here on purpose: the
    // tests below used to hit a package's own URL and got a 404, which looked
    // like a guest-access regression but was really a dead route.
    $this->package = Package::factory()->create([
        'name'       => 'Paket Dekorasi Lengkap',
        'stock'      => 5,
        'catalog_id' => $this->catalog->getKey(),
    ]);
});

/*
 * The landing page the old marketing view used to serve is gone, so '/' has to
 * land somewhere real. Asserting the whole chain matters: a bare 302 would also
 * satisfy a test that only checked for a redirect.
 */
test('the root url lands a guest on the storefront', function (): void {
    // '/' masih satu hop redirect ke beranda. Yang diuji adalah RANTAI
    // nya: tujuan redirectnya beranda, dan beranda itu benar-benar
    // merender (200) untuk tamu.
    $response = get('/');

    expect($response->isRedirect())->toBeTrue()
        ->and(parse_url($response->headers->get('Location'), PHP_URL_PATH))->toBe('/home');

    get('/home')->assertOk();
});

test('a signed-in visitor is sent to the welcome panel home', function (string $path): void {
    // Beranda storefront milik tamu. User yang sudah Sign In -- termasuk
    // super_admin yang juga punya panel admin -- harus mendarat di home panel
    // welcome, tidak berhenti di storefront.
    //
    // Rantai redirect diikuti sampai habis. Berbeda per path, dan itu
    // memang yang diuji:
    //
    //   '/'     -> satu hop ke /home (route closure di routes/web/web.php)
    //   '/home' -> tanpa hop; beranda itu sendiri sudahTUHAN rumah
    //
    // Yang WAJIB benar di keduanya: url akhirnya /home dan halamannya
    // merender 200. Jumlah hop tidak dikunci -- yang dikunci adalah
    // tujuan akhirnya, plus jaring pengaman anti-loop di bawah.
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

    expect(parse_url($current, PHP_URL_PATH))->toBe('/home')
        ->and($response->getStatusCode())->toBe(200);
})->with([
    '/home',
    '/',
]);

test('a signed-in visitor is still allowed on the catalog', function (string $path): void {
    // Cuma Beranda yang milik tamu. Katalog harus tetap bisa dibuka user yang
    // sudah login -- Browse-from-storefront-after-SignIn tetap berlaku.
    actingAs($this->customer, 'web');

    get(strtr($path, [
        '{catalog}' => (string) $this->catalog->getKey(),
        '{package}' => (string) $this->package->getKey(),
    ]))->assertOk();
})->with([
    '/flowerdecorationscatalog',
    '/flowerdecorationscatalog/{catalog}',
]);

test('a guest can browse the storefront and the catalog', function (string $path): void {
    get(strtr($path, [
        '{catalog}' => (string) $this->catalog->getKey(),
        '{package}' => (string) $this->package->getKey(),
    ]))->assertOk();
})->with([
    '/home',
    '/flowerdecorationscatalog',
    '/flowerdecorationscatalog/{catalog}',
]);

test('a guest is sent to the welcome panel auth landing page from an account page', function (string $path): void {
    // Ke /auth, bukan /signin: middleware meneruskan tamu ke
    // AuthenticateWelcome::LOGIN_ROUTE, yang kini menunjuk halaman auth
    // pertama (Sign In + Continue With Google). Dicek lewat konstanta yang
    // sama supaya test ini tidak diam-diam lulus kalau suatu saat ada kelas
    // Welcome lain yang menulis slug "/signin" langsung di sini.
    get($path)->assertRedirect(route(AuthenticateWelcome::LOGIN_ROUTE));
})->with([
    '/carts',
    '/orders',
    '/wishlists',
    '/reviews',
    '/vouchers',
    '/histories',
    // `/settings` SENGAJA TIDAK ada di sini: halamannya punya bagian yang
    // boleh dipakai TAMU (pemilih huruf tampilan, disimpan di session), jadi
    // `AuthenticateWelcome::PUBLIC_PATHS` memuat 'settings'. Yang di dalam
    // view itu sendiri (notifikasi, keamanan, kunci aplikasi, hapus akun)
    // dibungkus `@auth` -- tamu tidak melihatnya, dan tidak ada kode yang
    // dijalankan untuk dia.
]);

test('the route the gate redirects to is the auth landing page', function (): void {
    // Otherwise every guest redirect above lands on a 404, or worse: on the
    // form, which would silently take the Continue With Google door away.
    expect(AuthenticateWelcome::LOGIN_ROUTE)->toBe('filament.welcome.auth.index')
        ->and(route(AuthenticateWelcome::LOGIN_ROUTE))->toBe(AuthLanding::getUrl())
        ->and(route(AuthenticateWelcome::LOGIN_ROUTE))->toContain('/auth');

    get('/auth')->assertOk();

    // The form itself still exists and stays reachable -- it is one click from
    // the landing page, so the gate moving does not remove it.
    get('/signin')->assertOk();

    // URL lama tidak boleh jadi 404 (bookmark lama), cukup redirect. Ke form,
    // bukan ke gate: /user/login adalah alias dari halaman Sign In, bukan pintu
    // masuk tamu dari storefront.
    get('/login')->assertRedirect(route('filament.welcome.auth.login'));
});

/*
 * The checkout wizard writes an order against a user, so it cannot run for a
 * guest -- but its URL sits under the storefront's public path, so the panel
 * middleware waves it through and the page itself has to turn the guest away.
 * Left unguarded it is a silent no-op (handleCheckout returns null on a null
 * user), which reads as "your order was placed" and is not.
 */
test('a guest cannot reach the checkout wizard', function (): void {
    // Satu request, bukan dua. Sebelumnya test ini mengulang path yang sama
    // dengan id catalog dan id paket; setelah `PackageResource` dilipat ke
    // `CatalogResource` keduanya menjadi URL yang identik, jadi baris kedua
    // hanya menguji hal yang sama sekali (dan id paket yang dipakai sana
    // sudah bukan lagi id katalog).
    get("/flowerdecorationscatalog/{$this->catalog->getKey()}/checkout")
        ->assertRedirect(route(AuthenticateWelcome::LOGIN_ROUTE));
});

test('a signed in customer can still reach the checkout wizard', function (): void {
    actingAs($this->customer, 'web');

    get("/flowerdecorationscatalog/{$this->catalog->getKey()}/checkout")->assertOk();
});

/*
 * The topbar used to be an avatar linking to the profile. On a public storefront
 * that is useless to exactly the people most likely to be there, so it becomes a
 * user icon whose dropdown holds Sign In To Account (id: Masuk ke Akun) --
 * pointing at the welcome panel, which is where the auth pages live -- and the
 * avatar goes away. Registration is deliberately not linked from here; the
 * login page carries a "Belum memiliki akun? Daftar" link, so the flow stays
 * reachable without spending topbar width on a second button.
 */
test('the topbar offers Sign In to a guest instead of an avatar', function (): void {
    get('/home')
        ->assertOk()
        // Inside the dropdown, which is why the wording is the long one here and
        // the icon carries the shortcut.
        ->assertSee(__('Sign In To Account'))
        // Goes to the auth picker (/auth: Sign In button + Google), not
        // straight to the email/password form.
        ->assertSee(AuthLanding::getUrl(), escape: false)
        // PERUBAHAN PERILAKU: assertion `/signup` yang tadinya "dilarang"
        // dibalik menjadi "harus ada".
        //
        // Pendaftaran email/password sekarang AKTIF
        // (`->registration(SignUp::class)` + `->registrationRouteSlug('signup')`
        // di WelcomePanelProvider), dan dropdown akun punya item "Sign Up
        // Sekarang" yang menunjuk halaman itu. Test lama melarangnya dengan
        // alasan pendaftaran dinonaktifkan -- kondisi yang sudah tidak berlaku.
        //
        // Yang tetap dijaga: pintunya adalah `/auth` (Sign In + Google),
        // bukan form sign-in langsung, dan tidak ada avatar menu untuk tamu.
        ->assertSee('/signup', escape: false)
        ->assertDontSee(route('filament.welcome.auth.login'), escape: false)
        // No avatar / user menu for a guest to click through to.
        ->assertDontSee('fi-user-menu', escape: false);
});

test('the guest topbar dropdown item is translated for both switcher locales', function (): void {
    // The item label goes through __(), so a missing key would leave the
    // Indonesian storefront showing the English wording.
    get('/home?locale=id')
        ->assertOk()
        ->assertSee('Masuk ke Akun', escape: false);

    get('/home?locale=en')
        ->assertOk()
        ->assertSee(__('Sign In To Account'), escape: false);
});

/**
 * Topbar panel Welcome sekarang ada di SEMUA halaman
 * (`->topbar(TopNavigation::always())`).
 *
 * Dulunya topbar hanya ada di home, jadi test ini mengassert link "ke akun"
 * TIDAK ada di HALAMAN KATALOG, dengan alasan tertulis bahwa home milik
 * tamu sehingga harus diuji di halaman lain.
 *
 * Aturan itu dibalik untuk aplikasi desktop (Capacitor): topbar adalah tempat
 * pencarian global, user menu, switcher bahasa/tema, dan tombol kembali --
 * semuanya dibutuhkan di setiap layar. Akibatnya user yang sudah loginpun
 * menemukan kontrol akunnya di katalog, bukan cuma di berandanya.
 *
 * Yang tetap dijaga di tempat lain: halaman auth memakai layout simple
 * Filament, yang memang tidak punya topbar sama sekali.
 */
test('the topbar is present outside the storefront home, even for a signed in visitor', function (): void {
    actingAs($this->customer, 'web');

    get('/flowerdecorationscatalog')
        ->assertOk()
        // Topbar ada di katalog, jadi user login menemukan kontrol akunnya
        // di sana -- bukan cuma di berandanya.
        ->assertSee('fi-user-menu', escape: false);
});

test('the topbar keeps the theme switcher reachable for a guest', function (): void {
    // The switcher lives inside the user menu in stock Filament, and the welcome
    // panel does not render a user menu, so it has to be re-anchored or guests
    // lose light / dark / system entirely.
    get('/home')
        ->assertOk()
        ->assertSee('fi-theme-switcher', escape: false);
});

test('the theme switcher is a dropdown offering all three modes', function (): void {
    // Filament's stock switcher is a three-button strip, which crowded the
    // topbar next to Masuk and wrapped on a phone. It was replaced with a
    // dropdown, so the three modes have to still be individually reachable --
    // a dropdown that only opened an empty panel would pass the test above.
    get('/home')
        ->assertOk()
        ->assertSee('fi-dropdown', escape: false)
        // Welcome panel uses Shared theme switcher with simple labels
        // "Light", "Dark", "System" (not Filament's "Enable X theme")
        ->assertSee('Light', escape: false)
        ->assertSee('Dark', escape: false)
        ->assertSee('System', escape: false)
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
test('a guest sees every catalog detail button, and each redirects to login', function (): void {
    get("/flowerdecorationscatalog/{$this->catalog->getKey()}")
        ->assertOk()
        ->assertSee(__('Masukkan ke Keranjang'))
        ->assertSee(__('Tambah ke Favorit'))
        ->assertSee(__('Tulis Ulasan'))
        ->assertSee(__('Chat Admin')) // the one that works without an account
        // The checkout link must not be reachable directly -- it is behind the
        // Add-to-Cart modal, whose submit action redirects to login.
        ->assertDontSee("/flowerdecorationscatalog/{$this->catalog->getKey()}/checkout", escape: false);
});

/*
 * Test "a guest sees every package detail button" DIHAPUS, bukan diperbaiki.
 *
 * Awalnya test ini membuka `/welcome/flowerdecorationspackagecatalog/{package}`
 * dan memeriksa tombol detail paket. Route itu sudah tidak ada: `Package`
 * kini adalah TIER dari katalog (`Catalog::tiers()` ->
 * `hasMany(Package, 'catalog_id')`), dan `PackageResource`-nya dilipat ke
 * `CatalogResource` yang slug-nya `flowerdecorationscatalog`.
 *
 * Kalau test ini "diperbaiki" dengan mengganti URL-nya ke id katalog, isinya
 * menjadi salinan persis dari test katalog di atasnya -- dua test dengan nama
 * berbeda yang menguji hal yang sama. Itu lebih buruk daripada tidak ada:
 * suite jadi terlihat punya cakupan paket padahal tidak.
 *
 * Yang perlu dilakukan kalau halaman detail paket dihidupkan lagi: buat route
 * tier-nya (mis. `/flowerdecorationscatalog/{catalog}/tiers/{tier}`),
 * lalu tambahkan test yang benar-benar membuka URL itu. Sampai saat itu, tidak
 * ada yang perlu dijaga di sini.
 */

test('a signed in customer still gets the account actions', function (): void {
    actingAs($this->customer, 'web');

    get("/flowerdecorationscatalog/{$this->catalog->getKey()}")
        ->assertOk()
        ->assertSee(__('Masukkan ke Keranjang'))
        ->assertSee(__('Chat Admin'));
});

/*
 * The catalog cards are rendered by views under resources/views/Welcome (the
 * welcome resources point at those, not at their own copies), and those build
 * their hrefs from the App\Filament\Welcome resources -- which looks wrong for a
 * public storefront. It is not: Filament resolves Resource::getUrl() against
 * the *current* panel, so the class namespace is not what decides the prefix,
 * and on /flowerdecorationscatalog the hrefs come out as /flowerdecorationscatalog/{id}.
 *
 * This asserts the rendered outcome rather than the reason, so the storefront
 * cannot quietly start linking out to /user/ and bouncing guests off a login
 * wall in the middle of the public catalog.
 */
test('catalog cards link within the welcome panel, not out to the user panel', function (): void {
    get('/flowerdecorationscatalog')
        ->assertOk()
        ->assertSee("/flowerdecorationscatalog/{$this->catalog->getKey()}", escape: false)
        ->assertDontSee('/user/flowerdecorationscatalog', escape: false);
});

test('the dashboard catalog grid links within the welcome panel too', function (): void {
    // CombinedCatalogWidget is lazy-loaded (x-intersect), so the dashboard's
    // initial HTML never contains the grid. Render the view the widget uses
    // instead of driving the lazy-load request.
    Filament::setCurrentPanel(Filament::getPanel('welcome'));

    $html = view('Welcome.components.combined-catalog-grid.combined-catalog-grid', [
        'items' => Package::query()->with('category')->get()
            ->each(fn ($package) => $package->setAttribute('catalog_type', 'package'))
            ->concat(Catalog::query()->with('category')->get()
                ->each(fn ($catalog) => $catalog->setAttribute('catalog_type', 'catalog'))),
    ])->render();

    expect($html)->toContain('/flowerdecorationscatalog/')
        ->and($html)->not->toContain('/user/flowerdecorationscatalog')
        ->and($html)->not->toContain('/user/flowerdecorationscatalog');
});

test('the welcome panel resource urls are panel relative', function (): void {
    // getUrl() resolves against the current panel, so one has to be selected.
    Filament::setCurrentPanel(Filament::getPanel('welcome'));

    expect(CatalogResource::getUrl('view', ['record' => $this->catalog]))
        ->toContain('/flowerdecorationscatalog/')
        ->and(CatalogResource::getUrl('view', ['record' => $this->package]))
        ->toContain('/flowerdecorationscatalog/');
});
