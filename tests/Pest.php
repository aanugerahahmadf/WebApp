<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Pest\Expectation;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

/*
| Integration needs the same base as Feature: it makes real HTTP requests, so it
| needs the booted application and the database. The difference between the two is
| scope, not infrastructure -- Integration asserts on the middleware chain, Feature
| asserts on a single component. Do NOT add RefreshDatabase to Unit: a Unit test
| that needs a database belongs in Feature or Integration instead.
*/
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Integration');

pest()->extend(TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    /** @var Expectation $this */
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| Fungsi global dipakai lebih dari satu file test. Fungsi global di dalam file
| test akan fatal "Cannot redeclare function" begitu PHPUnit memuat kedua file
| itu dalam satu proses, jadi kumpulkan di sini.
|
*/

/**
 * Buat role yang dibutuhkan test, lengkap dengan nama guard-nya.
 *
 * Kenapa helper ini ada, dan kenapa `Role::firstOrCreate(['name' => ...])`
 * polos tidak boleh dipakai di test:
 *
 * Role admin hidup di guard `admin` (tabel `admins`), sedangkan role shopper
 * hidup di guard `web` (tabel `users`). `Spatie\Permission` mencari role
 * berdasarkan (nama, guard) dan `Model::assignRole()` mencari di guard model
 * itu sendiri -- jadi `Role::firstOrCreate(['name' => 'super_admin'])` membuat
 * role ber-guard `web`, lalu `Admin::factory()->superAdmin()` melempar
 * `RoleDoesNotExist: There is no role named 'super_admin' for guard 'admin'`.
 *
 * Gejalanya tidak mengarah ke cause yang benar: test gagal di baris setup,
 * sebelum satu pun assertion berjalan, dan pesannya menyiratkan role-nya hilang
 * -- padahal yang salah cuma guard-nya.
 *
 * @return array<string, \Spatie\Permission\Models\Role>
 */
if (! function_exists('seedRoles')) {
    /**
     * @param  list<string>  $adminRoles  nama role untuk guard `admin`
     * @param  list<string>  $webRoles    nama role untuk guard `web`
     * @return array<string, \Spatie\Permission\Models\Role>
     */
    function seedRoles(array $adminRoles = ['super_admin'], array $webRoles = ['customer']): array
    {
        $roles = [];

        foreach ($adminRoles as $name) {
            $roles[$name] = \Spatie\Permission\Models\Role::firstOrCreate([
                'name'       => $name,
                'guard_name' => 'admin',
            ]);
        }

        foreach ($webRoles as $name) {
            $roles[$name] = \Spatie\Permission\Models\Role::firstOrCreate([
                'name'       => $name,
                'guard_name' => 'web',
            ]);
        }

        return $roles;
    }
}

/**
 * Buat admin yang siap dipakai test: aktif, punya role `super_admin` ber-guard
 * `admin`, dan sudah punya identitas belanja tertaut di `admins.user_id`.
 *
 * Parameter `user_id` menentukan apakah admin bisa dipakai untuk fitur yang
 * butuh "staff" di sisi shopper -- `ChatStaff::adminUser()` mencari
 * `Admin::whereNotNull('user_id')`, jadi admin tanpa identitas itu akan
 * terlihat "belum dikonfigurasi".
 *
 * @param  array<string, mixed>  $attributes
 */
if (! function_exists('makeAdminWithStorefrontIdentity')) {
    function makeAdminWithStorefrontIdentity(array $attributes = []): \App\Models\Admin\Admin
    {
        seedRoles();

        $admin = \App\Models\Admin\Admin::factory()->create(
            array_merge(['active_status' => true], $attributes)
        );

        $admin->assignRole('super_admin');

        if ($admin->user_id === null) {
            $admin->forceFill([
                'user_id' => \App\Models\User\User::factory()->create()->id,
            ])->save();
        }

        return $admin->fresh();
    }
}

/**
 * Kode TOTP yang sah untuk secret tertentu, dihitung dari nol -- bukan string tebakan.
 *
 * Ditaruh di sini (bukan di file test) karena dipakai lebih dari satu test file.
 * Fungsi global di file test akan fatal "Cannot redeclare function" begitu
 * PHPUnit memuat kedua file itu dalam satu proses.
 */
if (! function_exists('currentTotp')) {
    function currentTotp(string $secret): string
    {
        return (new \PragmaRX\Google2FA\Google2FA)->getCurrentOtp($secret);
    }
}

/**
 * Pasangkan kode CAPTCHA dengan session, lalu sisipkan ke field form Livewire.
 *
 * Kenapa helper ini perlu ada, dan kenapa posisinya di sini:
 *
 * 1. `SignIn` menempelkan `CaptchaField` yang membawa rule
 *    `MarcoGermani87\FilamentCaptcha\Rules\Captcha`. Rule itu membandingkan nilai
 *    yang dikirim dengan `session('filament_captcha_code')`. Tanpa endian yang
 *    cocok, SETIAP test yang memanggil `authenticate()` gagal dengan
 *    `captcha_invalid` -- termasuk test yang sama sekali tidak berhubungan dengan
 *    captcha, seperti "password salah ditolak di field login".
 *
 * 2. Urutan itu penting dan tidak intuitif. `CaptchaField::getImage()` menulis
 *    session dari dalam `render()`, dan `render()` jalan pada SETIAP request
 *    Livewire. Jadi:
 *
 *      $component->set('data.captcha', $kode);   // menulis form, LALU render
 *      // render menimpa session dengan kode BARU -> $kode sudah basi
 *
 *    Sebaliknya, kode yang dibaca dari session setelah `set()` juga sudah
 *    basi karena ada request lagi di antaranya. Keduanya harus disamakan TANPA
 *    ada request Livewire di antaranya -- makanya session ditulis terakhir,
 *    tepat sebelum `call()`.
 *
 * 3. Menebak kode yang "kelihatan valid" tidak akan berhasil: `Captcha`
 *    membandingkan dengan session, bukan dengan format atau checksum. Kode
 *    tebakan selalu ditolak, dan karena pesan errornya sama untuk semua nilai,
 *    test tidak bisa membedakan "kode salah" dari "sesi basi".
 *
 * Ditaruh di `Pest.php` karena dipakai lebih dari satu file test.
 */
if (! function_exists('fillCaptcha')) {
    /**
     * @param  \Livewire\Features\SupportTesting\Testable  $component
     * @return \Livewire\Features\SupportTesting\Testable
     */
    function fillCaptcha($component, ?string $code = null): object
    {
        // Panjang harus sama dengan config plugin; `Captcha` juga memeriksa
        // panjangnya secara terpisah dari pencocokan nilai.
        $code ??= 'aB3xY';

        $component->set('data.captcha', $code);

        // Session ditulis SESUDAH set() -- lihat catatan (2) di atas.
        session(['filament_captcha_code' => $code]);

        return $component;
    }
}

/*
|--------------------------------------------------------------------------
| Pembaca teks PDF
|--------------------------------------------------------------------------
|
| Dompdf menulis tiap glyph sebagai 2 byte: byte glyph lalu byte tinggi.
| Byte tinggi 0x00 ditulis sebagai 0x20 (spasi) supaya aman di dalam
| literal string PDF. Jadi:
|
|   - satu spasi  = byte tinggi glyph (padding)
|   - dua spasi   = glyph kosong + glyph spasi -> SPASI ASLI
|
| Kalau semua spasi dibuang, "Formulir Laporan #1" jadi
| "FormulirLaporan#1" -- assertion ke teks yang wajar jadi mustahil. Jadi
| spasi asli dipulihkan dulu, baru padding dibuang.
|
| Ditaruh di `Pest.php` karena dipakai lebih dari satu file test, dan
| fungsi global PHP tidak boleh namanya sama dalam satu proses.
 */
if (! function_exists('pdfTextRows')) {
    /**
     * Semua baris teks di dalam file PDF.
     *
     * @return list<array{0: float, 1: float, 2: float, 3: string}> [x, y, ukuranFont, teks]
     */
    function pdfTextRows(string $pdf): array
    {
        $rows = [];

        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $streams);

        foreach ($streams[1] as $raw) {
            $stream = @gzuncompress($raw);

            if ($stream === false) {
                $stream = $raw;
            }

            if (! str_contains($stream, 'BT')) {
                continue;
            }

            preg_match_all('/BT\s+([-\d.]+)\s+([-\d.]+)\s+Td\s+\/F\d+\s+([\d.]+)\s+Tf\s+\[\((.*?)\)\]\s*TJ/s', $stream, $matches, PREG_SET_ORDER);

            foreach ($matches as $set) {
                $text = $set[4];

                // Escape dulu, baru rapikan spasi.
                $text = preg_replace_callback('/\\\\([0-7]{1,3})/', fn ($x) => chr(octdec($x[1])), $text) ?? $text;
                $text = str_replace(["\0", ' '], ' ', $text);
                // Dua spasi = spasi asli.
                $text = str_replace('  ', "\x01", $text);
                // Sisa spasi = padding byte tinggi.
                $text = str_replace(' ', '', $text);
                $text = str_replace("\x01", ' ', $text);

                if (trim($text) === '') {
                    continue;
                }

                $rows[] = [(float) $set[1], (float) $set[2], (float) $set[3], $text];
            }
        }

        return $rows;
    }
}
