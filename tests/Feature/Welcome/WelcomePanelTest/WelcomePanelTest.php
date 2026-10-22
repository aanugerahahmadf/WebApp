<?php

use App\Enums\RuntimePlatform\RuntimePlatform;
use App\Http\Middleware\AuthenticateWelcome\AuthenticateWelcome;
use App\Http\Middleware\EnsureProfileComplete\EnsureProfileComplete;
use App\Models\User\User;
use App\Support\AppPlatform\AppPlatform;
use Database\Factories\HistoryFactory;
use Filament\Facades\Filament;
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
    // `super_admin` dibuat di KEDUA guard. User di bawah adalah
    // `App\Models\User\User` (panel user dan welcome), dan `assignRole()`
    // mencari role di guard model itu sendiri -- yaitu `web`. Baris versi
    // `admin` milik tabel `admins` tetap dibuat supaya test yang membandingkan
    // konfigurasi ketiga panel punya referensi yang sama.
    seedRoles(adminRoles: ['super_admin'], webRoles: ['super_admin', 'customer']);

    $this->user = User::factory()->create();
    $this->user->assignRole('super_admin');

    actingAs($this->user, 'web');
});

/**
 * Follows the panel's own redirect chain, so asserting on /welcome and on
 * /home give the same page rather than one being a 302.
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
 * Buang bagian dari `getMiddleware()` yang memang BOLEH berbeda antar panel.
 *
 * Ada dua, dan keduanya disengaja:
 *
 * 1. Marker route-group `panel:<id>` yang ditambahkan Filament di depan. Id-nya
 *    memang berbeda, jadi membandingkannya apa adanya selalu gagal.
 *
 * 2. PARAMETER pada middleware yang dipakai bersama. `CheckWebsiteMaintenance`
 *    menerima argumen berupa nama panel -- `CheckWebsiteMaintenance:user` di panel
 *    user, `CheckWebsiteMaintenance:welcome` di panel welcome -- jadi middleware
 *    yang sama dengan parameter berbeda. Membandingkan string utuh sama
 *    masalahnya dengan (1): yang dibandingkan bukan jenis middleware-nya,
 *    tapi panel mana yang ditujunya.
 *
 * Fungsi ini mengembalikan (nama middleware, parameter yang dinormalisasi)
 * supaya yang dibandingkan benar-benar "middleware yang sama", bukan
 * "middleware + tujuan yang kebetulan sama". Kalau nama panel ini dihapus
 * dari halaman depan someday, Assertion ini jadi longgar di satu tempat --
 * dan hanya di situ.
 */
function stripPanelMarker($panel): array
{
    return array_values(array_map(
        static function (string $middleware): string {
            // `Foo\bar:baz` -> `Foo\bar` + placeholder
            if (! str_contains($middleware, ':')) {
                return $middleware;
            }

            [$class, $parameter] = explode(':', $middleware, 2);

            // Parameter yang berupa id panel dinormalisasi, karena itu memang
            // berbeda antar panel.
            return in_array($parameter, ['user', 'welcome', 'admin'], true)
                ? $class.':<panel>'
                : $middleware;
        },
        array_filter(
            $panel->getMiddleware(),
            fn ($middleware) => ! str_starts_with($middleware, 'panel:'),
        )
    ));
}

test('the welcome panel is registered', function (): void {
    // Path panel welcome sekarang ROOT ('/'), bukan '/welcome'. Panel
    // 'user' sudah dilebur ke sini, jadi seluruh storefront, auth, dan
    // akun hidup di satu panel tanpa prefix.
    expect(Filament::getPanel('welcome')->getId())->toBe('welcome')
        ->and(Filament::getPanel('welcome')->getPath())->toBe('/');
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
    '/orders',
    '/histories',
    '/flowerdecorationscatalog',
    '/vouchers',
    '/wishlists',
    '/carts',
    '/reviews',
    // `/welcome/flowerdecorationspackagecatalog` dihapus dari dataset ini:
    // `PackageResource`-nya sudah tidak ada sejak penataan ulang
    // `Product` -> `Catalog`, jadi URL-nya 404. Test ini tadinya berbunyi
    // "panel welcome bisa diakses" untuk setiap halaman yang terdaftar, dan
    // sekarang berubah menjadi "panel welcome punya halaman yang tidak lagi
    // ada" -- dengan 404-nya menutupi refactor yang belum tuntas.
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

    $this->get('/histories')->assertOk();
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
     * Aturan di sini tetap ketat: TIDAK ADA file di app/Filament/Welcome
     * yang boleh memakai class milik App\Filament\User.
     *
     * Dulu ada satu pengecualian (Welcome\Pages\Home yang mengarahkan user
     * yang sudah login ke home panel user). Redirect itu sudah dihapus --
     * panel user sekarang adalah panel yang sama dengan panel welcome, jadi
     * panoutu tidak mungkin lagi -- dan panel user yang tidak ada lagi tidak
     * mungkin menyalin class ke folder Welcome.
     *
     * Daftar rujukan yang bocor harus KOSONG. Begitu ada satu saja, test
     * ini gagal -- itulah gunanya aturan ini.
     */
    expect($leaked)->toBe([]);
});

