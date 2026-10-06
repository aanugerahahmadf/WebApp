<?php

/**
 * `SetLocale` middleware -- satu-satunya tempat locale aplikasi ditentukan.
 *
 * Setiap label di seluruh aplikasi melewati middleware ini, jadi order
 * penentuannya adalah kontrak, bukan detail internal. Urutan yang dikunci di
 * `App\Http\Middleware\SetLocale`:
 *
 *   1. query `?locale=`, lalu `?lang=` -- jalur yang dipakai language switcher
 *      dan test-test label di suite ini,
 *   2. `session('locale')` -- hasil request sebelumnya, jadi locale "mengalir"
 *      antar halaman tanpa perlu switcher dipanggil lagi,
 *   3. header `Accept-Language` -- jalur yang dipakai Flutter dan browser,
 *      dengan normalisasi `id-ID` -> `id`,
 *   4. bahasa yang tersimpan di database user (hanya bila 1-3 kosong),
 *   5. fallback: shell mobile native dipaksa `id`, selain itu bahasa pertama
 *      dari `config/filament-language-switcher.php`.
 *
 * Yang juga dijaga: locale yang dipakai request HARUS ditulis balik ke session
 * dan ke tabel `user_languages`, supaya kunjungan berikutnya (tanpa query
 * string) memakai bahasa yang sama.
 *
 * Test lama hanya mengujinya secara tidak sengaja -- `UserAuthLandingPageTest` dan
 * `WelcomeStorefrontGuestAccessTest` menguji label dengan `?locale=id|en`, jadi
 * cabang 1 teruji, tetapi cabang 2-5 tidak pernah disentuh. Tidak ada satu pun
 * test untuk middleware ini secara langsung, padahal ia yang menentukan seluruh
 * teks Bahasa Inggris/Indonesia di aplikasi.
 *
 * CATATAN GAP YANG SENGAJA TIDAK DIUJI: `?locale=` diambil tanpa validasi
 * terhadap daftar locale yang didukung, jadi `?locale=xx` membuat aplikasi
 * berjalan pada locale `xx` (seluruh terjemahan jatuh ke string sumber).
 * Perilaku itu belum diubah di produksi; kalau nanti diperbaiki, tambahkan
 * test-nya di sini bersama perbaikan tersebut.
 *
 * Dua jejak yang mudah membuat orang tersesat di area ini, keduanya sudah
 * dikunci oleh test di bawah:
 *
 *   1. Preferensi bahasa user TIDAK ada di kolom `users.lang` -- tidak ada
 *      kolom itu di skema. Ia disimpan di tabel `user_languages` lewat relasi
 *      morphOne (`InteractsWithLanguages::lang()`). `User::factory()->create(
 *      ['lang' => 'id'])` karena itu melempar `Unknown column 'lang'`, bukan
 *      gagal diam-diam.
 *   2. Route probe harus memakai middleware group `web`. Tanpa session,
 *      `$request->hasSession()` false, sehingga cabang baca-dan-tulis session
 *      tidak pernah jalan dan test bisa hijau tanpa menguji apa pun.
 */

use App\Enums\RuntimePlatform\RuntimePlatform;
use App\Http\Middleware\SetLocale\SetLocale;
use App\Models\User\User;
use App\Models\UserLanguage\UserLanguage;
use App\Support\AppPlatform\AppPlatform;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Default suite: platform web, supaya cabang fallback tidak ikut ikutan
    // terpaksa `id`.
    AppPlatform::fake(RuntimePlatform::WebsiteWindows);
});

afterEach(function (): void {
    AppPlatform::fake(RuntimePlatform::WebsiteWindows);
});

