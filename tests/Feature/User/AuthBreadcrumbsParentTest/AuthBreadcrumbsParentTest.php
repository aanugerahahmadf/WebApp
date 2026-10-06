<?php

/**
 * `HasAuthBreadcrumbs` -- pemilihan parent crumb untuk halaman auth yang
 * memakai trait ini.
 *
 * Berbeda dengan `SignIn`/`SignUp` yang overriding `getBreadcrumbs()` dengan
 * parent tetap, halaman OTP, verifikasi email, dan reset kata sandi membiarkan
 * trait yang menebak parent dari "asal klik": `url()->previous()` lalu
 * `session('url.intended')`, dengan daftar pengecualian per jenis halaman.
 *
 * Trait ini sebelumnya nyaris tanpa test: satu-satunya penjaga adalah
 * `UserAuthLandingPageTest` yang memeriksa crumb halaman auth AWAL, yang justru
 * tidak memakai trait. Semua cabang di `resolveAuthParentCrumb()` -- flash
 * `after_logout`, complete-profile, verifikasi email, reset kata sandi, verify
 * OTP, `/welcome`, `/user/`, serta kasus tanpa kandidat -- belum pernah
 * dieksekusi.
 *
 * Cara mengujinya: trait diuji melalui kelas anonim yang memakainya, dengan
 * wrapper publik untuk method protected-nya. Ini disengaja -- menguji lewat
 * HTTP untuk setiap cabang berarti ikut menguji seluruh rendering tiap halaman
 * OTP, sementara yang perlu dijaga di sini murni keputusan "crumb mana yang
 * dipilih".
 */

use App\Filament\User\Auth\Concerns\HasAuthBreadcrumbs;
use App\Filament\Welcome\Pages\Home\Home as WelcomeHome;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
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
 * Objek uji trait: memakainya, lalu membuka dua method protected-nya
 * supaya bisa dipanggil dari test. Nama methodnya sudah diubah agar tidak
 * bentrok dengan method trait aslinya.
 */
function authBreadcrumbProbe(): object
{
    return new class
    {
        use HasAuthBreadcrumbs;

        public function currentLabel(): string
        {
            return $this->getAuthBreadcrumbsCurrentLabel();
        }

        public function parentCrumb(): ?array
        {
            return $this->resolveAuthParentCrumb();
        }
    };
}

/**
 * Simulasikan "tamu datang dari halaman X": referer untuk `url()->previous()`,
 * flash session untuk `url.intended`.
 *
 * Path request yang dipakai sengaja bukan salah satu origin yang diuji.
 * Trait membandingkan candidate dengan `url()->current()`, jadi kalau origin
 * sama dengan path request, candidate itu terbawa sebagai "halaman yang sama"
 * dan dibuang -- bukan karena trait salah, tapi karena skenario "asal klik =
 * halaman yang sedang dibuka" memang tidak masuk akal dan memang tidak boleh
 * jadi parent crumb.
 *
 * `o-t-p-verify` dipilih karena itu halaman auth yang benar-benar ada dan
 * tidak pernah dipakai sebagai referer di test manapun.
 */
function arrivingFrom(?string $referer, ?string $intended = null): object
{
    $request = Request::create('/user/o-t-p-verify', 'GET');

    if ($referer !== null) {
        $request->headers->set('referer', $referer);
    }

    app()->instance('request', $request);

    session()->forget('url.intended');

    if ($intended !== null) {
        session(['url.intended' => $intended]);
    }

    return authBreadcrumbProbe();
}

test('the current crumb label falls back to the class name when there is no heading', function (): void {
    // Halaman yang tidak punya getHeading()/getTitle() (mis. wrapper Livewire)
    // harus tetap punya label, kalau tidak crumb-nya kosong. Tanpa heading,
    // trait memakai nama class -- dan untuk probe anonim itu namanya bukan
    // "VerifyOtp", jadi yang dikunci sifatnya: label ada dan sama dengan nama
    // class yang dipakainya.
    $probe = authBreadcrumbProbe();

    expect($probe->currentLabel())
        ->toBe(class_basename($probe::class))
        ->not->toBe('');
});

