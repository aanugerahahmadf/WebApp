<?php

/**
 * Rantai breadcrumb, judul, dan isi form di halaman Sign-In (`/signin`).
 *
 * Yang dijaga di sini:
 *
 *   1. Parent crumb halaman Sign In adalah HALAMAN AUTH (`/auth`), bukan
 *      Welcome Home dan bukan "halaman asal klik". Lihat
 *      `SignIn::getBreadcrumbs()`: ia meng-override trait HasAuthBreadcrumbs
 *      dengan tepat dua crumb, `[Auth::getUrl() => Auth::crumbLabel(), label
 *      untuk halaman ini sendiri]`.
 *
 *   2. Crumb terakhir adalah heading halaman ini ("Sign In") dan ditandai
 *      `aria-current="page"`. Parent-nya memakai label yang sama dengan judul
 *      halaman induk ("Welcome Back"), dan itu HARUS berbeda dari "Sign In" --
 *      kalau tidak, crumb-nya jadi "Sign In / Sign In".
 *
 *   3. `url.intended` tidak pernah jadi crumb parent. Nilainya milik tombol
 *      back, bukan breadcrumb; lihat `SignIn::getBackUrl()` yang diuji di
 *      `tests/Feature/signinBackUrlTest/SignInBackUrlTest.php`.
 *
 *   4. Tidak ada crumb "Beranda" maupun label generik "Kembali" di halaman
 *      auth: keduanya mengarahkan tamu ke storefront, padahal alur auth
 *      bercabang dari `/auth`.
 *
 *   5. `<title>` ikut heading ("Sign In"), bukan "Login" bawaan Filament --
 *      lihat `SignIn::getTitle()`.
 *
 *   6. Action login/register bawaan Filament disembunyikan
 *      (`loginAction()->hidden()`, `registerAction()->hidden()`), jadi form ini
 *      punya SATU tombol submit. Kalau salah satunya bocor, Filament merender
 *      tombol submit ganda yang sama-sama mencoba mengautentikasi.
 *
 *   7. Field tersembunyi `agreement` dan `remember` harus ada di schema form.
 *      `authenticate()` membaca keduanya dari `$this->data`, bukan dari DOM --
 *      kalau field-nya hilang, nilainya null dan semua login ditolak padahal
 *      checkbox di layar sudah dicentang.
 *
 *   8. Tidak ada pintu pendaftaran di form ini. `registerAction()` bawaan
 *      Filament disembunyikan (lihat butir 6), jadi form Sign In hanya boleh
 *      punya SATU pintu auth: tombol submit. Pendaftaran sendiri hidup di
 *      halaman terpisah (`/signup`, route `filament.welcome.auth.register`) --
 *      bukan di dalam form ini.
 *
 * CATATAN SOAL NAMA FILE: file ini dulunya `SignInBackButtonTest`, padahal
 * tidak pernah menguji `getBackUrl()` sama sekali -- yang diuji adalah
 * breadcrumb. Test back button yang sebenarnya sekarang ada di
 * `tests/Feature/signinBackUrlTest/SignInBackUrlTest.php`.
 *
 * CATATAN SOAL LOKALE: expectation sengaja memakai `__()` yang dievaluasi
 * SETELAH request, bukan literal Bahasa Indonesia. Locale suite tidak dikunci
 * (`config/app.php` + middleware `SetLocale` yang membaca query/session/header),
 * jadi test yang menulis "Beranda" atau "Selamat Datang Kembali" secara
 * harfiah akan gagal atau -- lebih buruk -- hijau karena kebetulan. Yang dikunci
 * di sini adalah struktur crumb: URL parent, label, dan `aria-current`.
 */

use App\Filament\User\Auth\Auth\Auth;
use App\Filament\User\Auth\SignIn\SignIn;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Method halaman Filament yang memanggil route()/getUrl() butuh panel aktif.
    Filament::setCurrentPanel(Filament::getPanel('user'));

    $this->customer = \App\Models\User\User::factory()->create();
    $this->customer->assignRole(
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'customer'])
    );
});

afterEach(function (): void {
    Filament::setCurrentPanel(null);
});

