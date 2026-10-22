<?php

use App\Filament\User\Resources\CatalogResource\CatalogResource as UserCatalogResource;
use App\Filament\Welcome\Resources\CatalogResource\CatalogResource as WelcomeCatalogResource;
use App\Http\Middleware\AuthenticateWelcome\AuthenticateWelcome;
use App\Models\Catalog\Catalog;
use App\Models\Review\Review;
use App\Models\User\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/*
|--------------------------------------------------------------------------
| CreateReviews -- halaman tulis ulasan (bukan modal lagi)
|--------------------------------------------------------------------------
|
| Tombol "Tulis Ulasan" di halaman detail katalog dulunya membuka
| slideOver. Sekarang ia menaut ke halaman penuh `CreateReviews` yang
| terdaftar di `getPages()` resource. Test ini mengunci kontrak
| halaman barunya:
|
|   1. Halaman ada di kedua panel dan bisa dibuka anggota yang belum
|      menulis ulasan.
|   2. Submit menyimpan satu baris review yang tertaut ke user + item yang
|      benar, lalu kembali ke halaman detail.
|   3. Satu user hanya boleh punya satu ulasan per item -- dicek di mount()
|      DAN di submit(), jadi dua tab yang terbuka bersamaan tidak bisa
|      menyisipkan baris kedua.
|   4. Panel Welcome: tamu yang mengetik URL-nya langsung dilempar ke login
|      (ulasan butuh user_id), dan foto dari Repeater `photos` tersimpan --
|      bukan ke kolom `photo` saja seperti versi modal yang lama.
|
| MATRIXNYA SEKARANG DUA PANEL, BUKAN EMPAT.
|
| File ini dulu menjalankan setiap aturan terhadap 4 pasang resource (user dan
| welcome x katalog dan paket). Tiga di antaranya menunjuk
| `App\Filament\*\Resources\PackageResource\...`, yang sudah dihapus saat
| penataan ulang `Product` -> `Catalog`: `PackageResource` tidak ada lagi di
| kedua panel, dan `CreateReviews` yang tersisa hanya punya
| `protected static string $resource = CatalogResource::class`.
|
| Baris paket tidak dihapus diam-diam kecepet: `Review` masih punya kolom
| `package_id` dan model `Package` masih hidup, jadi reviewer Quite yang
| mengira ulasan paket masih bisa ditulis bisa bertanya "kenapa tidak ada
| halamannya?". Jawabannya ada di production, bukan di sini.
|
| Kalau halaman ulasan paket dihidupkan lagi, tambahkan resource-nya ke
| `reviewPageMatrix()` -- structurenya sudah siap menerima baris tambahan.
|
*/

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'customer']);
});

/**
 * Aktifkan panel yang sedang diuji.
 *
 * `Filament::setCurrentPanel()` menerima objek Panel, bukan string ---nya
 * ditentukan oleh tipe, jadi nama panel harus di-resolve dulu lewat
 * getPanel(). Tanpa ini, `Resource::getUrl()` milik panel lain akan
 * menghasilkan URL dari panel yang kebetulan aktif.
 */
function usePanel(string $panel): void
{
    Filament::setCurrentPanel(Filament::getPanel($panel));
}

/** Akun dari form email/password: `social_type` kosong, jadi middleware profil meloloskan. */
function makeReviewer(): User
{
    $user = User::factory()->create([
        'password' => Hash::make('senin-pagi-2026'),
        'social_type' => null,
        'email_verified_at' => now(),
    ]);

    $user->assignRole('customer');

    return $user;
}

/**
 * Pasangan resource -> panel, supaya setiap aturan di header bisa
 * dijalankan terhadap setiap halaman tanpa menulis test yang sama berkali-kali.
 *
 * Dua baris, bukan empat: lihat catatan di header file soalhilangnya
 * `PackageResource`.
 *
 * @return array<string, array{0: class-string, 1: string, 2: class-string}>
 */
function reviewPageMatrix(): array
{
    return [
        'user / katalog' => [UserCatalogResource::class, 'catalog', 'catalog_id'],
        'welcome / katalog' => [WelcomeCatalogResource::class, 'catalog', 'catalog_id'],
    ];
}

function makeItem(string $kind): Catalog
{
    return Catalog::factory()->create();
}

/**
 * Tulis satu ulasan yang sudah ada sebelumnya untuk (user, item).
 *
 * `Review` tidak memakai trait HasFactory, jadi `Review::factory()` tidak
 * ada -- makanya barisnya dibuat langsung, sama seperti test detail katalog
 * yang sudah ada di repo ini.
 */
function makeExistingReview(User $user, string $kind, Catalog $item): Review
{
    return Review::create([
        'user_id' => $user->id,
        'catalog_id' => $item->getKey(),
        'rating' => 4,
        'comment' => 'Ulasan lama.',
    ]);
}

/** Nama panel yang dimiliki sebuah resource, diturunkan dari namespace-nya. */
function panelFor(string $resource): string
{
    return str_contains($resource, '\\Welcome\\') ? 'welcome' : 'user';
}

/**
 * Kelas halaman CreateReviews milik sebuah resource.
 *
 * `Livewire::test()` menerima kelas komponen, BUKAN URL -- kalau diberi
 * `getUrl()` ia melempar ComponentNotFoundException atas URL penuhnya. Kelas
 * diambil kembali dari `getPages()`, yang sekaligus membuktikan halamannya
 * memang terdaftar di bawah nama itu.
 *
 * @return class-string
 */
function reviewPage(string $resource): string
{
    return $resource::getPages()['create-reviews']->getPage();
}