/**
 * Locale akhir setelah satu request lewat middleware.
 *
 * Route dummy dipakai supaya middleware benar-benar dieksekusi tanpa bergantung
 * pada panel: yang diuji adalah middleware-nya, bukan halaman.
 *
 * Route probe memakai middleware group `web` sama seperti route produksi, karena
 * dua cabang middleware hanya hidup di sana: membaca `session('locale')` dan
 * menulis balik locale ke session. Tanpa group itu, `$request->hasSession()`
 * false dan kedua cabangnya tidak pernah dieksekusi -- testnya jadi hijau
 * karena tidak menguji apa pun.
 *
 * Header `Accept-Language` dikosongkan secara default karena test client
 * mengirimkannya sendiri (`en-us,en;q=0.5`). Tanpa itu, cabang fallback tidak
 * akan pernah tercapai: header tersebut selalu menang lebih dulu.
 */
function localeAfterRequest(array $query = [], array $headers = [], array $session = []): string
{
    test()->withSession($session);

    $url = '/__locale-probe';

    if ($query !== []) {
        $url .= '?'.http_build_query($query);
    }

    test()
        ->withHeaders(['Accept-Language' => ''])
        ->withHeaders($headers)
        ->get($url);

    return (string) app()->getLocale();
}

beforeEach(function (): void {
    // Daftarkan route probe sekali saja per proses test.
    if (! app('router')->has('locale-probe')) {
        app('router')->get('/__locale-probe', fn () => 'ok')
            ->middleware(['web', SetLocale::class])
            ->name('locale-probe');
    }
});

test('the locale query parameter wins over everything else', function (): void {
    // Prioritas tertinggi: switcher dan test label mengandalkan ini.
    expect(localeAfterRequest(['locale' => 'id'], ['accept-language' => 'en-US,en;q=0.9'], ['locale' => 'en']))
        ->toBe('id');
});

test('the lang query parameter is accepted as an alias for locale', function (): void {
    // Dua nama untuk hal yang sama, karena link lama di frontend memakai
    // `lang` -- menghapusnya akan mematikan tautan yang sudah circulating.
    expect(localeAfterRequest(['lang' => 'id']))->toBe('id');
});

test('the session locale is remembered across requests', function (): void {
    // Kunjungan pertama tanpa query string tidak menghasilkan apa pun; yang
    // penting locale itu DISIMPAN, sehingga request berikutnya (tanpa switcher
    // dipanggil lagi) tetap memakai bahasa yang sama.
    expect(localeAfterRequest(['locale' => 'id']))->toBe('id');

    expect(localeAfterRequest(session: ['locale' => 'id']))->toBe('id')
        ->and(localeAfterRequest([], ['accept-language' => 'en-US']))->toBe('id');
});

test('the accept language header is honoured, including regional variants', function (string $header, string $expected): void {
    // Flutter dan browser mengirim header; `id-ID` harus dikenali sebagai `id`,
    // bukan dianggap locale yang tidak didukung.
    expect(localeAfterRequest(headers: ['accept-language' => $header]))->toBe($expected);
})->with([
    'indonesian' => ['id-ID,id;q=0.9', 'id'],
    'english' => ['en-US,en;q=0.9', 'en'],
    'plain english' => ['en', 'en'],
    'unsupported falls back to the first configured locale' => ['fr-FR,fr;q=0.9', 'id'],
]);

test('the query parameter wins over everything, and the session outranks the header', function (): void {
    // Tiga sumber, tiga urutan. Tanpa ini, test label bisa lulus karena
    // kebetulan cocok.
    //
    // Urutannya mengikuti middleware: query > session > Accept-Language > bahasa
    // di DB user. Header TIDAK mengalahkan session -- kalau iya, orang yang
    // sudah memilih Bahasa Indonesia lewat switcher akan dikejutkan begitu
    // browsernya mengirim `Accept-Language` untuk halaman berikutnya.
    expect(localeAfterRequest(['locale' => 'en'], ['accept-language' => 'id-ID']))->toBe('en')
        ->and(localeAfterRequest(['locale' => 'en'], ['accept-language' => 'id-ID'], ['locale' => 'id']))
        ->toBe('en')
        ->and(localeAfterRequest([], ['accept-language' => 'en-US'], ['locale' => 'id']))->toBe('id');
});