/**
 * Potong markup `<nav aria-label="breadcrumb">` supaya assertion tidak bisa
 * lolos karena string yang sama muncul di tempat lain (mis. di `<title>`).
 */
function breadcrumbNav(string $html): string
{
    $start = strpos($html, '<nav aria-label="breadcrumb"');

    if ($start === false) {
        return '';
    }

    $end = strpos($html, '</nav>', $start);

    if ($end === false) {
        return '';
    }

    return substr($html, $start, $end - $start);
}

/**
 * Terjemahan yang sudah di-escape seperti yang Blade tulis di HTML.
 *
 * Assertion di file ini membandingkan string markup mentah dengan
 * `expect($html)->toContain(...)`, yang tidak melakukan escaping. Label yang
 * mengandung tanda baca (kutip, &) muncul di HTML sebagai entity --
 * "Don't have an account yet?" jadi `Don&#039;t have an account yet?` --
 * jadi perbandingan harus memakai bentuk yang sama. `assertSee($label,
 * escape: false)` adalah jalan lain, tapi helper ini dipakai supaya tetap satu
 * gaya di seluruh file.
 *
 * `__()` dipanggil di dalam helper, bukan di luar, supaya ikut locale yang
 * benar-benar dipakai saat render.
 */
function label(string $key): string
{
    return e(__($key));
}

/**
 * Instansiasi halaman tanpa constructor: constructor Livewire butuh container
 * page yang sudah di-boot, sedangkan yang diuji di sini hanya method murni
 * (getBreadcrumbs/getTitle/getHeading). Pola yang sama dipakai
 * UserAuthLandingPageTest.
 */
function signInPage(): SignIn
{
    return (new \ReflectionClass(SignIn::class))->newInstanceWithoutConstructor();
}

test('the sign in breadcrumb chain is welcome back then sign in', function (): void {
    $crumbs = signInPage()->getBreadcrumbs();

    expect(array_keys($crumbs))
        ->toHaveCount(2)
        // Parent: halaman auth, bukan storefront.
        ->and(array_keys($crumbs)[0])->toBe(Auth::getUrl())
        ->and(array_values($crumbs)[0])->toBe(Auth::crumbLabel())
        // Crumb terakhir: nama cabang ini sendiri.
        ->and(array_values($crumbs)[1])->toBe(__('Sign In'))
        // Parent TIDAK boleh Welcome Home -- inilah yang diropaui test lama.
        ->and(array_keys($crumbs)[0])->not->toBe(route('filament.welcome.pages.home'));
});

test('the rendered breadcrumb nav links the parent to the auth landing page', function (): void {
    $nav = breadcrumbNav($this->get(route('filament.welcome.auth.login'))->assertOk()->getContent());

    // `__()` dievaluasi setelah request supaya ikut locale yang benar-benar
    // dipakai saat render.
    expect($nav)
        ->toContain('href="'.Auth::getUrl().'"')
        ->toContain(label('Welcome Back'))
        ->toContain(label('Sign In'))
        // Crumb terakhir ditandai sebagai halaman aktif.
        ->toContain('aria-current="page"')
        // Parent-nya halaman auth, bukan storefront maupun halaman akun.
        ->not->toContain('href="'.route('filament.welcome.pages.home').'"')
        ->not->toContain('href="'.route('filament.welcome.pages.home').'"');

    // Tepat dua crumb: satu parent (halaman auth) dan halaman ini sendiri --
    // hanya parent yang berupa link. Jumlah link dihitung, bukan negasi label,
    // karena "Beranda"/"Kembali" dalam Bahasa Inggris ("Home"/"Back") bisa muncul
    // sebagai substring dari label lain: "Welcome Back" mengandung "Back".
    expect(substr_count($nav, '<a'))->toBe(1);
});

test('the parent crumb is named after the landing page, not after this form', function (): void {
    // Kalau parent memakai nama form-nya sendiri, SignIn akan tampil sebagai
    // "Sign In / Sign In".
    expect(Auth::crumbLabel())->toBe(__('Welcome Back'))
        ->and(Auth::crumbLabel())->not->toBe(signInPage()->getHeading());
});

