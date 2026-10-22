<?php

use App\Enums\RuntimePlatform\RuntimePlatform;
use App\Models\User\User;
use App\Support\AppPlatform\AppPlatform;
use App\Support\MobileNav\MobileNav;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;

/*
 * The website, the Capacitor mobile shells (app/Capacitor/AdminApp,
 * app/Capacitor/UserApp) and the Electron desktop shell all render the very same
 * Filament panel over HTTP, so "does this screen work on a phone?" is a
 * question about the panel's output, not about a separate codebase.
 *
 * AppPlatform::fake() is the seam for that: it pins the platform for the
 * request, and everything (panel config, render hooks, blade) reads it from
 * there.
 */

beforeEach(function (): void {
    seed();
});

function mobileUser(): User
{
    // Role untuk panel USER harus ber-guard `web`, bukan `admin`.
    //
    // `User::assignRole()` mencari role di guard model itu sendiri (`web`),
    // sedangkan role `super_admin` yang sesungguhnya dibuat seeder hidup di
    // guard `admin` untuk tabel `admins`. Tanpa baris tambahan ini,
    // `assignRole()` melempar `RoleDoesNotExist` -- test gagal di helper,
    // sebelum satu pun assertion berjalan.
    //
    // Ini bukan berarti ada user ber-role `super_admin` di produksi: yang ada
    // di guard `web` hanya untuk keperluan panel user. Yang diuji file ini adalah
    // chrome panel, jadi yang dibutuhkan hanyalah "user yang boleh masuk".
    seedRoles(adminRoles: [], webRoles: ['super_admin']);

    $user = User::factory()->create();
    $user->assignRole('super_admin');

    return $user;
}

/**
 * GET a panel root and follow the panel's own redirect to its home page.
 *
 * Both panels redirect `/admin` and `/user` to their dashboard, so asserting on
 * the first response would only ever assert on a 302.
 *
 * Guard dipilih dari path-nya. Panel admin berjalan di guard `admin` (tabel
 * `admins`), jadi `actingAs($user, 'web')->get('/admin')` selalu mendarat di
 * halaman MASUK admin: `Authenticate` Filament menolak karena guard `admin`
 * kosong. Responsnya tetap 200 dan tetap punya topbar -- tapi tidak punya
 * sidebar maupun bottom nav, jadi test chrome panel admin ikut gagal dengan
 * `To contain: fi-sidebar` / `fi-bottom-nav-item` yang jauh dari penyebabnya.
 */
function getPanelPage(string $path): TestResponse
{
    $isAdminPanel = str_starts_with($path, '/admin');

    $actor = $isAdminPanel ? makeAdminWithStorefrontIdentity() : mobileUser();
    $guard = $isAdminPanel ? 'admin' : 'web';

    $response = actingAs($actor, $guard)->get($path);

    while ($response->isRedirect() && $response->headers->get('Location')) {
        $response = actingAs($actor, $guard)->get($response->headers->get('Location'));
    }

    return $response;
}

// â”€â”€ Platform detection â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

test('detects a desktop browser as a desktop website', function (): void {
    $request = Request::create('/user', 'GET');
    $request->headers->set('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36');

    expect(AppPlatform::detect($request))->toBe(RuntimePlatform::WebsiteWindows)
        ->and(AppPlatform::detect($request)->isMobileShell())->toBeFalse();
});

test('detects a phone browser as a mobile website but not a native app', function (): void {
    $request = Request::create('/user', 'GET');
    $request->headers->set('User-Agent', 'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 Chrome/120.0.0.0 Mobile Safari/537.36');

    $platform = AppPlatform::detect($request);

    expect($platform)->toBe(RuntimePlatform::WebsiteAndroid)
        ->and($platform->isMobileShell())->toBeTrue()
        // A phone in Chrome is not inside the app shell: the bottom bar and the
        // Capacitor camera must not be forced on it.
        ->and($platform->isMobileApp())->toBeFalse()
        // Only an app shell gets 'native' (the shell's own file inputs). Every
        // other surface -- website, desktop browser, mobile browser -- runs the
        // in-page WebRTC viewfinder, so a phone browser resolves to 'webrtc'.
        // The third legacy value, 'mobile_browser_capture', no longer exists:
        // see RuntimePlatform::cbirCameraMode(), whose contract is native|webrtc.
        ->and($platform->cbirCameraMode())->toBe('webrtc');
});

test('detects the capacitor android shell from the app_shell cookie', function (): void {
    $request = Request::create('/user', 'GET');
    $request->headers->set('User-Agent', 'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 Chrome/120.0.0.0 Mobile Safari/537.36');
    $request->cookies->set(AppPlatform::SHELL_COOKIE, 'native_android');

    $platform = AppPlatform::detect($request);

    expect($platform)->toBe(RuntimePlatform::MobileAppAndroid)
        ->and($platform->isMobileApp())->toBeTrue()
        ->and($platform->cbirCameraMode())->toBe('native');
});

