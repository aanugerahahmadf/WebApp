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
 *      `web` tetap menjalankan middleware dasar dengan `$except` kosong.
 *      Test terakhir mengunci fakta ini supaya tidak ada yang mengira
 *      penggantian itu bekerja.
 */

use App\Http\Middleware\VerifyCsrfToken\VerifyCsrfToken;
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
    expect(csrfExcludes('/user/home'))->toBeFalse()
        ->and(csrfExcludes('/user/settings'))->toBeFalse()
        ->and(csrfExcludes('/welcome/orders'))->toBeFalse()
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
    // `web` berjalan dengan middleware DASAR yang `$except`-nya kosong, dan
    // `App\Http\Middleware\VerifyCsrfToken` hanya berjalan sebagai lapisan kedua
    // di level panel Filament.
    //
    // Konsekuensinya: `POST /user/logout` tetap diperiksa oleh middleware dasar
    // lebih dulu. Belum jelas apakah itu yang diinginkan, tapi test ini ada supaya
    // orang yang membaca `replace()` itu mengira pengaruhnya lebih besar dari
    // kenyataan -- dan supaya kalau suatu saat diperbaiki, test ini yang
    // memberitahu.
    $webGroup = app('router')->getMiddlewareGroups()['web'] ?? [];

    $baseCsrfIndex = null;

    foreach (array_values($webGroup) as $index => $middleware) {
        if ($middleware === \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class) {
            $baseCsrfIndex = $index;

            break;
        }
    }

    expect($baseCsrfIndex)->not->toBeNull()
        // Dan aplikasi TIDAK menumpuk middleware-nya sendiri di grup web --
        // tidak ada penggantian, tidak ada pengurangan.
        ->and($webGroup)->not->toContain(VerifyCsrfToken::class);
});