test('url intended never becomes the breadcrumb parent', function (string $intended): void {
    // url.intended milik tombol back (SignIn::getBackUrl()), bukan breadcrumb.
    // GuestAddToCart menaruhnya di session setiap kali tamu menekan aksi yang
    // butuh login, jadi nilai realistis di sini bukan nilai remeh.
    $nav = breadcrumbNav(
        $this->withSession(['url.intended' => $intended])
            ->get(route('filament.welcome.auth.login'))
            ->assertOk()
            ->getContent()
    );

    expect($nav)
        ->toContain('href="'.Auth::getUrl().'"')
        ->not->toContain('href="'.$intended.'"');
})->with([
    'package detail' => ['http://localhost/welcome/flowerdecorationspackagecatalog/2'],
    'catalog detail' => ['http://localhost/flowerdecorationscatalog/7'],
    'catalog index' => ['http://localhost/flowerdecorationscatalog'],
    'dead sign up url' => ['http://localhost/signup'],
]);

test('a signed in visitor sees the same chain, not an account crumb', function (): void {
    // Login tidak mengubah alur crumb auth; yang berubah hanya topbar panel user.
    actingAs($this->customer, 'web');

    $crumbs = signInPage()->getBreadcrumbs();

    expect(array_keys($crumbs)[0])->toBe(Auth::getUrl())
        ->and(implode(' ', array_keys($crumbs)))
        ->not->toContain(route('filament.welcome.pages.home'));
});

test('the page title follows the heading instead of the filament default', function (): void {
    $page = signInPage();

    // Base class Filament mengembalikan "Login" untuk title; tanpa override ini
    // heading kartunya "Sign In" tapi tab browser "Login". Filament menempelkan
    // nama brand di belakang judul, jadi yang dikunci adalah bagian.awalnya.
    expect((string) $page->getTitle())->toBe(__('Sign In'))
        ->and((string) $page->getTitle())->toBe((string) $page->getHeading());

    $html = $this->get(route('filament.welcome.auth.login'))->assertOk()->getContent();

    expect($html)->toMatch('/<title>\s*'.preg_quote(e(__('Sign In')), '/').'\s*-/');
});

test('the form has exactly one submit action', function (): void {
    // Action bawaan Filament (login + register) sengaja disembunyikan supaya
    // form ini tidak punya dua tombol submit. Yang diuji adalah akibatnya --
    // jumlah action yang terlihat dan jumlah tombol submit di HTML -- bukan
    // memanggil method-nya: `SignIn::loginAction()` memanggil
    // `parent::loginAction()` yang tidak ada di Filament 3.3, jadi method itu
    // sudah tidak bisa dipanggil tanpa melempar BadMethodCallException
    // (reported sebagai temuan, bukan expectation).
    $actions = Livewire::test(SignIn::class)->instance()->getCachedFormActions();

    expect($actions)->not->toBeEmpty()
        ->and(collect($actions)->filter(fn ($action): bool => ! $action->isHidden())->count())->toBe(1);

    $html = $this->get(route('filament.welcome.auth.login'))->assertOk()->getContent();

    expect(substr_count($html, 'type="submit"'))->toBe(1);
});

test('the forgot password link still points at the password reset route', function (): void {
    // Yang dikunci adalah URL, bukan labelnya: label yang tampil sekarang berasal
    // dari kunci Filament sendiri ("Forgot password?"), bukan dari override
    // `SignIn::passwordResetAction()` -- dan itu boleh berubah saat Filament
    // naik versi. Yang tidak boleh hilang adalah jalurnya: tanpa link ini tamu
    // yang lupa kata sandi tidak punya jalan keluar dari form.
    // Panel storefront hidup di root, jadi jalur reset password juga di root
    // (dulu /user/password-reset/request).
    expect(route('filament.welcome.auth.password-reset.request'))->toContain('/password-reset/request');

    $html = $this->get(route('filament.welcome.auth.login'))->assertOk()->getContent();

    expect($html)->toContain('href="'.route('filament.welcome.auth.password-reset.request').'"');
});

