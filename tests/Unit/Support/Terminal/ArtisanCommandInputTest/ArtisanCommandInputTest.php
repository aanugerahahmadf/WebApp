<?php

namespace Tests\Unit\Support\Terminal\ArtisanCommandInputTest;

use App\Support\Terminal\ArtisanCommandInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit test untuk App\Support\Terminal\ArtisanCommandInput.
 *
 * Kenapa file ini ada dan kenapa ia ada di Unit (bukan Feature):
 *
 * 1. `ArtisanCommandInput` adalah fungsi string murni: tidak menyentuh
 *    database, tidak butuh HTTP, tidak butuh container Laravel. Feature test
 *    untuk kelas sekecil ini jauh lebih mahal karena `RefreshDatabase`
 *    menjalankan `migrate:fresh` + seeder utuh hanya untuk memanggil fungsi
 *    yang selesai dalam mikrodetik. Test Feature yang memanggilnya
 *    (`TerminalPageTest`) tetap ada -- ia menguji perilaku yang terlihat dari
 *    user -- tapi cakupan lengkap harus ada di sini supaya regresi pada
 *    regex terdeteksi dalam hitungan milidetik, bukan setelah satu menit setup.
 *
 * 2. Bug yang dijaga adalah bug NYATA yang sudah terjadi di produksi:
 *    admin mengetik `php artisan migrate` persis seperti di shell, lalu
 *    `Artisan::call()` menafsirkan `php` sebagai nama command dan melempar
 *    `The command "php" does not exist.` Karena `Artisan::call()` sudah
 *    berjalan di dalam konteks artisan, prefiks shell itu harus dibuang --
 *    dan normalisasi harus happening SEBELUM cek blacklist, supaya
 *    `php artisan tinker` tidak bisa melewati daftar blokir.
 *
 * 3. Normalisasi berjalan berulang (loop) karena prefiks bisadoUBLE
 *    (`php artisan artisan migrate`). Loop tanpa batas akan hang kalau ada
 *    pola yang cocok dengan string kosong, jadi ada test yang mengunci
 *    konvergensi: memanggilan normalize pada hasil normalize harus idempoten.
 *
 * 4. Argumen harus TIDAK dirusak. Ini yang sering salah di implementasi
 *    serupa: kalau pem.parse memecah string lalu merakit ulang, tanda kutip
 *    dan spasi di dalam nilai argumen ikut berubah. Karena itu kelas ini
 *    sengaja hanya membersihkan lapisan luar dan meneruskan sisa string
 *    apa adanya ke `Artisan::call()`. Test di bawah mengunci nilai yang
 *    mengandung spasi dan tanda kutip.
 *
 * Konvensi repo: satu file test = satu folder bernama sama, namespace PSR-4
 * cocok dengan lokasi (`tests/Unit/Support/Terminal/...`).
 */
final class ArtisanCommandInputTest extends TestCase
{
    /* ───────────────── Prefix dibuang ───────────────── */

