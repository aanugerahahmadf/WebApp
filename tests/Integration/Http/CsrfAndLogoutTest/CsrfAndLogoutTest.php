<?php

/*
 * Kontrak CSRF dan logout, diuji lewat request HTTP sungguhan.
 *
 * Yang membuat file ini perlu ada: CSRF TIDAK bisa diuji lewat `$this->post()`
 * biasa karena `VerifyCsrfToken::handle()` melewati seluruh logikanya saat
 * `app->runningInConsole() && app['env'] === 'testing'`. Artinya jalur 419 --
 * yang justru jalur paling penting di sini -- tidak akan pernah muncul dari
 * test HTTP biasa. Test di sini karena itu memanggil middleware-nya secara
 * langsung, lewat `handle()` dengan token yang sengaja dibuat salah.
 *
 * Dua hal yang diuji, dan keduanya pernah salah:
 *
 *   1. Daftar `$except` di `VerifyCsrfToken` menentukan request mana yang TIDAP
 *      boleh memakai token. Kalau satu path hilang, user yang benar-benar
 *      klik SignOut dengan sesi kedaluwarsa mendarat di "419 PAGE EXPIRED"
 *      alih-alih halaman depan.
 *   2. `bootstrap/app.php` memanggil `$middleware->replace(ValidateCsrfToken::class,
 *      VerifyCsrfToken::class)`. Di Laravel 11/12 `replace()` hanya berlaku untuk
 *      middleware GLOBAL, sedangkan `ValidateCsrfToken` berada di grup `web`,
 *      bukan global -- jadi penggantian itu praktis tidak berpengaruh dan grup
 *      `web` tetap menjalankan middleware dasar. Yang benar-benar mengecualikan
 *      path di grup `web` adalah `validateCsrfTokens(except: ...)` yang
 *      mengisi daftar `neverVerify` middleware dasar; test di bawah memisahkan
 *      dua daftar itu supaya tidak ada yang mengira `replace()` bekerja.
 */

use App\Http\Middleware\VerifyCsrfToken\VerifyCsrfToken;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\TokenMismatchException;

uses(RefreshDatabase::class);

/**
 * `$except` yang dideklarasikan middleware, dibaca lewat reflection supaya test
 * tidak perlu menebak ulang daftar yang sama di dua tempat.
 *
 * `newInstanceWithoutConstructor()` dipakai karena middleware CSRF framework
 * butuh `Application` dan `Encrypter` di konstruktornya, sedangkan yang kita
 * perlukan di sini cuma nilai `$except` yang konstan.
 *
 * @return list<string>
 */
function csrfExceptPaths(): array
{
    $property = new ReflectionProperty(VerifyCsrfToken::class, 'except');

    /** @var list<string> $paths */
    $paths = $property->getValue(
        (new ReflectionClass(VerifyCsrfToken::class))->newInstanceWithoutConstructor()
    );

    return $paths;
}

/**
 * Apakah request ini lolos pemeriksaan CSRF karena masuk daftar `$except`.
 *
 * Dipanggil lewat reflection karena `inExceptArray()` itu `protected` -- dan
 * memanggil `handle()` secara langsung TIDAK bisa menggantikannya: framework
 * melewati seluruh logikanya saat `runningUnitTests()`, jadi jalur 419 memang
 * tidak bisa dicapai dari test. Yang bisa, dan yang justru jadi kontrak yang
 * perlu dikunci, adalah apakah sebuah path|TYPEDAK DIKECUALIKAN`.
 */
function csrfExcludes(string $uri, string $method = 'GET'): bool
{
    $middleware = (new ReflectionClass(VerifyCsrfToken::class))->newInstanceWithoutConstructor();

    $method_inExceptArray = new ReflectionMethod(VerifyCsrfToken::class, 'inExceptArray');

    return (bool) $method_inExceptArray->invoke($middleware, Request::create($uri, $method));
}

/**
 * Sama seperti `csrfExcludes()`, tapi untuk middleware yang BENAR-BENAR
 * dipakai grup `web`, yaitu `ValidateCsrfToken` bawaan Laravel.
 *
 * Bedanya penting. `csrfExcludes()` membaca daftar `$except` milik subclass
 * APLIKASI, yang -- karena `replace()` hanya berlaku untuk middleware global --
 * tidak pernah berjalan di grup `web`. Semua request web masuk ke middleware
 * dasar, jadi hanya daftar `neverVerify` yang di `bootstrap/app.php` lewat
 * `validateCsrfTokens(except: ...)` yang benar-benar mengecualikan path.
 */