test('a native mobile shell is forced to indonesian', function (): void {
    // Bundle Capacitor dikirim dalam Bahasa Indonesia-first; kalau header
    // browser atau default OS berbahasa lain mengambil alih, app shell akan
    // menampilkan campuran dua bahasa.
    AppPlatform::fake(RuntimePlatform::MobileAppAndroid);

    expect(localeAfterRequest())->toBe('id');
});

test('a native mobile shell still obeys an explicit choice', function (): void {
    // Paksa-`id` hanya berlaku saat 1-3 kosong; pengguna yang sudah memilih
    // bahasa Inggris lewat switcher tidak boleh ditimpa setiap request.
    AppPlatform::fake(RuntimePlatform::MobileAppIos);

    expect(localeAfterRequest(['locale' => 'en']))->toBe('en');
});

test('the chosen locale is written back to the session for the next request', function (): void {
    localeAfterRequest(['locale' => 'en']);

    expect(session('locale'))->toBe('en');
});

/*
 * CATATAN MENGAPA TEST INI DIGANTI DARI `$user::class` KE `getMorphClass()`
 *
 * Nilai aslinya adalah FQCN: `App\Models\User\User`. Tapi `User` memakai trait
 * `LegacyMorphClass`, jadi kunci morph-nya adalah `App\Models\User` -- bukan
 * FQCN. Relasi `InteractsWithLanguages::lang()` mencari dengan `getMorphClass()`,
 * sementara `SetLocale` dulu menulis dengan `get_class()`. Hasilnya kedua nilai
 * itu berbeda dan barisnya tidak pernah ketemu: bahasa yang dipilih pengguna
 * tersimpan, lalu selalu kembali ke default `'en'` pada kunjungan berikutnya.
 *
 * Assertion di file ini ikut mengunci nilai yang salah. Setelah `SetLocale` ditulis
 * ulang memakai `getMorphClass()`, ketiga assertion ini harus ikut menyesuaikan
 * diri -- kalau tidak, testnya akan terlihat benar padahal sedang menguji bug.
 */

test('a signed in visitor has the chosen locale stored on the user record', function (): void {
    // Tanpa write-back, language switcher akan terlihat berhasil lalu hilang
    // lagi di kunjungan berikutnya: user membuka katalog, bahasa di database tidak
    // pernah berubah.
    $user = User::factory()->create();

    actingAs($user, 'web');

    localeAfterRequest(['locale' => 'id']);

    expect(UserLanguage::query()
        ->where('model_id', (string) $user->id)
        ->where('model_type', $user->getMorphClass())
        ->value('lang'))->toBe('id');
});

test('an unchanged locale does not create a duplicate language record', function (): void {
    // `updateOrCreate` dipakai justru untuk ini; kalau diubah jadi `create`,
    // setiap request akan menambah baris untuk user yang sama.
    //
    // Preferensi bahasa user TIDAK disimpan di kolom `users.lang` -- tidak ada
    // kolom itu. Ia ada di tabel `user_languages` lewat relasi morphOne
    // (`InteractsWithLanguages`), jadi baris awalnya dibuat di sana.
    $user = User::factory()->create();

    UserLanguage::create([
        'model_id' => (string) $user->id,
        'model_type' => $user->getMorphClass(),
        'lang' => 'id',
    ]);

    actingAs($user, 'web');

    localeAfterRequest(['locale' => 'id']);
    localeAfterRequest(['locale' => 'id']);

    expect(UserLanguage::query()
        ->where('model_id', (string) $user->id)
        ->where('model_type', $user->getMorphClass())
        ->count())->toBe(1);
});

test('a guest has no language record written', function (): void {
    // Tamu tidak punya baris user; menuliskan locale-nya ke tabel hanya
    // menambah sampah.
    localeAfterRequest(['locale' => 'en']);

    expect(UserLanguage::query()->count())->toBe(0);
});
