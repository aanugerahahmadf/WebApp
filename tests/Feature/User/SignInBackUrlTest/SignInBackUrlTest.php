<?php

/**
 * `SignIn::getBackUrl()` -- tujuan tombol "kembali" di halaman login.
 *
 * Method ini 46 baris dengan dua tingkat prioritas dan enam kondisi penolakan,
 * dan tidak pernah diuji sebelum file ini dibuat. Test yang sebelumnya bernama
 * `SignInBackButtonTest` -- yang namanya menjanjikan hal ini -- sebenarnya
 * tidak menyentuh `getBackUrl()` sama sekali; yang diuji di sana adalah
 * breadcrumb (sekarang `tests/Feature/User/SignInBreadcrumbsTest`).
 *
 * Aturan yang dikunci di sini, sesuai `SignIn::getBackUrl()`:
 *
 *   1. Sumber kandidat berurutan: `url()->previous()` (sinyal terbaru, dari
 *      header `referer`) lalu `session('url.intended')` (disimpan GuestAddToCart
 *      saat tamu menekan aksi yang butuh login).
 *   2. Kandidat yang tidak bisa dipakai dibuang: endpoint Livewire, halaman auth
 *      (`/user/signin`), halaman pendaftaran yang dinonaktifkan (`/user/signup`
 *      dan `/user/register`), alur reset kata sandi, OTP, dan panel admin. Untuk
 *      semua itu tombol kembali akan mengulang halaman yang sedang dibuka atau
 *      melempar tamu ke panel lain.
 *   3. Prioritas pertama: katalog paket & produk Welcome (index dan detail).
 *   4. Prioritas kedua: halaman Welcome lain yang valid.
 *   5. Fallback terakhir: Welcome Home.
 *
 * PENTING: `getBackUrl()` belum punya konsumen view -- tidak ada blade yang
 * memanggilnya, jadi tujuan yang dihitung method ini belum muncul sebagai
 * tautan di halaman login (lihat test terakhir di file ini). Method-nya tetap
 * diuji karena logikanya intricate dan jadi bahan hidup begitu disambungkan.
 *
 * `getBackUrl()` membaca request dari container (`url()->previous()`), jadi test
 * menyuntikkan request palsu lewat `app()->instance('request', ...)` alih-alih
 * melakukan HTTP, sehingga yang diuji murni logika pemilihan URL.
 */

use App\Filament\User\Auth\SignIn\SignIn;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Root di sini HARUS sama dengan root request palsu yang disuntikkan di
    // `backUrlFor()` (`Request::create()` memakai host `localhost`), karena
    // `route()` di dalam `getBackUrl()` diselesaikan terhadap root request
    // tersebut. `route()` yang dipanggil di luar request memakai `APP_URL`,
    // yang di .env.testing bisa `http://127.0.0.1:8000` -- membandingkan host
    // akan membuat test ini gagal karenaenvironment, bukan karena logikanya.
    $this->welcomeHome = 'http://localhost'.route('filament.welcome.pages.home', absolute: false);
});

afterEach(function (): void {
    Filament::setCurrentPanel(null);
});

/**
 * Hitung `getBackUrl()` pada kondisi tertentu.
 *
 * `referer` disimulasikan lewat header pada request yang disuntikkan ke
 * container; `intended` lewat session, karena itulah dua sumber yang dibaca
 * method aslinya.
 */
function backUrlFor(?string $referer = null, ?string $intended = null): string
{
    $request = Request::create('/user/signin', 'GET');

    if ($referer !== null) {
        $request->headers->set('referer', $referer);
    }

    app()->instance('request', $request);

    session()->forget('url.intended');

    if ($intended !== null) {
        session(['url.intended' => $intended]);
    }

    Filament::setCurrentPanel(Filament::getPanel('user'));

    return (new \ReflectionClass(SignIn::class))->newInstanceWithoutConstructor()->getBackUrl();
}

test('the back target falls back to the storefront home without any signal', function (): void {
    // Tamu yang mengetik URL-nya secara manual tidak punya referer maupun
    // intended; tombol kembali tetap harus punya tujuan yang masuk akal, dan
    // satu-satunya yang selalu benar adalah Welcome Home.
    expect(backUrlFor())->toBe($this->welcomeHome)
        ->and(backUrlFor(null, ''))->toBe($this->welcomeHome);
});

test('the back target prefers the page the guest actually came from', function (): void {
    // Detail paket: sinyal terbaru langsung dikembalikan tanpa menunggu
    // kandidat kedua -- itulah prioritas putaran pertama.
    expect(backUrlFor('http://localhost/welcome/flowerdecorationspackagecatalog/5'))
        ->toBe('http://localhost/welcome/flowerdecorationspackagecatalog/5')
        ->and(backUrlFor('http://localhost/welcome/flowerdecorationspackagecatalog/5'))
        ->not->toBe($this->welcomeHome);
});

