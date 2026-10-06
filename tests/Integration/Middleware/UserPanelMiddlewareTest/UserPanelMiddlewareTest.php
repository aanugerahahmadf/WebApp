<?php

/*
 * Rangkaian middleware panel User, diuji lewat request HTTP sungguhan.
 *
 * Kenapa file ini ada di `tests/Integration` dan bukan `Feature`: yang diuji bukan
 * satu komponen, tapi RANTAIAN middleware yang saling menimpa. `EnsureProfileComplete`
 * hanya terlihat efeknya kalau `Filament\Http\Middleware\Authenticate` sudah
 * meloloskan tamunya lebih dulu; `SetLocale` hanya bisa menguji cabangnya kalau
 * session benar-benar ada. Test yang memanggil `Livewire::test()` atau satu
 * request tidak pernah melewati semua itu sesuai konfigurasi aslinya.
 *
 * Konfigurasi yang diuji apa adanya, tidak direkonstruksi di sini:
 *   UserPanelProvider ->middleware([...])
 *       ClerkFilamentAuth, EncryptCookies, AddQueuedCookiesToResponse,
 *       StartSession, SetLocale, AuthenticateSession, ShareErrorsFromSession,
 *       VerifyCsrfToken, SubstituteBindings, DisableBladeIconComponents
 *   UserPanelProvider ->authMiddleware([...])
 *       Authenticate, EnsureProfileComplete, CheckTrustedDevice (plugin 2FA)
 *   WelcomePanelProvider ->authMiddleware([...])  AuthenticateWelcome saja
 *   routes/web/web.php   route /admin/... + middleware SuperAdmin
 *
 * CATATAN SOAL `Filament::getPanel('user')->getUrl()`. Method itu mengembalikan
 * URL LOGIN (`/user/signin`), bukan home, karena panel tidak punya halaman home
 * terdaftar lewat `->home()` -- yang terdaftar adalah halaman `Home` sebagai
 * route `filament.user.pages.home`. Test yang memakai `getUrl()` lalu menganggap
 * "tamu tidak_redirect" padahal yang ia buka memang halaman login yang memang
 * harus terbuka untuk tamu. Karena itu file ini memakai
 * `route('filament.user.pages.home')` secara eksplisit.
 *
 * Yang dikunci:
 *   1. Tamu di /user/home dapat 302 ke /user/signin -- bukan 403, bukan 500.
 *   2. Halaman auth (`/user/auth`) dan Sign In tetap terbuka untuk tamu; tanpa itu
 *      tidak ada pintu masuk sama sekali.
 *   3. `EnsureProfileComplete` menahan HANYA akun dari tombol Google yang profilnya
 *      belum lengkap. Akun form harus langsung masuk.
 *   4. Akun Google yang emailnya belum terverifikasi TIDAK dilempar ke
 *      complete-profile, tapi ke prompt verifikasi email -- supaya flow OTP tidak
 *      terputus.
 *   5. `AuthenticateWelcome` membuka daftar path publik untuk tamu, dan hanya
 *      path di luar daftar itu yang dialihkan. Yang jadi tujuan penolakan itu
 *      halaman auth pertama panel user (/user/auth, Sign In + Google) lewat
 *      `LOGIN_ROUTE` -- BUKAN form /user/signin. Satu panel punya dua pintu auth
 *      dengan aturan berbeda, jadi keduanya tidak boleh dicampur: test 1 masih
 *      ke form (panel user punya halaman login sendiri), test 5 ke /user/auth.
 *   6. Middleware `SuperAdmin` membalas 403, bukan redirect -- berbeda dari
 *      `Authenticate`, dan itu yang membuatnya tidak bisa dipakai membocorkan
 *      keberadaan halaman.
 */

use App\Filament\User\Auth\Auth\Auth;
use App\Http\Middleware\AuthenticateWelcome\AuthenticateWelcome;
use App\Models\User\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'customer']);
    Role::firstOrCreate(['name' => 'super_admin']);
});

/**
 * Halaman "rumah" panel user.
 *
 * Dipakai sebagai ganti `Panel::getUrl()`, yang mengembalikan URL login. Lihat
 * catatan di header file.
 */
function userHome(): string
{
    return route('filament.user.pages.home');
}

function userSignIn(): string
{
    return route('filament.user.auth.login');
}