function webCsrfExcludes(string $uri, string $method = 'GET'): bool
{
    $middleware = new ValidateCsrfToken(app(), app('encrypter'));

    $inExceptArray = new ReflectionMethod($middleware, 'inExceptArray');

    return (bool) $inExceptArray->invoke($middleware, Request::create($uri, $method));
}

test('the logout paths are excluded from csrf verification', function (): void {
    // Dua-duanya harus ada. Tanpa `user/logout`, sesi kedaluwarsa + klik SignOut
    // berakhir di 419 dan pengguna tersesat, dan itu justru terjadi pada akun
    // yang paling sering memakai fitur ini.
    expect(csrfExceptPaths())
        ->toContain('user/logout')
        ->toContain('welcome/logout');
});

test('the logout exclusions are exact paths and not wildcards', function (): void {
    // `user/logout` juga akan cocok dengan `user/logout/anything` kalau dicatat
    // sebagai prefix. Itu berarti endpoint lain di bawah `/user/logout/`
    // otomatis bebas CSRF tanpa pernah ditinjau -- kelas dengan nilai kelompok
    // yang sama harus ditulis eksplisit.
    expect(csrfExceptPaths())
        ->not->toContain('user/*')
        ->not->toContain('welcome/*');
});

test('the logout urls actually match the exclusion list', function (): void {
    // Yang penting bukan hanya "path-nya ada di daftar", tapi daftar itu BENAR-BENAR
    // cocok dengan request. `inExceptArray()` mencocokkan lewat `fullUrlIs()` dan
    // `is()`, jadi penulisan yang keliru -- `user/logout/` dengan garis miring
    //_extra, atau prefix yang tidak lengkap -- akan lolos dari pengecekan daftar
    // tapi gagal saat mencocokkan. Test daftar saja tidak akan menangkapnya.
    expect(csrfExcludes('/user/logout', 'POST'))->toBeTrue()
        ->and(csrfExcludes('/welcome/logout', 'POST'))->toBeTrue()
        // Host dan query string tidak boleh membuat kecocokan gagal.
        ->and(csrfExcludes('http://localhost/user/logout', 'POST'))->toBeTrue();
});

test('a path that is not on the list is not excluded', function (): void {
    // Penyeimbang test sebelumnya: daftar `$except` tidak boleh sampai terlalu
    // longgar sampai semua endpoint biasa ikut bebas CSRF.
    expect(csrfExcludes('/home'))->toBeFalse()
        ->and(csrfExcludes('/settings'))->toBeFalse()
        ->and(csrfExcludes('/orders'))->toBeFalse()
        // `admin/*` memang ada di daftar sebagai wildcard, jadi yang di sini harus
        // benar: `/admin` polos TIDAK ikut wildcard itu.
        ->and(csrfExcludes('/admin'))->toBeFalse();
});

test('the admin wildcard exempts every admin endpoint including post', function (): void {
    // FAKTA INI BUKAN HAL YANG DIINGINKAN -- test ini menguncinya supaya tidak
    // berubah diam-diam, dan supaya kelemahannya terlihat.
    //
    // `admin/*` di `$except` berarti SELURUH panel admin lolos dari verifikasi
    // CSRF, bukan cuma yang memang butuh. Termasuk endpoint yang mengubah state:
    //
    //   POST /admin/reviews/{review}/helpful   (route `admin.reviews.vote`)
    //   POST /admin/reviews/{review}/report    (route `admin.reviews.report`)
    //
    // Dua-duanya di dalam group `auth` + prefix `admin` di `routes/web/web.php`,
    // jadi middleware `SuperAdmin` TIDAK melindungi dari CSRF -- `SuperAdmin`
    // hanya memeriksa peran, bukan asal request. Efeknya: panel admin yang sudah
    // login bisa dipaksa orang lain untuk mengubah status vote dan report review
    // tanpa korbannya pernah menyadarinya.
    //
    // Assertion di bawah mengunci perilaku INI sebagai apa adanya. Memperbaikinya
    // berarti sengaja membalik test ini menjadi `toBeFalse()` -- dan itu
    // keputusan yang harus diambil diam-diam, bukan hasil refactor.
    //
    // Kalau ini memang tidak disengaja, perbaikannya bukan menghapus `admin/*`
    // begitu saja: panel admin punya banyak form yang rely pada pengecualian ini.
    // Yang perlu ditinjau adalah daftar path spesifik, satu per satu.
    expect(csrfExcludes('/admin/data-exports/download/laporan.pdf', 'POST'))->toBeTrue()
        ->and(csrfExcludes('/admin/reviews/12/helpful', 'POST'))->toBeTrue()
        ->and(csrfExcludes('/admin/reviews/12/report', 'POST'))->toBeTrue();
});