test('the back target accepts both catalog indexes and details', function (string $referer): void {
    expect(backUrlFor($referer))->toBe($referer);
})->with([
    'package index' => ['http://localhost/welcome/flowerdecorationspackagecatalog'],
    'package detail' => ['http://localhost/welcome/flowerdecorationspackagecatalog/5'],
    'product index' => ['http://localhost/welcome/flowerdecorationscatalog'],
    'product detail' => ['http://localhost/welcome/flowerdecorationscatalog/9'],
]);

test('url intended is used when the referer is not a catalog page', function (): void {
    // GuestAddToCart menaruh detail paket yang baru dilihat ke `url.intended`;
    // kalau tombol kembali mengabaikannya, pengguna selalu mendarat di beranda.
    expect(backUrlFor(null, 'http://localhost/welcome/flowerdecorationspackagecatalog/5'))
        ->toBe('http://localhost/welcome/flowerdecorationspackagecatalog/5')
        ->and(backUrlFor(null, 'http://localhost/welcome/flowerdecorationscatalog/9'))
        ->toBe('http://localhost/welcome/flowerdecorationscatalog/9');
});

test('a catalog referer outranks an intended url, and another welcome page does not', function (): void {
    // Dua kandidat: katalog (putaran pertama menang) dan halaman welcome lain
    // (hanya putaran kedua). Urutan ini yang menjaga tombol kembali tidak
    // melompat ke dashboard storefront ketika pengguna sedang browse produk.
    expect(backUrlFor(
        'http://localhost/welcome/flowerdecorationscatalog/9',
        'http://localhost/welcome/messages',
    ))->toBe('http://localhost/welcome/flowerdecorationscatalog/9');

    // Tanpa kandidat katalog, halaman welcome lain tetap dipakai -- lebih baik
    // daripada melempar pengguna ke beranda.
    expect(backUrlFor('http://localhost/welcome/messages', null))
        ->toBe('http://localhost/welcome/messages')
        ->and(backUrlFor(null, 'http://localhost/welcome/messages'))
        ->toBe('http://localhost/welcome/messages');
});

test('unusable referers are skipped in favour of the intended url', function (string $referer): void {
    // Semua URL di bawah akan membuat tombol kembali mengulang halaman yang
    // sedang dibuka, melempar tamu ke panel admin, atau kembali ke halaman auth
    // -- semuanya tidak bisa dipakai sebagai tujuan.
    expect(backUrlFor($referer, 'http://localhost/welcome/flowerdecorationscatalog/3'))
        ->toBe('http://localhost/welcome/flowerdecorationscatalog/3');
})->with([
    'livewire endpoint' => ['http://localhost/livewire/update'],
    'the sign in page itself' => ['http://localhost/user/signin'],
    'the retired sign up url' => ['http://localhost/user/signup'],
    'the filament default register url' => ['http://localhost/user/register'],
    'the password reset request page' => ['http://localhost/user/password-reset/request'],
    'the otp verification page' => ['http://localhost/user/verify-otp'],
    'the admin panel' => ['http://localhost/admin/orders'],
]);

test('unusable candidates with nothing else to offer fall back to the storefront home', function (string $referer, ?string $intended): void {
    expect(backUrlFor($referer, $intended))->toBe($this->welcomeHome);
})->with([
    'livewire endpoint' => ['http://localhost/livewire/update', null],
    'the auth landing page' => ['http://localhost/user/auth', null],
    'the sign in page' => ['http://localhost/user/signin', null],
    'password reset request' => ['http://localhost/user/password-reset/request', null],
    'otp verification' => ['http://localhost/user/verify-otp', null],
    'admin panel' => ['http://localhost/admin', null],
    'outside the welcome panel' => ['http://localhost/', null],
    'both candidates unusable' => [
        'http://localhost/user/signin',
        'http://localhost/livewire/update',
    ],
]);

test('navigasi kembali dari halaman sign in adalah breadcrumb, bukan tombol back tersembunyi', function (): void {
    // `getBackUrl()` menghitung tujuan yang tepat, tapi tidak ada view yang
    // merender hasilnya: satu-satunya jalan kembali dari halaman ini adalah
    // breadcrumb ke `/user/auth`. Dipin supaya jelas bahwa referer tidak
    // pernah muncul sebagai tautan di halaman -- kalau suatu saat tombol back
    // benar-benar disambungkan, test ini harus ikut berubah, bukan diam saja.
    $html = $this
        ->withHeaders(['referer' => 'http://localhost/welcome/flowerdecorationscatalog/9'])
        ->get(route('filament.user.auth.login'))
        ->assertOk()
        ->getContent();

    // Breadcrumb tetap ada dan menunjuk ke halaman auth...
    expect($html)->toContain('<nav aria-label="breadcrumb"')
        ->toContain(\App\Filament\User\Auth\Auth\Auth::getUrl());

    // ...tetapi tidak ada tautan ke halaman asal yang dihitung getBackUrl().
    expect($html)->not->toContain('/welcome/flowerdecorationscatalog/9');
});