/*
 * The excluded block. The Welcome panel must NOT inherit the User panel's OTP
 * auth pages or its custom user menu: those items point at App\Filament\User
 * classes, so they would resolve to /user URLs and navigate out of /welcome.
 * The absence is asserted positively rather than by comparing page classes,
 * because Filament exposes has*() rather than a getter that can return null.
 *
 * Catatan: `hasRegistration()` FALSE untuk panel WELCOME dan ADMIN yang
 * menjadi bagian dari kontrak ini, bukan kebetulan. Panel USER berbeda --
 * pendaftaran email/password sudah diaktifkan kembali, jadi `hasRegistration()`
 * di sana `true` dan halaman Sign Up hidup di `/signup`.
 */
test('the welcome panel carries the auth pages of the merged storefront', function (): void {
    $welcome = Filament::getPanel('welcome');
    $admin = Filament::getPanel('admin');

    // Panel welcome MENYATU dua panel lama: storefront tamu DAN seluruh
    // halaman akun. Karena itu auth page milik panel user (Sign In, Sign
    // Up, reset password, verifikasi email) sekarang hidup di sini, di
    // /signin, /signup, dan friends -- bukan di panel terpisah.
    expect($welcome->hasLogin())->toBeTrue()
        ->and($welcome->hasPasswordReset())->toBeTrue()
        ->and($welcome->hasEmailVerification())->toBeTrue()
        ->and($welcome->hasRegistration())->toBeTrue()
        // Panel admin tetap Google-only: tidak ada pendaftaran email/password.
        ->and($admin->hasRegistration())->toBeFalse();
});

/*
 * "profile" is the one user-menu entry that renders as a labelled Profile item
 * in Filament's top navigation. It is part of the excluded block, so Welcome
 * keeps the stock menu and the top nav shows only the stock entries.
 */
test('the welcome panel keeps the storefront user menu, without the stock profile item', function (): void {
    $welcome = Filament::getPanel('welcome');

    // Menu ini milik storefront: pengaturan, riwayat, ulasan, privacy,
    // bantuan. 'profile' ikut ada karena halaman profil panel user sudah
    // dilebur ke sini, dan Filament menambahkannya sendiri ke user menu
    // selama panel punya halaman profil.
    expect($welcome->getUserMenuItems())->toHaveKey('pengaturan')
        ->and($welcome->getUserMenuItems())->toHaveKey('riwayat')
        ->and($welcome->getUserMenuItems())->toHaveKey('ulasan')
        ->and($welcome->getUserMenuItems())->toHaveKey('privacy')
        ->and($welcome->getUserMenuItems())->toHaveKey('bantuan')
        ->and($welcome->getUserMenuItems())->toHaveKey('logout');
});

test('the welcome panel keeps the storefront layout configuration', function (): void {
    $welcome = Filament::getPanel('welcome');

    // Dulu test ini membandingkan panel welcome dengan panel user ("harus
    // sama persis"). Panel user sudah dilebur ke panel welcome, jadi
    // perbandingannya jadi sia-sia -- panel dengan dirinya sendiri. Yang
    // diuji sekarang adalah KONFIGURASI NYA, supaya tidak ada yang hilang
    // diam-diam saat merge.
    expect($welcome->hasTopNavigation())->toBeTrue()
        ->and($welcome->hasSpaMode())->toBeTrue()
        ->and($welcome->getBrandName())->not->toBeEmpty()
        ->and($welcome->getBrandLogo())->not->toBeEmpty()
        ->and($welcome->getFontFamily())->not->toBeEmpty()
        ->and($welcome->getDefaultThemeMode())->not->toBeEmpty()
        ->and($welcome->getAuthGuard())->toBe('web')
        ->and($welcome->getColors())->not->toBeEmpty()
        ->and($welcome->getNavigationGroups())->not->toBeEmpty();
});

/*
 * Gate panel welcome, dan urutannya itu bagian dari kontrak.
 *
 * Daftar ditulis persis (bukan "mengandung"), dalam urutan yang sama seperti
 * saat didaftarkan di UserMulti2faPlugin::register() + WelcomePanelProvider,
 * supaya perubahan urutan diam-diam ikut terlihat di sini.
 */
test('the welcome panel lets guests in while still running the 2FA gate', function (): void {
    $welcome = Filament::getPanel('welcome');

    // Gate-nya berurutan, dan urutannya itu yang penting:
    //
    //   1. CheckTrustedDevice  -- 2FA, hanya berlaku untuk yang sudah login
    //   2. AuthenticateWelcome  -- gerbang TAMU: tamu yang lolos diarahkan
    //                             ke halaman auth landing (/auth), bukan
    //                             ditolak 403
    //   3. EnsureProfileComplete -- profil wajib lengkap sebelum masuk panel
    //
    // Dulu panel welcome punya gate sendiri (cuma AuthenticateWelcome) dan
    // panel user punya gate 2FA. Setelah merge keduanya jadi satu rantai.
    expect($welcome->getAuthMiddleware())->toBe([
        CheckTrustedDevice::class,
        AuthenticateWelcome::class,
        EnsureProfileComplete::class,
    ]);
});

test('the welcome panel renders without the mobile pagination pill', function (): void {
    AppPlatform::fake(RuntimePlatform::MobileAppAndroid);

    // The pill restyled Filament's paginator and the user panel no longer has
    // one, so nothing may re-inject it.
    $this->get('/histories')
        ->assertOk()
        ->assertDontSee('fi-mobile-pagination-root', escape: false);
});

