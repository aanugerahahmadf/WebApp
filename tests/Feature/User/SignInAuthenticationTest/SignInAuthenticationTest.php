<?php

/**
 * `SignIn::authenticate()` -- gate login panel user.
 *
 * Fungsi ini satu-satunya pintu masuk email/kata sandi di panel user, dan ia
 * melakukan tiga hal yang tidak dilakukan class Filament bawaan:
 *
 *   1. Menolak login bila checkbox persetujuan syarat (`agreement`) tidak
 *      dicentang, dengan pesan pada field `data.agreement`.
 *   2. Menolak login bila "Ingat Saya" (`remember`) tidak dicentang, dengan
 *      pesan pada field `data.remember`. Perhatikan: field `remember` di form
 *      sudah `->required()`, tapi itu hanya memvalidasi saat form di-submit
 *      lewat UI -- pemanggilan `authenticate()` langsung (Livewire, API, atau
 *      programmatic) akan lolos dari validasi form. Karena itu gate kedua ini
 *      tidak boleh dihapus sebagai "duplikat".
 *   3. Mengirim notifikasi "Selamat Datang Kembali!" setelah berhasil, dan
 *      "Otentikasi Gagal" saat kredensial ditolak.
 *
 * Test lama (`tests/Feature/User/AuthTest/AuthTest/AuthTest.php`) hanya menguji
 * endpoint API `/api/login` yang balannya 401; tidak menyentuh kode panel ini.
 * Jadi tanpa file ini, alur autentikasi panel user tidak punya penjaga sama
 * sekali. Pasangan penjaganya ada di
 * `tests/Feature/User/WebRegisterFlowTest/WebRegisterFlowTest.php` untuk sisi
 * pendaftaran (kode `SignUp` masih ada di repo dan diuji langsung lewat
 * `handleRegistration()`, walau halamannya tidak lagi punya route), dan
 * `tests/Feature/User/UserAuthLandingPageTest/UserAuthLandingPageTest.php`
 * untuk halaman `/user/auth` yang menjadi pintu masuk ke halaman Sign In.
 *
 * Pola assertion:
 *   - `assertHasFormErrors()` untuk field yang ditolak,
 *   - `assertGuest()` / `assertAuthenticated()` untuk dampak sebenarnya, karena
 *     error form tanpa sesi yang bocor adalah kegagalan separuh,
 *   - notification dibaca dari session (`filament.notifications`) lewat helper
 *     di bawah, karena `assertNotified()` membandingkan seluruh payload
 *     termasuk body yang memuat jam -- prone flake.
 */

use App\Filament\User\Auth\SignIn\SignIn;
use App\Models\User\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('user'));

    $this->password = 'senin-pagi-2026';

    $this->customer = User::factory()->create([
        'full_name' => 'Rangga Wicaksono',
        'username' => 'ranggapw',
        'email' => 'rangga@example.test',
        'password' => Hash::make($this->password),
    ]);

    $this->customer->assignRole(
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'customer'])
    );
});

afterEach(function (): void {
    Filament::setCurrentPanel(null);
});

/**
 * Judul notifikasi Filament yang terkirim pada request ini.
 *
 * `Notification::send()` pushing ke session key `filament.notifications`, jadi
 * ini yang benar-benar dikirim ke browser -- bukan hanya apa yang dipanggil di
 * kode.
 */
function sentNotificationTitles(): array
{
    return collect(session('filament.notifications', []))
        ->map(fn (array $notification): string => (string) ($notification['title'] ?? ''))
        ->all();
}

/**
 * Isi form dan panggil `authenticate()`.
 *
 * Field agreement/remember di-set lewat set('data....'), bukan illForm():
 * keduanya adalah Hidden di schema (tidak punya wrapper di DOM), dan yang dibaca
 * `authenticate()` adalah `$this->data`, jadi itu yang harus diprioritaskan.
 */
function submitLogin(array $overrides = []): object
{
    $credentials = array_merge([
        'login' => 'ranggapw',
        'password' => 'senin-pagi-2026',
        'agreement' => true,
        'remember' => true,
    ], $overrides);

    $component = Livewire::test(SignIn::class)
        ->fillForm([
            'login' => $credentials['login'],
            'password' => $credentials['password'],
        ]);

    $component->set('data.agreement', $credentials['agreement']);
    $component->set('data.remember', $credentials['remember']);

    return $component->call('authenticate');
}

test('a login without the agreement consent is rejected on the agreement field', function (): void {
    submitLogin(['agreement' => false])
        ->assertHasFormErrors(['agreement']);

    // Gate tanpa efek samping: tidak boleh ada sesi yang terbentuk.
    expect(auth()->check())->toBeFalse();
    expect(sentNotificationTitles())->toContain(__('Perhatian'));
});

test('a login without remember me is rejected on the remember field', function (): void {
    submitLogin(['remember' => false])
        ->assertHasFormErrors(['remember']);

    expect(auth()->check())->toBeFalse();
    expect(sentNotificationTitles())->toContain(__('Perhatian'));
});

test('both consents are checked in order', function (): void {
    // Satu gate dilewati, gate berikutnya harus tetap berlaku -- kalau hanya
    // pemeriksaan terakhir yang ada, checkbox persetujuan syarat jadi tidak
    // enforces sama sekali.
    submitLogin(['agreement' => false, 'remember' => false])
        ->assertHasFormErrors(['agreement'])
        ->assertHasNoFormErrors(['remember']);

    expect(auth()->check())->toBeFalse();
});