/**
 * Akun dari form email/password: `social_type` kosong, jadi
 * `shouldCompleteProfile()` mengembalikan false dan middleware meloloskannya.
 */
function makeFormUser(): User
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
 * Akun dari tombol Google: `social_type` terisi, profil masih kosong.
 */
function makeGoogleUser(array $overrides = []): User
{
    $user = User::factory()->create(array_merge([
        'social_type' => 'google',
        'email_verified_at' => now(),
    ], $overrides));

    $user->assignRole('customer');

    return $user;
}

test('a guest is redirected from the panel home to the sign in page', function (): void {
    $response = get(userHome());

    // Yang diperiksa adalah KODE redirect, bukan hanya tujuannya: 302 berarti
    // middleware berjalan, 403 berarti `EnsureProfileComplete` salah
    // diperlakukan sebagai policymen, dan 500 berarti exception yang tertangani
    // di mana saja.
    $response->assertRedirect(userSignIn());

    expect($response->getStatusCode())->toBe(302);
});

test('a guest can still open the sign in page and the auth landing page', function (): void {
    // `/user/signin` dan `/user/auth` harus terbuka untuk tamu. Kalau salah satu
    // ikut terkunci, tidak ada pintu masuk sama sekali untuk orang yang belum
    // punya akun -- dan `/user/auth` adalah satu-satunya tempat tombol Google,
    // yaitu satu-satunya jalur pendaftaran yang masih hidup.
    get(userSignIn())->assertOk();
    get(Auth::getUrl())->assertOk();
});

test('a guest can browse the public welcome storefront', function (): void {
    // Semua path ini ada di `AuthenticateWelcome::PUBLIC_PATHS`. Kalau daftarnya
    // menyempit, etalase toko ikut terkunci untuk tamu -- dan itu tidak akan
    // terlihat dari test panel user sama sekali.
    foreach (['/welcome/home', '/welcome/messages', '/welcome/flowerdecorationscatalog'] as $path) {
        get($path)->assertOk();
    }
});

test('a guest is redirected away from the private welcome pages', function (): void {
    // Pasangan dari test sebelumnya. `/welcome/orders` dan `/welcome/settings`
    // sengaja TIDAK ada di PUBLIC_PATHS, jadi keduanya harus dialihkan ke panel
    // user -- bukan ke panel welcome (yang tidak punya ->login()).
    //
    // Targetnya HALAMAN AUTH PERTAMA (/user/auth, Sign In + Google), bukan form
    // /user/signin: middleware mengalurkan lewat AuthenticateWelcome::LOGIN_ROUTE.
    // Memakai `userSignIn()` di sini akan membekukan kontrak lama -- sementara
    // test `a guest is redirected from the panel home` di atas tetap memakai
    // form, karena panel user punya halaman login sendiri.
    foreach (['/welcome/orders', '/welcome/settings'] as $path) {
        get($path)->assertRedirect(route(AuthenticateWelcome::LOGIN_ROUTE));
    }
});

test('the welcome panel root redirects guests to its own home', function (): void {
    // `/welcome` sendiri hanya pengantar, jadi tamu diarahkan ke `/welcome/home`.
    // Bedanya dengan test di atas: pengantar TIDAK ikut ke panel user.
    get('/welcome')->assertRedirect('/welcome/home');
});

test('a signed in form user reaches the panel without any redirect', function (): void {
    // `social_type` kosong => `shouldCompleteProfile()` false =>
    // `EnsureProfileComplete` harus meloloskan tanpa redirect sama sekali.
    actingAs(makeFormUser());

    get(userHome())->assertOk();
});

test('a google user with an incomplete profile is sent to complete profile', function (): void {
    actingAs(makeGoogleUser());

    get(userHome())->assertRedirect(route('filament.user.pages.complete-profile'));
});

test('a google user with a complete profile reaches the panel', function (): void {
    // Kolomnya harus sama persis dengan `User::isProfileComplete()`. Kalau daftar
    // di sana berubah, test inilah yang memberitahu -- bukan halaman yang diam saja
    // sambil menahan semua akun Google.
    $user = makeGoogleUser([
        'first_name' => 'Jane',
        'mid_name' => 'Ann',
        'last_name' => 'Doe',
        'whatsapp' => '+628123456789',
        'gender' => 'Wanita',
        'address' => 'Jl. Braga No. 2, Bandung',
        'occupation' => 'Wiraswasta',
        'identity_type' => 'KTP',
    ]);

    expect($user->isProfileComplete())->toBeTrue();

    actingAs($user);

    get(userHome())->assertOk();
});