test('without a usable origin there is only the current crumb', function (): void {
    // Tidak ada parent berarti array satu elemen -- trait sengaja tidak memaksa
    // "Beranda", karena belum tentu itu benar.
    $crumbs = arrivingFrom(null)->parentCrumb();

    expect($crumbs)->toBeNull();
});

test('a just signed out visitor gets the storefront home as a one shot parent', function (): void {
    // `after_logout` di-flash WelcomeLogoutResponse dan dipakai sekali saja
    // (`session()->pull`), jadi setelah dipakai crumb berikutnya tidak lagi
    // menunjuk ke beranda.
    session(['after_logout' => true]);

    $probe = arrivingFrom(null);

    expect($probe->parentCrumb())
        ->toBe(['url' => route('filament.welcome.pages.home'), 'label' => __('Beranda')]);

    // Dipakai sekali: pemanggilan berikutnya tidak lagi melihat flash-nya.
    expect($probe->parentCrumb())->toBeNull();
});

test('an origin inside the sign in page is reported as the sign in form', function (): void {
    // Misal tamu menekan tombol kembali dari halaman OTP ke form login.
    expect(arrivingFrom('http://localhost/user/signin')->parentCrumb())
        ->toBe(['url' => route('filament.user.auth.login'), 'label' => __('Sign In')]);
});

test('an origin inside the retired sign up page never points at a missing route', function (): void {
    // Sign Up DIHILANGKAN dari panel User: ->registration() dibuang dari
    // UserPanelProvider (jadi route `filament.user.auth.register` hilang),
    // SignIn::registerAction() di->hidden(), dan page class SignUp beserta
    // view serta alias Livewire-nya ikut dihapus.
    //
    // Test versi lama memanggil route('filament.user.auth.register') dan
    // mengharapkan label "Sign Up". Dua-duanya sudah tidak berlaku: crumb
    // itu tidak punya tujuan nyata, dan memanggil route() yang hilang akan
    // melempar RouteNotFoundException -- yang di produksi berarti halaman auth
    // 500 untuk tamu yang datang dengan referer /user/signup.
    //
    // Yang dikunci sekarang: untuk tamu tidak ada parent crumb sama sekali
    // (cabang Sign Up sudah dihapus dari trait), dan route yang hilang itu
    // memang tidak terdaftar -- supaya test ini gagal kalau Sign Up suatu
    // saat dikembalikan tanpa cabangnya ikut dihidupkan lagi.
    expect(arrivingFrom('http://localhost/user/signup')->parentCrumb())->toBeNull();

    expect(Route::has('filament.user.auth.register'))->toBeFalse();

    // Untuk user yang sudah login, referer /user/signup tidak crash dan
    // jatuh ke cabang "/user/ + sudah login" -- label "Kembali", bukan "Sign Up".
    actingAs($this->customer, 'web');

    expect(arrivingFrom('http://localhost/user/signup')->parentCrumb())
        ->toBe(['url' => 'http://localhost/user/signup', 'label' => __('Kembali')]);
});

test('each step of the password reset flow is labelled with its own page', function (string $origin, string $labelKey): void {
    // Tiga langkah harus punya tiga label berbeda; kalau tidak, pengguna
    // kembali ke langkah yang salah tanpa ada perubahan apa pun.
    //
    // Dataset berisi KUNCI label, bukan hasil __(). Penting: __() di dalam
    // ->with([...]) membuat dataset kosong dan Pest melempar DatasetMissing --
    // test-nya gagal sebelum body-nya jalan, jadi penyebabnya mustahil
    // dilacak dari assertion. Terjemahan dihitung di body supaya lokalnya
    // sama dengan saat halaman benar-benar dirender.
    expect(arrivingFrom($origin)->parentCrumb()['label'])->toBe(__($labelKey));
})->with([
    'request a reset' => [
        'http://localhost/user/password-reset/request',
        'Lupa Kata Sandi',
    ],
    'set the new password' => [
        'http://localhost/user/password-reset/reset',
        'Atur Ulang Kata Sandi',
    ],
]);