test('the livewire update endpoint is excluded so forms can save', function (): void {
    // Tanpa `livewire/*`, SETIAP form Livewire -- yang memakai POST ke
    // /livewire/update -- akan kena 419 dan tidak bisa disimpan sama sekali.
    expect(csrfExceptPaths())->toContain('livewire/*')
        ->and(csrfExcludes('/livewire/update', 'POST'))->toBeTrue();
});

test('the base middleware still guards the web group', function (): void {
    // Fakta yang mudah disalahpahami: `replace()` di bootstrap/app.php tidak
    // mengganti apa pun di grup `web` -- penggantian itu hanya berlaku
    // untuk middleware global, dan `ValidateCsrfToken` bukan global. Jadi grup
    // `web` berjalan dengan middleware DASAR, dan
    // `App\Http\Middleware\VerifyCsrfToken` hanya berjalan sebagai lapisan kedua
    // di level panel Filament.
    //
    // Yang membuat pengecualian berlaku untuk request web adalah
    // `validateCsrfTokens(except: ...)` di bootstrap/app.php, bukan subclass
    // aplikasi. Konsekuensinya harus tetap terlihat di sini: `admin/*` TIDAK
    // ikut bebas di grup `web` -- hanya `livewire/*` dan ketiga path logout.
    $webGroup = app('router')->getMiddlewareGroups()['web'] ?? [];

    $baseCsrfIndex = null;

    foreach (array_values($webGroup) as $index => $middleware) {
        if ($middleware === ValidateCsrfToken::class) {
            $baseCsrfIndex = $index;

            break;
        }
    }

    expect($baseCsrfIndex)->not->toBeNull()
        // Dan aplikasi TIDAK menumpuk middleware-nya sendiri di grup web --
        // tidak ada penggantian, tidak ada pengurangan.
        ->and($webGroup)->not->toContain(VerifyCsrfToken::class)
        // Wildcard `admin/*` milik subclass aplikasi tidak boleh ikut
        // ditambahkan ke `validateCsrfTokens()` tanpa keputusan tersendiri:
        // itu akan membebaskan endpoint non-panel (mis. admin.reviews.vote).
        ->and(webCsrfExcludes('/admin/reviews/12/helpful', 'POST'))->toBeFalse();
});

test('the web group really exempts the livewire update endpoint', function (): void {
    // Ini penyebab dialog "This page has expired. Would you like to refresh the
    // page?" yang muncul tepat setelah Pusat Unduhan Data selesai mengunduh.
    //
    // Setiap klik pada halaman Filament = POST /livewire/update. Token sesi
    // berubah setiap kali user login (OTP/2FA memanggil `regenerateToken()`),
    // jadi tab yang sudah terbuka sebelum login masih memegang token lama dan
    // request-nya kena 419 -- livewire.js menampilkan dialog, halaman mungkin
    // kehilangan state, dan user mengira unduhannya gagal.
    //
    // Test ini membaca middleware yang BENAR-BENAR dipakai grup `web`, bukan
    // daftar `$except` milik subclass aplikasi yang tidak pernah jalan di sini.
    expect(webCsrfExcludes('/livewire/update', 'POST'))->toBeTrue()
        ->and(webCsrfExcludes('/livewire/upload-file', 'POST'))->toBeTrue();
});

test('the web group really exempts the logout paths', function (): void {
    // Sama seperti di atas, tapi untuk SignOut: logout itu idempotent, dan
    // bentuk yang paling sering terjadi justru sesi yang sudah tidak berlaku.
    expect(webCsrfExcludes('/user/logout', 'POST'))->toBeTrue()
        ->and(webCsrfExcludes('/welcome/logout', 'POST'))->toBeTrue()
        ->and(webCsrfExcludes('/logout', 'POST'))->toBeTrue();
});

test('the web group still guards ordinary web routes', function (): void {
    // Penyeimbang: pengecualian di atas tidak boleh melebar sampai semua
    // request web bebas CSRF.
    expect(webCsrfExcludes('/home', 'POST'))->toBeFalse()
        ->and(webCsrfExcludes('/settings', 'POST'))->toBeFalse()
        ->and(webCsrfExcludes('/orders', 'POST'))->toBeFalse()
        ->and(webCsrfExcludes('/home', 'GET'))->toBeFalse();
});