    /**
     * Bentuk input yang paling sering diketik admin di shell, dipetakan ke
     * hasil yang harus diteruskan ke Artisan.
     *
     * Semua varian di sini adalah "salah ketik yang sangat wajar": admin
     * sudah terbiasa dengan terminal sungguhan, jadi `php artisan` ikut
     * terketik. Menolak input seperti ini dengan pesan error tidak membantu
     * -- keduanya perintah yang sama.
     */
    #[DataProvider('prefixIsStrippedProvider')]
    public function test_it_strips_shell_prefixes(string $input, string $expected): void
    {
        $this->assertSame($expected, ArtisanCommandInput::normalize($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function prefixIsStrippedProvider(): array
    {
        return [
            // Bentuk paling umum: interpreter + file artisan.
            'php artisan + command'          => ['php artisan migrate', 'migrate'],
            'php8.3 artisan + command'       => ['php8.3 artisan migrate', 'migrate'],
            'php.exe artisan + command'      => ['php.exe artisan migrate', 'migrate'],
            'php10 artisan + command'        => ['php10 artisan migrate', 'migrate'],

            // Path file artisan, gaya Unix dan Windows.
            'bare artisan'                   => ['artisan migrate', 'migrate'],
            './artisan'                      => ['./artisan migrate', 'migrate'],
            '.\artisan (backslash)'          => ['.\artisan migrate', 'migrate'],
            '/artisan (absolute-ish)'        => ['/artisan migrate', 'migrate'],
            '.\artisan.exe-less bat'         => ['.\artisan.bat migrate', 'migrate'],

            // Prefiks dobel harus dibuang sampai habis, bukan satu kali.
            'php artisan artisan'            => ['php artisan artisan migrate', 'migrate'],
            'artisan artisan'                => ['artisan artisan migrate', 'migrate'],

            // Prefix SENDIRI tanpa command = input kosong, bukan command
            // bernama "php" atau "artisan".
            'hanya prefiks php artisan'      => ['php artisan', ''],
            'hanya prefiks artisan'          => ['artisan', ''],
            'hanya prefiks ./artisan'        => ['./artisan', ''],
            'hanya prefiks dobel'            => ['php artisan artisan', ''],
        ];
    }

    /* ───────────────── Input tanpa prefix ───────────────── */

    /**
     * Command yang sudah benar HARUS tetap utuh. Kalau ini gagal, berarti
     * normalisasi ikut memakan bagian command -- mis. `artisan:` dianggap
     * prefiks -- yang akan membuat terminal memperbaiki input yang sebenarnya
     * sudah benar.
     */
    #[DataProvider('untouchedProvider')]
    public function test_it_leaves_clean_commands_untouched(string $input): void
    {
        $this->assertSame($input, ArtisanCommandInput::normalize($input));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function untouchedProvider(): array
    {
        return [
            'migrate'                => ['migrate'],
            'migrate:status'         => ['migrate:status'],
            'migrate:fresh --seed'   => ['migrate:fresh --seed'],
            'list --raw'             => ['list --raw'],
            'route:list --compact'   => ['route:list --compact'],
            'storage:link'           => ['storage:link'],
            'queue:retry all'        => ['queue:retry all'],
            'env:encrypt'            => ['env:encrypt'],
            'db:show'                => ['db:show'],
            // Command yang namanya mirip prefiks tapi bukan prefiks.
            'artisan:foo'            => ['artisan:foo'],
            'phpinfo'                => ['phpinfo'],
        ];
    }

    /* ───────────────── Argumen tidak dirusak ───────────────── */

    /**
     * Ini test yang paling mudah ditulis salah.
     *
     * Implementasi yang memecah string lalu merakit ulang akan merusak nilai
     * argumen yang mengandung spasi atau tanda kutip. Karena `Artisan::call()`
     * menerima string mentah, kelas ini HARUS meneruskan sisanya tanpa
     * penyusunan ulang.
     */
    #[DataProvider('argumentIsPreservedProvider')]
    public function test_it_preserves_arguments_exactly(string $input, string $expected): void
    {
        $this->assertSame($expected, ArtisanCommandInput::normalize($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function argumentIsPreservedProvider(): array
    {
        return [
            'flag sederhana' => [
                'php artisan migrate:status --database=mysql',
                'migrate:status --database=mysql',
            ],
            'nilai berkutip ganda' => [
                'php artisan migrate --path="database/migrations"',
                'migrate --path="database/migrations"',
            ],
            'nilai berkutip tunggal' => [
                "php artisan migrate --path='database/migrations'",
                "migrate --path='database/migrations'",
            ],
            'dua flag' => [
                'php artisan migrate:rollback --step=3 --database=sqlite',
                'migrate:rollback --step=3 --database=sqlite',
            ],
            'argumen tanpa nilai' => [
                'php artisan queue:retry all',
                'queue:retry all',
            ],
        ];
    }

    /* ───────────────── Whitespace ───────────────── */

    /**
     * Admin menyalin dari terminal, jadi input sering brings newline/tab
     * dan spasi ganda. Spasi berlebih dirapatkan, tapi spasi di DALAM
     * nilai argumen tidak boleh ikut dirapatkan.
     */
    #[DataProvider('whitespaceProvider')]
    public function test_it_collapses_excess_whitespace(string $input, string $expected): void
    {
        $this->assertSame($expected, ArtisanCommandInput::normalize($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function whitespaceProvider(): array
    {
        return [
            'spasi di tepi'          => ['   about   ', 'about'],
            'spasi ganda di tengah'  => ['php   artisan    about', 'about'],
            'tab sebagai separator'  => ["php\tartisan\tabout", 'about'],
            'newline dari copy-paste'=> ["php artisan\nabout", 'about'],
            'campuran'               => ["  php \t artisan \n  about  ", 'about'],
            // Spasi di DALAM nilai argumen harus utuh -- ini yang membedakan
            // "rapatkan whitespace" dari "pecah string per token".
            'spasi dalam kutip'      => ['migrate --name="my migration"', 'migrate --name="my migration"'],
        ];
    }

    /* ───────────────── Input kosong ───────────────── */

    #[DataProvider('emptyProvider')]
    public function test_it_returns_empty_for_blank_input(string $input): void
    {
        $this->assertSame('', ArtisanCommandInput::normalize($input));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function emptyProvider(): array
    {
        return [
            'string kosong'   => [''],
            'spasi saja'      => ['   '],
            'tab saja'        => ["\t"],
            'newline saja'    => ["\n"],
            'campuran putih'  => ["  \t\n  "],
        ];
    }

    /* ───────────────── Idempotensi / konvergensi ───────────────── */

    /**
     * `normalize()` dipanggil loop_INTERNAL untuk membuang prefiks dobel.
     * Kalau ada pola yang cocok dengan string kosong, loop itu tidak akan
     * pernah berhenti -- command pertama yang diketik admin akan membuat
     * request web hang. Test ini mengunci bahwa operasi ini konvergen.
     */
    #[DataProvider('idempotencyProvider')]
    public function test_normalization_is_idempotent(string $input): void
    {
        $once = ArtisanCommandInput::normalize($input);
        $twice = ArtisanCommandInput::normalize($once);

        $this->assertSame($once, $twice, 'normalize() harus idempoten.');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function idempotencyProvider(): array
    {
        return [
            'clean'              => ['migrate'],
            'php artisan'        => ['php artisan migrate'],
            'double prefix'      => ['php artisan artisan artisan migrate'],
            'windows path'       => ['.\artisan migrate'],
            'prefix only'        => ['php artisan'],
            'blank'              => [''],
        ];
    }

    /* ───────────────── commandName() ───────────────── */

    /**
     * `commandName()` adalah input untuk cek blacklist. Kalau dia mengembalikan
     * `php` untuk `php artisan tinker`, maka `tinker` lolos dari daftar blokir.
     * Test di bawah mengunci keduanya: apa yang dikembalikan, dan bahwa
     * nama command yang diblokir memang terdeteksi setelah normalisasi.
     */
    #[DataProvider('commandNameProvider')]
    public function test_command_name_extraction(string $input, string $expected): void
    {
        $this->assertSame($expected, ArtisanCommandInput::commandName($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function commandNameProvider(): array
    {
        return [
            'tanpa argumen'              => ['migrate', 'migrate'],
            'dengan flag'                => ['migrate --force', 'migrate'],
            'dengan flag bernilai'       => ['migrate:status --database=mysql', 'migrate:status'],
            'prefiks php artisan'        => ['php artisan tinker', 'tinker'],
            'prefiks artisan'            => ['artisan serve', 'serve'],
            'prefiks windows'            => ['.\artisan env:encrypt', 'env:encrypt'],
            'spasi berlebih'             => ['  php   artisan   down  ', 'down'],
            'argumen tanpa nilai'        => ['queue:retry all', 'queue:retry'],
        ];
    }

    /**
     * Blacklist di TerminalPage memakai `in_array(commandName(...), BLOCKED, true)`.
     * Test ini menutup celah yang_unit test di atas tidak bisa lihat sendiri:
     * dia memastikan hasil `commandName()` benar-benar match daftar blokir,
     * termasuk ketika admin mengetik dengan prefiks.
     *
     * Daftar ini MIRROR dari `TerminalPage::BLOCKED_COMMANDS`. Kalau salah satu
     * berubah di produksi dan test ini tidak ikut diubah, test ini harus gagal
     * -- itu gunanya: daftar blokir dan testnya harus bergerak bersama.
     */
    #[DataProvider('blockedCommandProvider')]
    public function test_blocked_commands_are_detected_despite_prefix(string $input, string $expectedName): void
    {
        $blocked = [
            'tinker',
            'serve',
            'key:generate',
            'down',
            'up',
            'env',
            'env:encrypt',
            'env:decrypt',
        ];

        $detected = ArtisanCommandInput::commandName($input);

        $this->assertSame($expectedName, $detected);
        $this->assertContains(
            $detected,
            $blocked,
            'Perintah yang diblokir harus terdeteksi meski ditulis dengan prefiks shell.',
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function blockedCommandProvider(): array
    {
        return [
            'tinker polos'          => ['tinker', 'tinker'],
            'tinker prefiks'        => ['php artisan tinker', 'tinker'],
            'serve polos'           => ['serve', 'serve'],
            'serve prefiks'         => ['artisan serve', 'serve'],
            'key:generate prefiks'  => ['php artisan key:generate', 'key:generate'],
            'down prefiks'          => ['php artisan down', 'down'],
            'up prefiks'            => ['php artisan up', 'up'],
            'env prefiks'           => ['php artisan env', 'env'],
            'env:encrypt prefiks'   => ['php artisan env:encrypt', 'env:encrypt'],
            'env:decrypt prefiks'   => ['php artisan env:decrypt', 'env:decrypt'],
        ];
    }

    /**
     * Perintah yang TIDAK diblokir tidak boleh ikut tertangkap blacklist.
     * Tanpa test ini, penambahan isi blacklist yang terlalu longgar (mis.
     * menambah `up` padahal `up` bukan command berbahaya) tidak akan
     * terdeteksi.
     */
    #[DataProvider('nonBlockedCommandProvider')]
    public function test_allowed_commands_are_not_confused_with_blocked_ones(string $input): void
    {
        $blocked = [
            'tinker',
            'serve',
            'key:generate',
            'down',
            'up',
            'env',
            'env:encrypt',
            'env:decrypt',
        ];

        $this->assertNotContains(ArtisanCommandInput::commandName($input), $blocked);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonBlockedCommandProvider(): array
    {
        return [
            'migrate'        => ['php artisan migrate'],
            'migrate:status' => ['migrate:status'],
            'about'          => ['php artisan about'],
            'optimize:clear' => ['php artisan optimize:clear'],
            'list'           => ['artisan list'],
            'route:list'     => ['php artisan route:list'],
            // Nama yang mengandung string terlarang tapi bukan command itu.
            'environment'    => ['php artisan environment'],
            'updater'        => ['php artisan updater'],
        ];
    }

    /* ───────────────── Bentuk kelas ───────────────── */

    /**
     * Fungsi static murni: tidak boleh menyimpan state antar-panggilan.
     * Kalau suatu saat ada cache statis (memoization) yang ditambahkan,
     * pemanggil di web bisa menyajikannya ke request yang berbeda --
     * jadi ini dikunci di sini.
     */
    public function test_normalization_has_no_state_between_calls(): void
    {
        $first = ArtisanCommandInput::normalize('php artisan migrate');

        // Input yang tidak berkaitan di antara panggilan.
        ArtisanCommandInput::normalize('php artisan tinker');
        ArtisanCommandInput::normalize('artisan serve');

        $this->assertSame($first, ArtisanCommandInput::normalize('php artisan migrate'));
    }
}