test('detects the capacitor ios shell from the X-Capacitor header', function (): void {
    $request = Request::create('/user', 'GET');
    $request->headers->set('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148');
    $request->headers->set(AppPlatform::HEADER_CAPACITOR, 'ios');

    expect(AppPlatform::detect($request))->toBe(RuntimePlatform::MobileAppIos);
});

test('detects the electron desktop shell from the user agent', function (): void {
    $request = Request::create('/user', 'GET');
    $request->headers->set('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/128.0.0.0 Electron/33.0.0 Safari/537.36');

    $platform = AppPlatform::detect($request);

    expect($platform)->toBe(RuntimePlatform::DesktopAppWindows)
        ->and($platform->isDesktopApp())->toBeTrue();
});

test('an android webview without the shell cookie is still treated as the native app', function (): void {
    // Capacitor's Android WebView carries a `; wv` token in the platform section
    // that a real Chrome install never sends. This is what makes the very first
    // paint correct, before the in-page probe has had a chance to set a cookie.
    $request = Request::create('/user', 'GET');
    $request->headers->set('User-Agent', 'Mozilla/5.0 (Linux; Android 13; SM-A536B; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/120.0.0.0 Mobile Safari/537.36');

    expect(AppPlatform::detect($request))->toBe(RuntimePlatform::MobileAppAndroid);
});

test('an installed desktop pwa is a desktop app', function (): void {
    $request = Request::create('/user', 'GET');
    $request->headers->set('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 Safari/605.1.15');
    $request->cookies->set('app_display_mode', 'standalone');

    expect(AppPlatform::detect($request))->toBe(RuntimePlatform::DesktopAppMacOS);
});

test('a pwa installed on a phone stays a mobile platform', function (): void {
    // `display-mode: standalone` is also true for an installed phone PWA. Reading
    // it as a desktop signal would strip the bottom nav and the camera mode off
    // the platform that most needs them.
    $request = Request::create('/user', 'GET');
    $request->headers->set('User-Agent', 'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 Chrome/120.0.0.0 Mobile Safari/537.36');
    $request->cookies->set('app_display_mode', 'standalone');

    $platform = AppPlatform::detect($request);

    expect($platform)->toBe(RuntimePlatform::WebsiteAndroid)
        ->and($platform->isMobileShell())->toBeTrue();
});

test('CAPACITOR_PLATFORM overrides every request signal', function (): void {
    config(['app-platform.force_platform' => 'android']);

    $request = Request::create('/user', 'GET');
    $request->headers->set('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36');

    expect(AppPlatform::detect($request))->toBe(RuntimePlatform::MobileAppAndroid);
});

test('an unknown user agent falls back to a desktop website', function (): void {
    $request = Request::create('/user', 'GET');
    $request->headers->set('User-Agent', 'SomeCrawler/1.0');

    expect(AppPlatform::detect($request))->toBe(RuntimePlatform::WebsiteWindows);
});

// â”€â”€ Bottom navigation â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

test('the bottom navigation is hidden on desktop', function (): void {
    AppPlatform::fake(RuntimePlatform::WebsiteWindows);

    expect(MobileNav::shouldRender('user'))->toBeFalse()
        ->and(MobileNav::shouldRender('admin'))->toBeFalse();
});

test('the bottom navigation is shown for the mobile shells and phone browsers', function (): void {
    AppPlatform::fake(RuntimePlatform::MobileAppAndroid);
    expect(MobileNav::shouldRender('user'))->toBeTrue();

    AppPlatform::fake(RuntimePlatform::MobileAppIos);
    expect(MobileNav::shouldRender('user'))->toBeTrue();

    AppPlatform::fake(RuntimePlatform::WebsiteAndroid);
    expect(MobileNav::shouldRender('user'))->toBeTrue();

    AppPlatform::fake(RuntimePlatform::WebsiteIos);
    expect(MobileNav::shouldRender('admin'))->toBeTrue();
});

test('the bottom navigation stays hidden in the electron desktop shell', function (): void {
    AppPlatform::fake(RuntimePlatform::DesktopAppWindows);

    expect(MobileNav::shouldRender('user'))->toBeFalse();
});

test('the bottom navigation is suppressed on auth pages', function (): void {
    AppPlatform::fake(RuntimePlatform::MobileAppAndroid);

    $this->get('/signin')->assertOk()->assertDontSee('fi-bottom-nav');
    // Pendaftaran aktif lagi di `/signup`. Seperti halaman auth lain, ia
    // harus bebas bottom-nav -- kalau tidak, tamu akan melihat nav yang
    // tidak bisa dipakai sebelum login.
    $this->get('/signup')->assertOk()->assertDontSee('fi-bottom-nav');
    $this->get('/auth')->assertOk()->assertDontSee('fi-bottom-nav');
    // Panel storefront sudah di root, jadi reset password juga di root.
    $this->get('/password-reset/request')->assertOk()->assertDontSee('fi-bottom-nav');
    $this->get('/admin/signin')->assertOk()->assertDontSee('fi-bottom-nav');
});

