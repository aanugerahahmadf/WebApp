<?php

/**
 * `SignIn::getCredentialsFromFormData()` -- menerjemahkan satu kolom input
 * "login" menjadi pasangan kredensial email atau username.
 *
 * ATURAN SAAT INI: sign in hanya menerima Email atau Username.
 *
 * Sebelumnya field login mengiklankan "KTP / Passport / Driving License /
 * NPWP / Username / Email" dan method ini mencocokkan input ke enam kolom
 * (`email | username | ktp_number | passport_number | sim_number | npwp_number`).
 * Itu berarti field UI menjanjikan sesuatu yang tidak konsisten dengan app
 * Flutter, yang hanya punya satu field berlabel "Email / Username" dan mengirim
 * `{login, password}` ke `/api/login`. Akibatnya ada dua permukaan login dengan
 * aturan berbeda, dan nomor dokumen yang diketik di panel Filament ditolak
 * tanpa penjelasan.
 *
 * Sekarang nomor dokumen TIDAK lagi jadi alias login. Konsekuensinya:
 *   1. Query menyentuh satu kolom ter-index, bukan enam kolom di-orWhere.
 *   2. Kolom identitas lain (ktp/passport/sim/npwp) tetap ada di tabel users
 *      dan tetap dipakai untuk KYC -- hanya hilang dari jalur login.
 *
 * Yang membuat method ini rawan: ia menebak kolom dari bentuk input
 * (`filter_var(..., FILTER_VALIDATE_EMAIL)` -> email, selain itu username).
 * Kalau tebakan itu salah, `Auth::attempt()` diam-diam gagal dan pengguna
 * hanya melihat "Otentikasi Gagal".
 *
 * Metode ini `protected`, jadi dipanggil lewat reflection pada instance yang
 * dibuat tanpa constructor -- tidak butuh state Livewire, dan pemanggilannya
 * tidak melewati HTTP maupun form, sehingga test ini murni soal pemetaan kolom.
 */

use App\Filament\User\Auth\SignIn\SignIn;
use App\Models\User\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('user'));

    // User dengan email, username, dan keempat nomor dokumen terisi, supaya
    // test bisa membuktikan nomor dokumen TIDAK lagi dipakai sebagai login.
    $this->user = User::factory()->create([
        'full_name' => 'Sekar Ayu Pratama',
        'username' => 'sekarayu',
        'email' => 'sekar@example.test',
        'ktp_number' => '3201234567890001',
        'passport_number' => 'X1234567',
        'sim_number' => 'S987654321',
        'npwp_number' => '09.254.294.3-407.000',
        'password' => Hash::make('rahasia-panel-user'),
    ]);
});

afterEach(function (): void {
    Filament::setCurrentPanel(null);
});

/**
 * Panggil method protected dengan instance tanpa constructor.
 *
 * `newInstanceWithoutConstructor()` penting: constructor Livewire membutuhkan
 * container page yang sudah di-boot, sementara method ini hanya membaca argumen
 * dan menyentuh database lewat model.
 */
function credentialsFor(string $identifier, string $password = 'apa-saja'): array
{
    $page = (new ReflectionClass(SignIn::class))->newInstanceWithoutConstructor();

    $method = new ReflectionMethod(SignIn::class, 'getCredentialsFromFormData');
    $method->setAccessible(true);

    return $method->invoke($page, ['login' => $identifier, 'password' => $password]);
}

test('an email identifier resolves to the email column', function (): void {
    expect(credentialsFor('sekar@example.test'))
        ->toBe(['email' => 'sekar@example.test', 'password' => 'apa-saja']);
});

test('a username identifier resolves to the username column', function (): void {
    expect(credentialsFor('sekarayu'))
        ->toBe(['username' => 'sekarayu', 'password' => 'apa-saja']);
});