test('an unverified google user is sent to email verification not to complete profile', function (): void {
    // Cabang yang paling mudah hilang dari `EnsureProfileComplete`: email belum
    // terverifikasi karena akunnya baru dibuat lewat Google, dan yang perlu
    // berikutnya adalah verifikasi email -- bukan complete-profile. Melemparnya
    // ke complete-profile akan memutus flow OTP, dan gejalanya (pengguna
    // terjebak di halaman yang salah) tidak terlihat dari test mana pun selain
    // test middleware.
    actingAs(makeGoogleUser(['email_verified_at' => null]));

    get(userHome())
        ->assertRedirect(route('filament.user.auth.email-verification.prompt'));
});

test('a super admin bypasses the complete profile requirement', function (): void {
    $user = makeGoogleUser();
    $user->assignRole('super_admin');

    actingAs($user);

    get(userHome())->assertOk();
});

test('the complete profile page is reachable for an incomplete account', function (): void {
    // Tanpa test ini akan ada redirect beruntun: complete-profile -> home ->
    // complete-profile, karena middleware tidak mengenali dirinya sendiri sebagai
    // tujuan yang sah dan terus melempar balik ke sana.
    actingAs(makeGoogleUser());

    get('/user/complete-profile')->assertOk();
});

test('an unverified account is kept away from complete profile until it verifies', function (): void {
    // Cabang "biarkan flow OTP dulu" di `EnsureProfileComplete` berarti: jangan
    // lempar ke complete-profile, TAPI jangan juga tutup jalan ke sana.
    // Kalau cabang ini hilang, akun Google yang emailnya belum terverifikasi akan
    // terkurung di prompt verifikasi tanpa pernah sampai halaman profil -- dan
    // tidak ada test lain yang akan melihat itu.
    actingAs(makeGoogleUser(['email_verified_at' => null]));

    $response = get('/user/complete-profile');

    $response->assertRedirect(route('filament.user.auth.email-verification.prompt'));

    // Hanya redirect: tidak ada penolakan keras (403) dan tidak ada halaman
    // error. Ini yang membuatnya "dilewati", bukan "diblokir".
    expect($response->getStatusCode())->toBe(302);
});

test('a non super admin gets 403 on a super admin only route', function (): void {
    actingAs(makeFormUser());

    // `SuperAdmin` memanggil `abort(403)`, bukan redirect. Perbedaannya penting:
    // 403 tidak menulis `url.intended`, jadi tidak ada redirect yang bisa dipakai
    // untuk menebak halaman mana yang ada dan mana yang tidak.
    get('/admin/data-exports/download/laporan.pdf')->assertForbidden();
});

test('a guest is redirected to login before the super admin guard runs', function (): void {
    // Rute ini punya dua middleware: `auth` dari group prefix('admin'), lalu
    // `SuperAdmin`. Yang berjalan lebih dulu adalah `auth`, jadi tamu mendapat
    // 302, BUKAN 403.
    //
    // Bedanya penting untuk escrito: kalau urutan dibalik, tamu akan mendapat
    // 403 dan `url.intended` tidak pernah tertulis -- tapi yang lebih berguna,
    // test ini mengunci urutan itu sebagai hal yang disengaja, bukan kebetulan.
    // Dan karena `auth` yang menang, rute ini sekaligus membuktikan `SuperAdmin`
    // benar-benar bekerja: tanpa dia, akun biasa akan lolos ke controller dan
    // mengunduh berkas ekspor.
    get('/admin/data-exports/download/laporan.pdf')
        ->assertRedirect(userSignIn());
});

test('a super admin passes the super admin only route guard', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('super_admin');

    actingAs($user);

    // Yang diuji adalah middleware-nya, bukan isi PDF-nya. Jadi cukup pastikan
    // request tidak berhenti di `SuperAdmin`; error di lapisan berikutnya (404
    // karena berkas tidak ada) justru bukti bahwa guard-nya bekerja.
    $response = get('/admin/data-exports/download/laporan.pdf');

    expect($response->getStatusCode())->not->toBe(403);
});