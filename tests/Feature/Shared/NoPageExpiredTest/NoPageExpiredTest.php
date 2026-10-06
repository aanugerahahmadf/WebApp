<?php

/**
 * 419 "Page Expired" harus tidak pernah terlihat user.
 *
 * Di app ini 419 BUKAN masalah sesi. AppServiceProvider sudah memaksa
 * session.lifetime 525600 (1 tahun), expire_on_close false, dan
 * session.lottery [0, 100] yang membuat garbage collection tidak pernah
 * jalan -- jadi sesi praktis tidak pernah kedaluwarsa.
 *
 * 419 di sini datang dari Livewire, semuanya berstatus HTTP 419:
 *
 *   1. LivewireReleaseTokenMismatchException
 *      ReleaseToken::verify() melempar ini kalau class component di snapshot
 *      tidak bisa di-resolve, ATAU release token tidak cocok. Dua kasus itu
 *      memakai pesan yang sama, jadi tidak bisa dibedakan dari page error.
 *
 *   2. HandleRequests::update() memanggil abort(419) kalau hydration
 *      melempar TypeError. Karena APP_DEBUG=false, error aslinya disembunyikan.
 *
 *   3. CorruptComponentPayloadException -> response('', 419) saat debug mati.
 *
 * Handler di bootstrap/app.php mengubah 419 menjadi redirect GET diam-diam ke
 * halaman yang sama, jadi user tidak pernah melihat error.
 *
 * CATATAN SOAL CAKUPAN TEST
 *
 * Test di sini HANYA memakai request non-Livewire. Request Livewire sengaja
 * dibiarkan 419 oleh handler -- kalau di-redirect, browser transparan
 * mengikuti Location dengan GET ke /livewire/update yang hanya menerima POST,
 * hasilnya 405. Tapi menguji kasus itu lewat HTTP berarti error page
 * Laravel/Filament ikut dirender, dan itu menghabiskan 512 MB memory sampai
 * fatal. Jadi kasus Livewire tidak diuji lewat HTTP; penjaganya adalah
 * test 'App\Livewire\ComponentResolvableTest' yang membuktikan nama-nama
 * component benar-benar bisa di-resolve.
 */

use Illuminate\Support\Facades\Route;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    Route::middleware('web')->get('/__test/419-abort', fn () => abort(419));
    Route::middleware('web')->get('/__test/419-token-mismatch', function () {
        throw new TokenMismatchException('CSRF token mismatch.');
    });
    Route::middleware('web')->get('/__test/419-http-exception', function () {
        throw new HttpException(419, 'Page Expired');
    });
    Route::middleware('web')->get('/__test/419-query', fn () => abort(419));
});

it('mengubah 419 dari abort menjadi redirect, bukan halaman error', function (): void {
    $this->get('/__test/419-abort')
        ->assertStatus(302)
        ->assertRedirect('/__test/419-abort');
});

it('mengubah TokenMismatchException menjadi redirect', function (): void {
    // Penyebab paling umum: cookie sesi tidak cocok dengan token.
    $this->get('/__test/419-token-mismatch')
        ->assertStatus(302)
        ->assertRedirect('/__test/419-token-mismatch');
});

it('mengubah HttpException 419 menjadi redirect', function (): void {
    // Jalur yang dipakai LivewireReleaseTokenMismatchException.
    $this->get('/__test/419-http-exception')
        ->assertStatus(302)
        ->assertRedirect('/__test/419-http-exception');
});

it('tidak pernah mengirim status 419 ke user', function (string $url): void {
    expect($this->get($url)->getStatusCode())->toBe(302);
})->with([
    'abort' => '/__test/419-abort',
    'token mismatch' => '/__test/419-token-mismatch',
    'http exception' => '/__test/419-http-exception',
]);

it('membuang query string agar redirect tidak ikut membawa parameter rusak', function (): void {
    // URL berquery bisa jadi penyebab payload Livewire korup. Redirect ke
    // root lebih aman daripada memutar ulang URL yang sama.
    $response = $this->get('/__test/419-query?broken=1');

    $response->assertStatus(302);
    expect($response->headers->get('Location'))->toBe(url('/'));
});

it('kebetulan session tidak pernah kedaluwarsa, jadi 419 bukan masalah umur', function (): void {
    // Penjaga: kalau ada yang "memperbaiki" 419 dengan mengubah
    // SESSION_LIFETIME, test ini membuktikan bahwa itu bukan fix yang benar.
    expect(config('session.lifetime'))->toBeGreaterThanOrEqual(525600)
        ->and(config('session.expire_on_close'))->toBeFalse();
});