test('halaman create-reviews terdaftar di setiap resource', function (): void {
    // Yang diuji di sini adalah halaman benar-benar terdaftar pada resource,
    // bukan sekadar file yang ada di disk: `getPages()` yang mendaftarkan
    // rutenya, dan itulah yang membuat `getUrl('create-reviews')` bekerja.
    foreach ([UserCatalogResource::class, WelcomeCatalogResource::class] as $resource) {
        expect($resource::hasPage('create-reviews'))->toBeTrue();
    }
});

test('anggota yang belum menulis ulasan dapat membuka dan mengirim halaman create-reviews', function (string $resource, string $kind, string $column): void {
    usePanel(panelFor($resource));

    $user = makeReviewer();
    $item = makeItem($kind);

    actingAs($user);

    Livewire::test(reviewPage($resource), [
        'record' => $item->getKey(),
    ])
        ->assertOk()
        ->fillForm([
            'rating' => 5,
            'title' => 'Dekorasi sangat rapi',
            'comment' => 'Tim datang tepat waktu dan hasil akhirnya rapi sekali.',
        ])
        ->call('submit')
        ->assertHasNoFormErrors();

    $review = Review::query()->where('user_id', $user->id)->firstOrFail();

    expect($review->{$column})->toBe($item->getKey())
        ->and($review->rating)->toBe(5)
        ->and($review->comment)->toContain('rapi sekali');
})->with(reviewPageMatrix());

test('sudah pernah menulis ulasan tidak mendapat form, hanya pengalihan', function (string $resource, string $kind): void {
    usePanel(panelFor($resource));

    $user = makeReviewer();
    $item = makeItem($kind);

    makeExistingReview($user, $kind, $item);

    actingAs($user);

    Livewire::test(reviewPage($resource), [
        'record' => $item->getKey(),
    ])->assertRedirect($resource::getUrl('view', ['record' => $item->getKey()]));
})->with(reviewPageMatrix());

test('ulasan kedua untuk item yang sama ditolak tanpa menyisipkan baris baru', function (string $resource, string $kind): void {
    usePanel(panelFor($resource));

    $user = makeReviewer();
    $item = makeItem($kind);

    actingAs($user);

    // Dua tab yang BENAR-BENAR terbuka bersamaan: keduanya mounting saat
    // belum ada ulasan sama sekali, baru keduanya mengirim. Kalau tab kedua
    // baru dibuat SETELAH tab pertama selesai, `mount()`-lah yang menolaknya
    // dan guard di `submit()` tidak pernah teruji sama sekali.
    $firstTab = Livewire::test(reviewPage($resource), ['record' => $item->getKey()]);
    $secondTab = Livewire::test(reviewPage($resource), ['record' => $item->getKey()]);

    $firstTab
        ->fillForm([
            'rating' => 4,
            'comment' => 'Ulasan pertama.',
        ])
        ->call('submit')
        ->assertHasNoFormErrors();

    // Tab kedua masih memegang form yang sudah terisi, tapi review-nya sudah
    // ada sekarang. Guard di submit() yang harus menahan baris kedua.
    $secondTab
        ->fillForm([
            'rating' => 1,
            'comment' => 'Ulasan kedua yang tidak boleh masuk.',
        ])
        ->call('submit')
        ->assertRedirect($resource::getUrl('view', ['record' => $item->getKey()]));

    expect(Review::query()->where('user_id', $user->id)->count())->toBe(1)
        // Baris yang benar-benar tersimpan harus yang pertama -- kalau tidak,
        // check-nya hanya menghitung dan_second_-nya sudah berubah.
        ->and(Review::query()->where('user_id', $user->id)->first()->comment)->toBe('Ulasan pertama.');
})->with(reviewPageMatrix());

test('tamu yang mengetik URL create-reviews di panel welcome dilempar ke login', function (string $resource, string $kind): void {
    usePanel('welcome');

    $item = makeItem($kind);

    // Sengaja tanpa actingAs: panel welcome punya path publik, jadi middleware
    // TIDAK yang menahan halaman ini -- mount()-page yang harus melempar tamu.
    Livewire::test(reviewPage($resource), [
        'record' => $item->getKey(),
    ])->assertRedirect(route(AuthenticateWelcome::LOGIN_ROUTE));

    expect(Review::query()->count())->toBe(0);
})->with([
    'welcome / katalog' => [WelcomeCatalogResource::class, 'catalog'],
]);

test('foto dari repeater photos tersimpan di panel welcome', function (string $resource, string $kind, string $column): void {
    usePanel('welcome');

    $user = makeReviewer();
    $item = makeItem($kind);

    actingAs($user);

    Livewire::test(reviewPage($resource), [
        'record' => $item->getKey(),
    ])->fillForm([
        'rating' => 5,
        'comment' => 'Fotonya bagus.',
        'photos' => [
            ['photo' => 'review-photos/aaa.jpg'],
            ['photo' => 'review-photos/bbb.jpg'],
        ],
    ])->call('submit')->assertHasNoFormErrors();

    $review = Review::query()->where('user_id', $user->id)->firstOrFail();

    // `photos` yang jadi sumber kebenaran untuk panel welcome...
    expect($review->photos)->toBe(['review-photos/aaa.jpg', 'review-photos/bbb.jpg'])
        // ...dan foto pertama tetap ikut diisi ke kolom `photo` supaya
        // `Review::getPhotoUrlAttribute()` tetap melihat gambar.
        ->and($review->photo)->toBe('review-photos/aaa.jpg')
        ->and($review->{$column})->toBe($item->getKey());
})->with([
    'welcome / katalog' => [WelcomeCatalogResource::class, 'catalog', 'catalog_id'],
]);