test('the bottom navigation renders on an authenticated page on mobile', function (): void {
    AppPlatform::fake(RuntimePlatform::MobileAppAndroid);

    // Storefront sudah di root, jadi "/user" cuma redirect legacy ke "/home".
    // Yang diuji halaman aslinya.
    getPanelPage('/home')
        ->assertOk()
        ->assertSee('fi-bottom-nav', escape: false)
        ->assertSee('fi-bottom-nav-item', escape: false)
        // The bar must also be marked up for CSS, and must not be left as a
        // desktop-only sidebar substitute.
        ->assertSee('data-mobile-nav', escape: false);
});

test('the bottom navigation is not rendered on the same page on desktop', function (): void {
    AppPlatform::fake(RuntimePlatform::WebsiteWindows);

    getPanelPage('/home')
        ->assertOk()
        ->assertDontSee('fi-bottom-nav', escape: false);
});

test('the admin panel gets a bottom navigation of its own on mobile', function (): void {
    AppPlatform::fake(RuntimePlatform::MobileAppIos);

    getPanelPage('/admin')
        ->assertOk()
        ->assertSee('fi-bottom-nav-item', escape: false);
});

test('every registered panel has its own bottom navigation set', function (): void {
    /*
     * Set item bottom-nav diambil per id panel dari config/app-platform.php.
     * Panel yang TIDAK punya set item -> bar-nya kosong, tanpa error, tanpa
     * jejak di log.
     *
     * Ini persis yang terjadi di storefront setelah panel 'user' dilebur ke
     * panel 'welcome': config masih punya set 'user', panel yang benar-benar
     * merender minta set 'welcome', dan bottom nav hilang total dari
     * aplikasi HP.
     */
    foreach (array_keys(Filament::getPanels()) as $panelId) {
        expect(MobileNav::items($panelId))
            ->not->toBeEmpty("panel {$panelId} tidak punya set item bottom nav");
    }
});

test('each panel gets its own bottom navigation destinations', function (): void {
    $user = MobileNav::items('user');
    $admin = MobileNav::items('admin');

    expect($user)->not->toBeEmpty()
        ->and($admin)->not->toBeEmpty();

    // A bottom bar can only hold a handful of tabs, and the two panels serve
    // different audiences, so they must not resolve to the same destinations.
    // Absolute URLs, because that is what the active-state comparison uses.
    $userUrls = array_map(fn ($item) => $item->getUrl(), $user);
    $adminUrls = array_map(fn ($item) => $item->getUrl(), $admin);

    expect($user[0]->getUrl())->toContain('/home')
        ->and($admin[0]->getUrl())->toContain('/admin/')
        ->and(array_intersect($userUrls, $adminUrls))->toBeEmpty();

    foreach ([...$user, ...$admin] as $item) {
        expect($item->getLabel())->not->toBeEmpty()
            ->and($item->getIcon())->not->toBeEmpty()
            ->and($item->getUrl())->toStartWith('http');
    }
});

test('bottom navigation items stay inside their own panel', function (): void {
    // Storefront hidup di ROOT (/home, /messages, /settings, ...) dan panel
    // admin di /admin/*. Jadi batasnya bukan prefix per panel, tapi "tidak
    // pernah masuk ke panel milik orang lain".
    foreach (MobileNav::items('user') as $item) {
        expect($item->getUrl())->not->toContain('/admin/');
    }

    foreach (MobileNav::items('admin') as $item) {
        expect($item->getUrl())->toContain('/admin/');
    }
});

test('a bottom navigation entry pointing at a missing class is dropped', function (): void {
    config(['app-platform.mobile_nav.items.user' => [
        ['label' => 'Beranda', 'icon' => 'heroicon-o-home', 'url' => 'App\\Does\\Not\\Exist'],
        ['label' => 'Cari', 'icon' => 'heroicon-o-magnifying-glass', 'url' => '/user/cari'],
    ]]);

    MobileNav::flush();

    $items = MobileNav::items('user');

    expect($items)->toHaveCount(1)
        ->and($items[0]->getLabel())->toBe('Cari');
});

// â”€â”€ Responsive tables â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

test('the card-table script is emitted on mobile and omitted on desktop', function (): void {
    AppPlatform::fake(RuntimePlatform::MobileAppAndroid);
    $this->get('/signin')->assertOk()->assertSee('fi-table-cards', escape: false);

    AppPlatform::fake(RuntimePlatform::WebsiteWindows);
    $this->get('/signin')->assertOk()->assertDontSee('fi-table-cards', escape: false);
});

test('the card-table script can be turned off through config', function (): void {
    AppPlatform::fake(RuntimePlatform::MobileAppAndroid);
    config(['app-platform.responsive_tables.enabled' => false]);

    $this->get('/signin')->assertOk()->assertDontSee('fi-table-cards', escape: false);
});

// â”€â”€ The runtime probe â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

test('the runtime script reports the resolved platform to the client', function (): void {
    AppPlatform::fake(RuntimePlatform::MobileAppIos);

    $response = $this->get('/signin')->assertOk();

    $response->assertSee('mobile_app_ios', escape: false)
        ->assertSee('window.AppPlatform', escape: false)
        // The one-shot reload that teaches the server it is inside a shell.
        ->assertSee('app_shell=', escape: false);
});