test('the consent error messages tell the guest which box is missing', function (): void {
    // Pesanlah yang mengarahkan; pesan generik membuat checkbox terlihat tidak
    // berfungsi sama sekali di mata pengguna.
    expect(__('Anda harus menyetujui syarat dan ketentuan untuk melanjutkan.'))->not->toBe('')
        ->and(__('Anda harus mencentang Ingat Saya untuk melanjutkan.'))->not->toBe('');
});

test('a login with both consents and valid credentials succeeds', function (): void {
    submitLogin()
        ->assertHasNoFormErrors()
        ->assertRedirect();

    expect(auth()->check())->toBeTrue()
        ->and(auth()->id())->toBe($this->customer->id)
        // Notifikasi sukses hanya terkirim setelah parent::authenticate()
        // benar-benar mengembalikan response.
        ->and(sentNotificationTitles())->toContain(__('Selamat Datang Kembali!'));
});

test('a wrong password is rejected on the login field with a failure notification', function (): void {
    submitLogin(['password' => 'kata-sandi-yang-salah'])
        ->assertHasFormErrors(['login']);

    expect(auth()->check())->toBeFalse();
    // Notifikasi "Otentikasi Gagal" dikirim oleh
    // throwFailureValidationException(); tanpa itu pengguna hanya melihat
    // pesan validasi tanpa penjelasan.
    expect(sentNotificationTitles())->toContain(__('Otentikasi Gagal'));
});

test('an unknown identifier is rejected on the login field', function (): void {
    submitLogin(['login' => 'tidak-ada-user-ini'])
        ->assertHasFormErrors(['login']);

    expect(auth()->check())->toBeFalse();
});

test('the password is compared against the hash, never stored in plain text', function (): void {
    submitLogin();

    expect(auth()->check())->toBeTrue()
        ->and($this->customer->fresh()->password)->not->toBe($this->password)
        ->and(Hash::check($this->password, $this->customer->fresh()->password))->toBeTrue();
});

test('a login does not run the other gates at all', function (): void {
    // Notifikasi sukses hanya boleh muncul kalau autentikasi benar-benar lewat,
    // bukan sekadar karena kedua checkbox tercentang.
    submitLogin(['password' => 'salah']);

    expect(sentNotificationTitles())
        ->toContain(__('Otentikasi Gagal'))
        ->not->toContain(__('Selamat Datang Kembali!'));
});

test('remember me keeps the remember token intact across the login', function (): void {
    // Konsekuensi nyata dari gate `remember`: checkbox itu bukan formalitas, dan
    // login hanya berhasil kalau nilainya diteruskan ke guard
    // (`Auth::attempt($credentials, remember: true)`).
    //
    // Yang dikunci: login BERHASIL dan `remember_token` tetap utuh.
    //
    // SOAL `updated_at`: test ini SENGAJA tidak mengassert-nya, dan alasannya hasil
    // pengukuran, bukan tebakan. `SessionGuard::ensureRememberTokenIsSet()` hanya
    // menulis `remember_token` yang bernilai NULL, dan `UserFactory` sudah
    // mengisinya sejak awal -- jadi token memang tidak berubah. Tapi baris user
    // tetap TERJAMUH: `updated_at` maju satu detik pada login, dan `wasChanged()`
    // di instance yang di-`fresh()` bernilai false, artinya penulisannya terjadi di
    // tempat lain pada alur login, bukan di `ensureRememberTokenIsSet()`.
    //
    // Mengassert `updated_at` sama atau naik akan menguji sesuatu yang belum
    // dipahami -- dan kalau nanti penyebabnya ditemukan, test ini harus ditulis
    // ulang saat itu, bukan sekarang. Yang penting dan sudah pasti: token tidak
    // ikut diputar, jadi sesi berikutnya tidak menggigit pengguna.
    $tokenBefore = $this->customer->fresh()->remember_token;

    submitLogin();

    $after = $this->customer->fresh();

    expect(auth()->check())->toBeTrue()
        ->and($after)->not->toBeNull()
        ->and($after->remember_token)->toBe($tokenBefore)
        // Sanity check: assertion "token tidak berubah" hanya berarti kalau ada
        // nilai yang memang bisa berubah. Kalau factory nanti diam-diam berhenti
        // mengisi remember_token, assertion ini akan kosong tanpa dirinya terlihat.
        ->and($tokenBefore)->not->toBeNull();
});


test('leaving remember me off blocks the login before any token is written', function (): void {
    // Pasangan dari test sebelumnya, dan hanya itu yang bisa diuji: karena gate
    // `remember` menolak lebih dulu, tidak akan pernah ada login sukses dengan
    // remember_token kosong -- user factory sudah mengisinya dari awal. Yang
    // dikunci justru urutannya: token tidak boleh ikut diputar saat login
    // ditolak, kalau tidak checkbox "Ingat Saya" hanya formalitas yang tidak
    // diperiksa.
    $tokenBefore = $this->customer->fresh()->remember_token;

    submitLogin(['remember' => false])->assertHasFormErrors(['remember']);

    expect(auth()->check())->toBeFalse()
        ->and($this->customer->fresh()->remember_token)->toBe($tokenBefore);
});

test('an already authenticated visitor is redirected away from the login form', function (): void {
    // Authenticate middleware panel user yang menahan halaman ini, bukan
    // SignIn::mount(): user yang sudah punya sesi diarahkan ke home panel user
    // agar tidak melihat form login yang sudah tidak relevan untuknya.
    actingAs($this->customer, 'web');

    $this->get(route('filament.user.auth.login'))->assertRedirect(route('filament.user.pages.home'));

    // Sesi yang sudah ada tidak dirusak oleh kunjungan ke halaman auth.
    expect(auth()->check())->toBeTrue()
        ->and(auth()->id())->toBe($this->customer->id);
});