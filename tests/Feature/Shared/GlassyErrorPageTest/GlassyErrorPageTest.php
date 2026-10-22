<?php

use App\Support\ErrorPage\ErrorPage;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Route;

/*
 * Halaman error "glass" + animasi.
 *
 * Yang dikunci di sini:
 *
 *   1. Halaman error benar-benar memakai KACA (backdrop-filter), bukan
 *      halaman polos bawaan Laravel.
 *   2. View yang dipakai mengikuti PANEL: /admin/* memakai view admin
 *      (tombol kembali ke dashboard, warna brand indigo), sisanya view
 *      storefront (emas, kembali ke /home).
 *   3. Status di luar daftar pun tetap glassy (lewat `errors::minimal`).
 *   4. Halaman error TIDAK menyentuh database -- harus tetap tampil saat
 *      database-nya yang jadi penyebab error.
 *
 * Kenapa test ini perlu ada: sebelum handler milik aplikasi di-bind di
 * `bootstrap/app.php`, SEMUA folder `errors` di ketiga panel aplikasi ini
 * adalah kode mati -- yang tampil halaman polos bawaan Laravel. Tidak ada
 * error, tidak ada stack trace; view-nya cuma tidak pernah dipakai.
 *
 * CATATAN SOAL BAHASA:
 *
 *   Symfony menyuntik header `Accept-Language: en-us,en;q=0.5` ke setiap
 *   request test, jadi locale default test adalah `en`. Test yang memeriksa
 *   teks Bahasa Indonesia mengirim header `id` secara eksplisit -- dengan
 *   begitu yang diuji adalah terjemahannya, bukan tebakan tentang locale
 *   bawaan browser.
 */
beforeEach(function (): void {
    Route::get('/__test/glass/{status}', fn (int $status) => abort($status))
        ->middleware('web')
        ->where('status', '[0-9]{3}');

    Route::get('/admin/__test/glass/{status}', fn (int $status) => abort($status))
        ->middleware('web')
        ->where('status', '[0-9]{3}');
});

it('merender halaman 404 storefront sebagai kartu kaca', function (): void {
    $response = $this->get('/__test/glass/404');

    $response->assertStatus(404);

    $html = $response->getContent();

    // Kaca: blur + saturate, DAN prefiks -webkit- (Android WebView + iOS
    // Safari tidak lagi mendukung backdrop-filter tanpa prefiks itu).
    expect($html)->toContain('backdrop-filter');
    expect($html)->toContain('-webkit-backdrop-filter');

    // Struktur halaman.
    expect($html)->toContain('gx-card');
    expect($html)->toContain('gx-code');
    expect($html)->toContain('>404<');

    // Animasi.
    expect($html)->toContain('@keyframes');
    expect($html)->toContain('prefers-reduced-motion');
});

it('tidak menampilkan pesan teknis dari exception', function (): void {
    $html = $this->get('/__test/glass/404')->getContent();

    // "The route ... could not be found." adalah pesan internal Symfony.
    // Menampilkannya membocorkan struktur routing ke pengunjung.
    expect($html)->not->toContain('could not be found');
    expect($html)->not->toContain('__test/glass/404');
});

it('tidak menampilkan ikon status maupun kalimat penjelasan', function (): void {
    $html = $this->get('/__test/glass/404')->getContent();

    // Halaman error sengaja ringkas: kode, judul, tombol.
    expect($html)->not->toContain('gx-badge');
    expect($html)->not->toContain('gx-description');
    expect($html)->not->toContain('gx-detail');
});

it('memakai terjemahan Indonesia untuk judul halaman error', function (): void {
    $html = $this->get('/__test/glass/404', ['Accept-Language' => 'id'])->getContent();

    // id.json: "Not Found" -> "Tidak ditemukan"
    expect($html)->toContain('Tidak ditemukan');
    expect($html)->toContain('lang="id"');
});

it('halaman error di /admin memakai view dan warna panel admin', function (): void {
    $response = $this->get('/admin/__test/glass/404');

    $response->assertStatus(404);

    $html = $response->getContent();

    // Brand admin = indigo (bukan emas storefront).
    expect($html)->toContain('#4f46e5');
    expect($html)->toContain('#818cf8');
});

it('halaman error di storefront memakai view dan warna panel storefront', function (): void {
    $html = $this->get('/__test/glass/404')->getContent();

    // Brand storefront = emas.
    expect($html)->toContain('#ca8a04');
    expect($html)->toContain('#facc15');
});