test('the otp verification step is labelled as such', function (): void {
    // Dua URL jadi satu label: `verify-otp` (slug lama) dan
    // `password-reset/verify` (slug yang sekarang dipakai route
    // filament.user.auth.verify-otp). Keduanya dilayani VerifyOtp, jadi
    // pengguna dari salah satu harus kembali ke langkah yang sama.
    foreach ([
        'http://localhost/user/verify-otp',
        'http://localhost/user/password-reset/verify',
    ] as $origin) {
        expect(arrivingFrom($origin)->parentCrumb()['label'])
            ->toBe(__('Verifikasi Kode OTP'));
    }
});

test('an origin equal to the page being shown is not treated as a parent', function (): void {
    // Trait membuang candidate yang sama dengan `url()->current()`. Kalau tidak,
    // crumb-nya menunjuk ke dirinya sendiri dan pengguna menekan "kembali"
    // tanpa terjadi apa-apa.
    //
    // Skenario ini tidak diuji lewat arrivingFrom() karena path request-nya sudah
    // dipatok di o-t-p-verify; diuji langsung di sini dengan request yang
    // path-nya sama dengan referer.
    $request = Request::create('/user/password-reset/verify', 'GET');
    $request->headers->set('referer', 'http://localhost/user/password-reset/verify');
    app()->instance('request', $request);

    session()->forget('url.intended');

    expect(authBreadcrumbProbe()->parentCrumb())->toBeNull();
});

test('the complete profile step is labelled as such', function (): void {
    expect(arrivingFrom('http://localhost/user/complete-profile')->parentCrumb())
        ->toBe([
            'url' => route('filament.user.pages.complete-profile'),
            'label' => __('Lengkapi Profil Anda'),
        ]);
});

test('the email verification prompt is labelled as such', function (): void {
    expect(arrivingFrom('http://localhost/user/email-verification')->parentCrumb())
        ->toBe([
            'url' => route('filament.user.auth.email-verification.prompt'),
            'label' => __('Verifikasi Email Anda'),
        ]);
});

test('a welcome page origin becomes a generic back crumb', function (): void {
    // Tamu hanya diarahkan ke storefront, dan labelnya jujur: "Kembali".
    // URL asalnya dipertahankan supaya pengguna kembali ke halaman yang sedang
    // dilihat, bukan ke dashboard.
    $origin = 'http://localhost/welcome/flowerdecorationscatalog/4';

    expect(arrivingFrom($origin)->parentCrumb())
        ->toBe(['url' => $origin, 'label' => __('Kembali')]);
});

test('a user panel page is only a valid origin for a signed in visitor', function (): void {
    // Untuk tamu, halaman /user/* butuh auth sehingga tidak bisa jadi "asal
    // klik" yang bermakna; untuk user yang sudah login, URL itu dipakai apa
    // adanya.
    $origin = 'http://localhost/user/wishlists';

    expect(arrivingFrom($origin)->parentCrumb())->toBeNull();

    actingAs($this->customer, 'web');

    expect(arrivingFrom($origin)->parentCrumb())
        ->toBe(['url' => $origin, 'label' => __('Kembali')]);
});

test('livewire endpoints and the admin panel are never treated as an origin', function (string $origin): void {
    // Dua jenis asal klik yang pasti salah: endpoint internal Livewire, dan
    // panel admin yang tidak punya halaman auth sendiri.
    expect(arrivingFrom($origin)->parentCrumb())->toBeNull();
})->with([
    'livewire update' => ['http://localhost/livewire/update'],
    'admin panel' => ['http://localhost/admin/orders'],
]);

test('a single crumb chain is returned when no parent applies', function (): void {
    // Kontrak trait: satu elemen = crumb halaman ini saja, dua elemen =
    // parent + halaman ini. Bentuk return-nya tidak boleh setengah-setengah.
    $probe = arrivingFrom(null);

    expect($probe->parentCrumb())->toBeNull()
        ->and(authBreadcrumbProbe()->currentLabel())->not->toBe('');
});

test('the storefront home crumb is the same destination the landing page uses', function (): void {
    // Sanity check konfigurasi: parent "setelah logout" harus mengarah ke home
    // panel Welcome, bukan ke home panel user (yang butuh auth).
    expect(route('filament.welcome.pages.home'))->toBe(WelcomeHome::getUrl(panel: 'welcome'));
});
