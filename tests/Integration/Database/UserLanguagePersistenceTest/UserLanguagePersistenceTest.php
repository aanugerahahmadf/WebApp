<?php

/*
 * Bahasa yang tersimpan di database, diuji lewat request HTTP sungguhan.
 *
 * Yang membuat file ini layak ada di `tests/Integration`: `SetLocale` bukan
 * hanya membaca bahasa -- dia juga MENULIS ke tabel `user_languages`. Jadi
 * middleware ini punya dua wajah, dan yang kedua (tulis) hanya bisa diuji dengan
 * request sungguhan yang benar-benar menyentuh MySQL. Test `SetLocaleMiddlewareTest`
 * di `Feature` sudah menjajaki cabangnya, tapi file ini mengunci hal yang berbeda:
 * apa yang TERSIMPAN setelah request, dan apakah penulisan itu idempoten.
 *
 * Skema yang dipakai (bukan yang diasumsikan):
 *   user_languages.id, model_id (string), model_type (string), lang (string|null)
 *   relasi = morphOne dari User lewat `InteractsWithLanguages::lang()`
 *
 * Dua detail yang mudah terlewat dan biasanya jadi sumber bug:
 *
 *   1. `model_id` bertipe STRING di skema, padahal primary key `users.id` adalah
 *      INTEGER. `SetLocale` menulis `(string) $user->id`, jadi baca-baliknya harus
 *      tetap cocok -- kalau tidak, pencarian bahasa tidak akan pernah ketemu.
 *   2. `getLangAttribute()` mengembalikan `'en'` kalau relasinya kosong. Jadi
 *      "tidak ada baris" dan "baris berisi en" TIDAK bisa dibedakan lewat accessor.
 *      Test ini karena itu selalu memeriksa barisnya secara langsung, bukan cuma
 *      nilai `$user->lang`.
 */

use App\Models\User\User;
use App\Models\UserLanguage\UserLanguage;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

/**
 * Baris bahasa milik user ini, dibaca langsung dari tabel -- bukan lewat accessor.
 */
function languageRowFor(User $user): ?UserLanguage
{
    return UserLanguage::query()
        ->where('model_id', (string) $user->getKey())
        ->where('model_type', $user->getMorphClass())
        ->first();
}

test('the accessor defaults to english when nothing is stored', function (): void {
    expect(languageRowFor($this->user))->toBeNull()
        ->and($this->user->lang)->toBe('en');
});

test('a locale query parameter is persisted for a signed in user', function (): void {
    actingAs($this->user);

    get('/user/home?locale=id')->assertOk();

    $row = languageRowFor($this->user);

    // Diperiksa lewat baris tabel, bukan `$user->lang`, karena accessor selalu
    // mengembalikan 'en' saat relasi kosong dan jadi tidak bisa membedakan
    // "tidak tersimpan" dari "tersimpan tapi bernilai en".
    expect($row)->not->toBeNull()
        ->and($row->lang)->toBe('id')
        ->and($this->user->fresh()->lang)->toBe('id');
});

test('the stored locale is matched through a string model id', function (): void {
    actingAs($this->user);

    get('/user/home?locale=id')->assertOk();

    $row = languageRowFor($this->user);

    // `model_id` disimpan sebagai string oleh SetLocale sementara `users.id`
    // adalah integer. Test ini gagal kalau skema atau casting berubah, karena
    // polymorphic query-nya tidak akan lagi ketemu -- dan pilihan bahasa
    // pengguna akan hilang diam-diam setiap kali halaman dimuat ulang.
    expect((string) $row->model_id)->toBe((string) $this->user->getKey())
        ->and($this->user->fresh()->lang)->toBe('id');
});

test('switching the locale updates the same row instead of adding one', function (): void {
    actingAs($this->user);

    get('/user/home?locale=id')->assertOk();
    get('/user/home?locale=en')->assertOk();
    get('/user/home?locale=id')->assertOk();

    // `updateOrCreate` pada kombinasi model_id + model_type. Kalau suatu saat
    // berubah jadi `create()`, baris akan menumpuk dan setiap kali halaman
    // dimuat akan ada baris baru -- tabel mengembang tanpa batas.
    $rows = UserLanguage::query()
        ->where('model_id', (string) $this->user->getKey())
        ->where('model_type', $this->user->getMorphClass())
        ->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->lang)->toBe('id');
});

test('a guest request never writes a language row', function (): void {
    // `SetLocale` butuh user yang sudah login untuk menulis. Kalau nanti
    // ditambahkan cabang yang menulis untuk tamu, baris dengan model_id kosong
    // akan menumpuk dan tidak pernah punya pemilik.
    get('/user/signin?locale=id')->assertOk();

    expect(UserLanguage::query()->count())->toBe(0);
});

test('a locale query parameter beats the stored preference', function (): void {
    actingAs($this->user);

    get('/user/home?locale=id')->assertOk();

    expect(languageRowFor($this->user)->lang)->toBe('id');

    // Urutan di `SetLocale`: query > session > header > DB. Meminta 'en' secara
    // eksplisit harus menimpa 'id' yang sudah tersimpan -- kalau tidak, pengguna
    // tidak akan pernah bisa mengubah pilihannya kembali.
    get('/user/home?locale=en')->assertOk();

    expect(languageRowFor($this->user)->lang)->toBe('en');
});

test('an unsupported locale is never written to the database', function (): void {
    actingAs($this->user);

    get('/user/home?locale=zz')->assertOk();

    // Tidak ada baris sama yang ditulis, dan inilah yang benar. `?locale=` adalah
    // query string dari pengguna, jadi nilainya wajib disaring terhadap daftar
    // locale yang benar-benar ada sebelum dipakai -- persis seperti yang sudah
    // dilakukan branch `Accept-Language` sejak awal.
    //
    // Kalau diteruskan apa adanya, 'zz' tersimpan sebagai preferensi. Pada
    // kunjungan berikutnya baris itu dibaca sebagai preferensi yang sah, jadi
    // pengguna tertahan di locale yang tidak punya berkas terjemahan. Tidak ada
    // test Feature yang akan melihatnya, karena yang salah ada di tabel, bukan
    // di response.
    expect(languageRowFor($this->user))->toBeNull()
        ->and(UserLanguage::query()->where('lang', 'zz')->count())->toBe(0);
});

test('a locale that differs only in casing is still rejected', function (): void {
    actingAs($this->user);

    // Case-sensitive dengan sengaja. 'ID' bukan 'id', dan melonggarkannya dengan
    // `strtolower()` akan membuat query string bisa menyamar sebagai locale yang
    // sah padahal tidak ada berkasnya dengan nama itu.
    get('/user/home?locale=ID')->assertOk();

    expect(languageRowFor($this->user))->toBeNull();
});

test('a valid locale is still written after the filtering change', function (): void {
    // Penyeimbang dua test di atas: penyaringan tidak boleh membuat locale yang
    // benar ikut tertolak. Tanpa test ini, memperbaiki bug "menulis garbage" dengan
    // cara membuang semua input query akan tetap terlihat hijau.
    actingAs($this->user);

    get('/user/home?locale=id')->assertOk();

    expect(languageRowFor($this->user))->not->toBeNull()
        ->and(languageRowFor($this->user)->lang)->toBe('id');
});


test('the language row belongs to the requesting user only', function (): void {
    $other = User::factory()->create();

    actingAs($this->user);

    get('/user/home?locale=id')->assertOk();

    expect(languageRowFor($other))->toBeNull()
        ->and(languageRowFor($this->user)->lang)->toBe('id');
});