it('halaman error hanya punya satu tombol', function (): void {
    // "Kembali ke Beranda" SENGAJA dihapus: halaman error hampir selalu
    // muncul dari tautan salah atau aksi gagal, dan tombol itu membuang
    // konteksnya (katalog, keranjang, halaman pesanan).
    $html = $this->get('/__test/glass/404', ['Accept-Language' => 'id'])->getContent();

    expect(substr_count($html, 'class="gx-btn '))->toBe(1);
    expect($html)->not->toContain('Kembali ke Beranda');
    expect($html)->not->toContain('Kembali ke Dashboard');
});

it('halaman error tidak menampilkan nama brand di kaki halaman', function (): void {
    $html = $this->get('/__test/glass/404')->getContent();

    expect($html)->not->toContain('gx-footer');
    expect($html)->not->toContain((string) config('app-font.brand_name', 'Dekorasi Bunga Pernikahan'));
});

it('halaman 401 menawarkan pintu masuk, bukan tombol muat ulang', function (): void {
    $html = $this->get('/__test/glass/401', ['Accept-Language' => 'id'])->getContent();

    expect($html)->toContain('href="/signin"');
    expect($html)->toContain('Masuk ke Akun');
});

it('halaman 401 di panel admin mengarah ke pintu sign in admin', function (): void {
    $html = $this->get('/admin/__test/glass/401', ['Accept-Language' => 'id'])->getContent();

    expect($html)->toContain('href="/admin/signin"');
    expect($html)->toContain('Masuk sebagai Admin');
});

it('halaman 503 menawarkan muat ulang, bukan navigasi', function (): void {
    $html = $this->get('/__test/glass/503', ['Accept-Language' => 'id'])->getContent();

    expect($html)->toContain('data-gx-reload');
    expect($html)->toContain('Coba Lagi');
});

it('setiap status yang punya view sendiri merender kode yang benar', function (int $status): void {
    $response = $this->get("/__test/glass/{$status}");

    $response->assertStatus($status);
    expect($response->getContent())->toContain(">{$status}<");
})->with([401, 402, 403, 404, 429, 500, 503]);

it('halaman 419 dirender sebagai kaca ketika viewnya dipanggil langsung', function (): void {
    // 419 TIDAK boleh diuji lewat request HTTP: `bootstrap/app.php`
    // mencegat 419 untuk request halaman biasa dan me-redirect ke URL yang
    // sama (memuat ulang halaman secara diam-diam), jadi responsnya 302 --
    // bukan halaman error.
    //
    // View 419 tetap hidup karena request Livewire 419 dibiarkan apa adanya
    // oleh handler, dan karena status lain bisa memanggilnya. Yang diuji
    // di sini: view-nya benar-benar merender kartu kaca.
    $html = view('Welcome.errors.419.419', [
        'exception' => new \Symfony\Component\HttpKernel\Exception\HttpException(419),
    ])->render();

    expect($html)->toContain('>419<');
    expect($html)->toContain('gx-card');
    expect($html)->toContain('data-gx-reload');
});

it('status di luar daftar pun tetap merender halaman kaca', function (): void {
    // 405 tidak punya view sendiri di Welcome/User/Admin.
    $response = $this->get('/__test/glass/405');

    $response->assertStatus(405);
    expect($response->getContent())->toContain('gx-card');
});

it('handler milik aplikasi yang dipakai, bukan bawaan Laravel', function (): void {
    $handler = app(ExceptionHandler::class);

    expect($handler)->toBeInstanceOf(\App\Exceptions\Handler\Handler::class);
});

it('kandidat view menempatkan panel aktif di urutan pertama', function (): void {
    app()->instance('request', \Illuminate\Http\Request::create('/admin/apa-saja', 'GET'));

    expect(ErrorPage::panel())->toBe(ErrorPage::PANEL_ADMIN);
    expect(ErrorPage::viewNamespace())->toBe('Admin');
    expect(ErrorPage::viewCandidates(404)[0])->toBe('Admin.errors.404.404');

    app()->instance('request', \Illuminate\Http\Request::create('/katalog', 'GET'));

    expect(ErrorPage::panel())->toBe(ErrorPage::PANEL_STOREFRONT);
    expect(ErrorPage::viewNamespace())->toBe('Welcome');
    expect(ErrorPage::viewCandidates(404)[0])->toBe('Welcome.errors.404.404');
});

