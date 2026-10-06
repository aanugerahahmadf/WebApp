<?php

namespace Tests\Unit\Support\PasswordPolicy;

use App\Support\PasswordPolicy\PasswordPolicy;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;
use ReflectionClass;

/**
 * PasswordPolicy — mengunci aturan kata sandi agar tidak bisa dilonggarkan
 * tanpa test yang gagal.
 *
 * KEADAAN SEBELUM KELAS INI ADA
 * -----------------------------
 * Aturan tersebar di 17 titik dan tidak konsisten. Empat di antaranya masih
 * `min:8`:
 *
 *     api/Admin/UserController          min:8
 *     Filament/User/.../PasswordSecurityPage   min:8
 *     Filament/Welcome/.../PasswordSecurityPage min:8
 *     Console/Commands/AppInstall       min 8
 *
 * Dan tiga `EditPasswordComponent` memakai `Password::default()` yang tidak
 * pernah di-set, sehingga jatuh ke default Laravel (min 8). Akibatnya
 * "ubah kata sandi" menerima kata sandi 8 karakter sementara pendaftaran
 * exigir 12 — di aplikasi yang sama.
 *
 * YANG DIJAMIN TEST INI
 * ---------------------
 *  1. Kebijakan tunggal berlaku untuk kelima sumber aturan.
 *  2. `Password::default()` global juga memakai kebijakan yang sama, sehingga
 *     komponen Filament yang tidak disebut secara eksplisit ikut ketat.
 *  3. Kata sandi di bawah batas ditolak, `@Superadmin123` diterima.
 *
 * Test ini murni verifikasi aturan -- tidak menyentuh database, jadi tidak
 * perlu RefreshDatabase dan jalan jauh lebih cepat.
 */

test('the minimum length is 12 characters', function (): void {
    expect(PasswordPolicy::MIN_LENGTH)->toBe(12);
});

test('a compliant password has no violations', function (string $password): void {
    expect(PasswordPolicy::violations($password))->toBe([])
        ->and(PasswordPolicy::isValid($password))->toBeTrue();
})->with([
    'contoh dari spesifikasi' => '@Superadmin123',
    'tepat di batas 12' => '@Customer123',
    'memakai kata Indonesia' => 'Bunga@Mawar99',
    'panjang' => 'K4t4Sandi!2024',
    'simbol di depan' => '!Sandikata11',
    'simbol di belakang' => 'Sandikata11!',
    'tepat 12, semua jenis huruf' => 'AaBbCcDdEe1!',
    'tepat 12, simbol di tengah' => 'Pa$sSandika1',
]);

test('a weak password is rejected with a reason', function (?string $password, string $expectedFragment): void {
    $violations = PasswordPolicy::violations($password);

    expect($violations)->not->toBe([])
        ->and(implode(' ', $violations))->toContain($expectedFragment);
})->with([
    'kosong' => ['', 'wajib diisi'],
    'null' => [null, 'wajib diisi'],
    'terlalu pendek' => ['Short1!', 'minimal 12 karakter'],
    'tepat 11 karakter' => ['@Superadmi1', 'minimal 12 karakter'],
    'tanpa huruf besar' => ['@superadmin123', 'huruf besar'],
    'tanpa huruf kecil' => ['@SUPERADMIN123', 'huruf kecil'],
    'tanpa angka' => ['@Superadmin!!!', 'angka'],
    'tanpa simbol' => ['Superadmin1234', 'simbol'],
    'kata umum' => ['password123456', 'huruf besar'],
    'kelas lama 8 karakter' => ['password123', 'minimal 12 karakter'],
]);

test('all five rule sources behave identically', function (string $password): void {
    $passes = fn (array|string|object $rules): bool => Validator::make(
        ['p' => $password, 'p_confirmation' => $password],
        ['p' => $rules]
    )->passes();

    $sources = [
        'rules()' => PasswordPolicy::rules(),
        'confirmedRules()' => PasswordPolicy::confirmedRules(),
        'optionalRules()' => PasswordPolicy::optionalRules(),
        'filamentRules()' => [PasswordPolicy::filamentRules()],
        'Password::default()' => [Password::default()],
    ];

    $verdicts = [];
    foreach ($sources as $name => $rules) {
        $verdicts[$name] = $passes($rules);
    }

    // Kalau sumber mana saja berbeda, produk ini tidak konsisten -- dan itu
    // persis bug yang kelas ini dibuat untuk hilangkan.
    expect(array_unique($verdicts))->toHaveCount(1, 'sumber aturan tidak konsisten: '.json_encode($verdicts));
})->with([
    '@Superadmin123',
    'Bunga@Mawar99',
    'password',
    'password123',
    'Short1!',
    'abcdefghijkl',
    'ABCDEFGHIJKL',
    '12345678901234',
    '!!!!!!!!!!!!',
]);

test('optional rules do not make the field required', function (): void {
    // Field opsional harus tetap opsional ketika tidak disentuh.
    $absent = ['full_name' => 'Tanpa Password'];

    $optional = Validator::make($absent, [
        'full_name' => 'required|string',
        'password' => ['sometimes', ...PasswordPolicy::optionalRules()],
    ]);

    expect($optional->passes())->toBeTrue();

    // Ketika field-nya ADA tapi kosong, optionalRules() tidak menahan nilai
    // kosong -- tidak ada "required" di dalamnya. Itu disengaja: aturan
    // Cek kekuatan tetap berlaku kalau ada isinya, dan "wajib kirim
    // password" adalah keputusan bentuk form, bukan kebijakan kekuatan.
    $presentButEmpty = Validator::make(
        ['full_name' => 'Ada Password', 'password' => ''],
        ['password' => ['sometimes', ...PasswordPolicy::optionalRules()]]
    );

    expect($presentButEmpty->passes())->toBeTrue();

    // Tapi kalau isinya lemah, tetap ditolak.
    $presentButWeak = Validator::make(
        ['password' => 'password123'],
        ['password' => ['sometimes', ...PasswordPolicy::optionalRules()]]
    );

    expect($presentButWeak->passes())->toBeFalse();
});

test('required rules do reject an empty value', function (): void {
    // Kontras dengan test di atas: rules() punya "required", jadi field
    // wajib benar-benar wajib.
    $empty = Validator::make(
        ['password' => ''],
        ['password' => PasswordPolicy::rules()]
    );

    expect($empty->passes())->toBeFalse();
});

test('the global Password default is the policy, not the framework default', function (): void {
    // Kalau baris Password::defaults() di AppServiceProvider::boot() hilang,
    // test ini gagal -- dan itulah gunanya: halaman "Ubah Kata Sandi" akan
    // diam-diam menerima 8 karakter lagi.
    $passes = fn (string $pw): bool => Validator::make(
        ['p' => $pw, 'p_confirmation' => $pw],
        ['p' => Password::default()]
    )->passes();

    expect($passes('@Superadmin123'))->toBeTrue()
        ->and($passes('password123'))->toBeFalse();
});

test('the policy class is the single documented source', function (): void {
    // Kunci dokumentasi: kelas ini harus terus menyebut lokasi yang pernah
    // salah, supaya konteks penyimpulannya tidak hilang saat refactor.
    $source = file_get_contents((new ReflectionClass(PasswordPolicy::class))->getFileName());

    expect($source)->toContain('min:8')
        ->and($source)->toContain('EditPasswordComponent')
        ->and($source)->toContain('OtpResetPassword');
});