test('the form state carries hidden agreement and remember fields', function (): void {
    $form = Livewire::test(SignIn::class)->instance()->form;

    $hiddenNames = collect($form->getComponents(withHidden: true))
        ->filter(fn ($component): bool => $component instanceof Hidden)
        ->map(fn ($component): string => (string) $component->getName())
        ->all();

    sort($hiddenNames);

    // authenticate() membaca $this->data['agreement'] dan ['remember']; tanpa
    // dua field ini nilainya null dan login selalu ditolak.
    expect($hiddenNames)->toBe(['agreement', 'remember']);
});

test('the sign in page no longer offers a google door, it lives on the landing page', function (): void {
    // PERUBAHAAN PERILAKU pada dua hal yang tadinya satu test:
    //
    // 1. Test ini sebelumnya bernama "no longer offers a sign up door, because
    //    none exists" -- benar saat pendaftaran email/password dinonaktifkan.
    //    Sekarang pendaftaran AKTIF (`UserPanelProvider` memanggil
    //    `->registration(SignUp::class)`), jadi `/signup` adalah halaman
    //    nyata dan link dari Sign In ke sana memang benar adanya.
    //
    // 2. Yang TETAP melarang adalah tombol Google: `social-buttons` di
    //    `/signin` dirender dengan `showGoogleButton=false`, karena pintu
    //    OAuth hanya di `/auth`. Kalau itu berubah, ada dua pintu OAuth
    //    yang harus dijaga sinkron. Test di bawah ("drops the google button")
    //    sudah menjaganya; yang dijaga di sini adalah label-door Sign Up tidak
    //    lagi dilarang muncul, sementara label Google tetap dilarang.
    $html = $this->get(route('filament.welcome.auth.login'))->assertOk()->getContent();

    expect($html)
        ->not->toContain(label('Daftar dengan Google'))
        // Tidak ada lagi larangan atas `/signup`: itu halaman yang hidup.
        // Yang masih dilarang adalah `?register` gaya lama, karena door-nya
        // sekarang punya slug sendiri.
        ->not->toContain('?register');
});

test('the sign up page is a real page, and the old slug still redirects to it', function (): void {
    // `/signup` sekarang halaman utuh: `->registration(SignUp::class)` dan
    // `->registrationRouteSlug('signup')` di UserPanelProvider mendaftarkan
    // route-nya.
    //
    // `/user/register` tetap alias: itu slug default Filament SEBELUM
    // `registrationRouteSlug('signup')`, dan `routes/web/web.php` masih
    // mengarahkan ke `/signup`. Yang dijaga adalah alias itu tidak hilang
    // tanpa jejak -- bookmark lama harus tetap sampai ke form pendaftaran.
    expect(Route::has('filament.welcome.auth.register'))->toBeTrue();

    $this->get('/signup')->assertOk();
    $this->get('/user/register')->assertRedirect('/signup');
});

test('the sign in form drops the google button, which now lives on the landing page', function (): void {
    // Penjaga Entrance page: tombol Google hanya boleh ada di /auth. Kalau
    // partial social-buttons dirender lagi di sini tanpa showGoogleButton=false,
    // ada dua pintu OAuth yang harus dijaga sinkron.
    expect($this->get(route('filament.welcome.auth.login'))->assertOk()->getContent())
        ->not->toContain(label('Masuk Dengan Google'));
});

test('the consent checkboxes are on the sign in form, unlike on the landing page', function (): void {
    // Kebalikan dari /auth: di form, "Ingat Saya" + persetujuan syarat WAJIB
    // ada karena authenticate() menolak tanpa keduanya (lihat
    // SignInAuthenticationTest). Checkbox hilang = validasi tidak pernah bisa
    // terpenuhi padahal formnya masih 200.
    $html = $this->get(route('filament.welcome.auth.login'))->assertOk()->getContent();

    expect($html)
        ->toContain('id="remember-me-checkbox"')
        ->toContain('id="agreement-checkbox"')
        ->toContain(label('Ingat Saya'))
        ->toContain(label('Perjanjian Pengguna'));
});