it('warna aksen memakai sintaks rgb() modern yang valid', function (): void {
    $html = $this->get('/__test/glass/404')->getContent();

    // Triplet RGB dipisah SPASI, bukan koma.
    //
    // `rgb(14, 165, 233 / 0.5)` TIDAK VALID: sintaks rgb() yang mendukung
    // alpha dengan `/` hanya menerima komponen yang dipisah spasi. CSS
    // yang tidak valid dibuang TANPA PESAN -- jadi orb, aurora, gradasi
    // latar, dan glow tombol hilang total sementara halaman tetap tampil.
    // Gejalanya "warnanya tidak ada", bukan "error".
    expect($html)->toContain('--gx-accent-rgb-1: 14 165 233');

    // Tidak boleh ada triplet yang dipisah koma.
    expect($html)->not->toMatch('/--gx-[a-z0-9-]+-rgb-\d: \d+,/');
});

/*
 * BAHASA.
 *
 * Test-test di sini memakai URL yang TIDAK ADA. Itu bukan kebetulan --
 * justru inti dari testnya:
 *
 *   404 untuk URL yang tidak dikenal dilempar oleh ROUTER, SEBELUM
 *   middleware route (termasuk grup `web`) sempat jalan. Jadi middleware
 *   `SetLocale` TIDAK PERNAH dipanggil, dan tanpa langkah tambahan di
 *   exception handler, halaman error selalu memakai default aplikasi --
 *   bukan bahasa yang diminta browser.
 *
 *   Gejalanya: orang berbahasa Inggris membuka tautan yang salah dan
 *   mendapat "Tidak ditemukan" dalam Bahasa Indonesia.
 */
it('halaman 404 untuk URL yang tidak dikenal mengikuti bahasa browser', function (string $header, string $expected, string $unexpected): void {
    $html = $this->get('/__halaman-yang-tidak-ada-xyz', ['Accept-Language' => $header])->getContent();

    expect($html)->toContain($expected);
    expect($html)->not->toContain($unexpected);
})->with([
    'inggris' => ['en-US,en;q=0.9', 'Not Found', 'Tidak ditemukan'],
    'indonesia' => ['id-ID,id;q=0.9', 'Tidak ditemukan', 'Not Found'],
]);

it('halaman error menghormati parameter locale', function (): void {
    // Parameter locale menang atas Accept-Language, sama seperti di
    // `SetLocale`.
    $html = $this->get('/__halaman-yang-tidak-ada-xyz?locale=id', ['Accept-Language' => 'en-US,en;q=0.9'])->getContent();

    expect($html)->toContain('Tidak ditemukan');
});

it('halaman error memakai bahasa yang tersimpan di session', function (): void {
    // Route yang DIJEMPUT (bukan URL yang tidak dikenal): di situ middleware
    // `web` sempat jalan, jadi session-nya ada dan `session('locale')` bisa
    // dibaca.
    //
    // Untuk URL yang TIDAK ADA, middleware route tidak pernah jalan --
    // session pun tidak dimulai. One-satunya sumber bahasa di sana adalah
    // header `Accept-Language`. Itu konsekuensi yang wajar: membaca session
    // berarti memulainya, dan itu berarti query ke database -- yang tidak
    // boleh terjadi di halaman error.
    $this->withSession(['locale' => 'en']);

    $html = $this->get('/__test/glass/404', ['Accept-Language' => 'id-ID,id;q=0.9'])->getContent();

    expect($html)->toContain('Not Found');
});

it('status di luar daftar menampilkan kode statusnya sendiri', function (): void {
    // 405 tidak punya view sendiri, jadi handler jatuh ke `errors::minimal`
    // yang tidak punya section 'code'. Kalau nilainya dipaksa 404, halaman
    // 405 akan menampilkan angka 404.
    $html = $this->get('/__test/glass/405')->getContent();

    expect($html)->toContain('>405<');
    expect($html)->not->toContain('>404<');
});

it('halaman error tidak melakukan query ke database', function (): void {
    // Route TANPA middleware group `web`.
    //
    // Alasannya: dengan `web`, session ikut jalan, dan ketika driver session
    //-nya `database`, middleware itu menulis baris ke tabel `sessions` --
    // itu query MIDDLEWARE, bukan query halaman error. Asersi "nol query"
    // jadi tidak bisaBEDA antara "halaman error aman" dan "session aktif".
    //
    // Tanpa `web` tidak ada session, jadi satu-satunya query yang mungkin
    // muncul REALLY berasal dari halaman error. Dan halaman error boleh
    // muncul justru saat database tidak terjangkau.
    Route::get('/__test/glass-nodb/{status}', fn (int $status) => abort($status))
        ->where('status', '[0-9]{3}');

    \Illuminate\Support\Facades\DB::enableQueryLog();

    $this->get('/__test/glass-nodb/404')->assertStatus(404);

    $queries = \Illuminate\Support\Facades\DB::getQueryLog();

    \Illuminate\Support\Facades\DB::disableQueryLog();

    expect($queries)->toBe([]);
});