test('a document number is no longer accepted as a login', function (string $identifier, string $column): void {
    // Keempat nomor dokumen KETEMU di user, tapi kredensial yang dikembalikan
    // tetap kolom `username` -- bukan kolom nomornya. Auth::attempt() akan
    // menolak, dan itu memang perilaku yang diinginkan: nomor dokumen bukan
    // lagi kredensial, dan kolomnya masih ada untuk keperluan KYC.
    $user = $this->user->fresh();
    expect($user->{$column})->toBe($identifier);

    expect(credentialsFor($identifier))
        ->toBe(['username' => $identifier, 'password' => 'apa-saja']);
})->with([
    'KTP' => ['3201234567890001', 'ktp_number'],
    'passport' => ['X1234567', 'passport_number'],
    'SIM' => ['S987654321', 'sim_number'],
    'NPWP' => ['09.254.294.3-407.000', 'npwp_number'],
]);

test('an unknown identifier falls back to the username column', function (): void {
    // Bukan email -> kolom awal `username`. Authentication berikutnya yang akan
    // menolak, dan itu benar: kredensial harus tetap well-formed supaya
    // failure-nya muncul sebagai "Otentikasi Gagal", bukan sebagai exception.
    expect(credentialsFor('tidak-ada'))
        ->toBe(['username' => 'tidak-ada', 'password' => 'apa-saja']);
});

test('the password is passed through untouched', function (): void {
    // Kredensial harus berisi password apa adanya (belum di-hash): hashing-nya
    // tugas Auth::attempt(). Kalau di-hash di sini, semua login gagal dengan
    // pesan yang menyesatkan.
    expect(credentialsFor('sekarayu', 'kata-sandi-mentah'))
        ->toHaveKey('password', 'kata-sandi-mentah');
});

test('the credentials array holds exactly one identity field', function (): void {
    // Pengaman terhadap kembalinya orWhere enam kolom: kredensial tidak boleh
    // memuat kolom identitas selain email atau username.
    expect(credentialsFor('sekar@example.test'))->toHaveKeys(['email', 'password'])
        ->and(credentialsFor('sekarayu'))->toHaveKeys(['username', 'password']);

    foreach (['ktp_number', 'passport_number', 'sim_number', 'npwp_number'] as $column) {
        expect(credentialsFor('sekarayu'))->not->toHaveKey($column);
    }
});

test('login by email authenticates end to end', function (): void {
    // Jalur sukses yang kini menjadi jalur utama: panel Filament dan app
    // Flutter memakai aturan yang sama.
    Livewire\Livewire::test(SignIn::class)
        ->fillForm(['login' => 'sekar@example.test', 'password' => 'rahasia-panel-user'])
        ->set('data.agreement', true)
        ->set('data.remember', true)
        ->call('authenticate')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    expect(auth()->check())->toBeTrue()
        ->and(auth()->id())->toBe($this->user->id);
});

test('login by username authenticates end to end', function (): void {
    Livewire\Livewire::test(SignIn::class)
        ->fillForm(['login' => 'sekarayu', 'password' => 'rahasia-panel-user'])
        ->set('data.agreement', true)
        ->set('data.remember', true)
        ->call('authenticate')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    expect(auth()->check())->toBeTrue()
        ->and(auth()->id())->toBe($this->user->id);
});

test('login by KTP number is rejected', function (): void {
    // Menutup jalur yang sebelumnya diiklankan UI. Nomor KTP user ini memang
    // ada di database, tapi tidak boleh bisa dipakai untuk masuk.
    Livewire\Livewire::test(SignIn::class)
        ->fillForm(['login' => '3201234567890001', 'password' => 'rahasia-panel-user'])
        ->set('data.agreement', true)
        ->set('data.remember', true)
        ->call('authenticate')
        ->assertHasFormErrors(['login']);

    expect(auth()->check())->toBeFalse();
});

test('the sign in field is labelled Email / Username', function (): void {
 // Label form dan perilaku harus sama. Sebelumnya label menjanjikan
    // lima jenis identitas yang tidak diproses.
    $page = (new ReflectionClass(SignIn::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod(SignIn::class, 'getEmailFormComponent');
    $method->setAccessible(true);

    $label = (string) $method->invoke($page)->getLabel();

    expect($label)->toBe('Email / Username')
        ->and($label)->not->toContain('KTP')
        ->and($label)->not->toContain('Passport')
        ->and($label)->not->toContain('NPWP')
        ->and($label)->not->toContain('SIM');
});
