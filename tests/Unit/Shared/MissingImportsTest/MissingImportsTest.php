<?php

namespace Tests\Unit\Shared\MissingImportsTest;

use PHPUnit\Framework\TestCase;

/**
 * Menangkap kelas milik project yang dipanggil TANPA import yang benar.
 *
 * Bug ini sudah terjadi beberapa kali di repo ini. Contoh paling jelas ada di
 * `HistoryResource` (versi User dan Welcome): penataan ulang `Product` ->
 * `Catalog` menghapus satu baris `use`, lalu `CatalogResource::getUrl()` di
 * dalam `table()` tidak bisa di-resolve.
 *
 * Gejalanya muncul JAUH dari sumbernya:
 *
 * - PHP tidak melempar error saat file di-parse. Ia baru gagal saat class itu
 *   benar-benar dipanggil, yaitu ketika ada user menekan tombol di halaman itu.
 * - Pesannya `Class "App\Filament\User\Resources\HistoryResource\CatalogResource"
 *   not found` -- nama yang terlihat menunjuk ke resource Histori, bukan ke
 *   import yang hilang.
 * - Di test, itu muncul sebagai `ViewException` dari dalam Filament, beberapa
 *   lapis dari tempat yang sebenarnya rusak.
 *
 * Karena itu penjaganya harus statis: scan seluruh `app/`, bukan bergantung
 * pada test yang kebetulan memanggil jalurnya.
 *
 * DETAIL YANG MEMBUAT TEST INI TIDAK BERGUNA JIKA DILEWATI:
 *
 * 1. **Komentar harus dibuang lebih dulu.** Tanpa itu, setiap referensi di
 *    dalam docblock -- dan repo ini JAUH lebih banyak docblock daripada kode --
 *    dilaporkan sebagai pelanggaran. `EnsureProfileComplete` misalnya menulis
 *    "jadi `User::hasRole('super_admin')` selalu false" di komentar, dan tanpa
 *    pembuangan komentar file itu dilaporkan rusak padahal flawless. Itu
 *    penyebabnya detector seperti ini langsung diabaikan.
 *
 * 2. **Class harus dicari di seluruh `app/`, bukan di `app/<Name>.php`.**
 *    `CatalogResource` hidup di `app/Filament/User/Resources/CatalogResource/
 *    CatalogResource.php`. Kalau dicek `app/CatalogResource.php`, hasilnya
 *    selalu false dan tidak ada yang pernah dilaporkan -- test hijau tanpa
 *    pernah mengecek apa pun.
 *
 * 3. **"Bisa di-resolve" hanya boleh berarti: ter-import, atau class di folder
 *    yang SAMA.** Folder induk tidak boleh ikut dihitung; PHP tidak
 *    me-resolve ke sana.
 *
 * File ini berada di `tests/Unit` dan memakai `PHPUnit\Framework\TestCase`
 * langsung: tidak butuh database, tidak butuh container Laravel, dan harus
 * bisa jalan sebelum aplikasi di-boot.
 */
final class MissingImportsTest extends TestCase
{
    /**
     * Nama yang boleh muncul tanpa import: class bawaan PHP dan keyword.
     *
     * @var list<string>
     */
    private const BUILTIN = [
        'self', 'static', 'parent', 'PHP_VERSION_ID', 'PHP_EOL', 'PHP_INT_MAX',
        'String', 'Array', 'Object', 'Int', 'Float', 'Bool', 'Null', 'Void',
        'Mixed', 'Never', 'Iterable', 'Callable', 'False', 'True',
        'Exception', 'Error', 'TypeError', 'ValueError', 'ArgumentCountError',
        'ArithmeticError', 'DivisionByZeroError', 'JsonException', 'LogicException',
        'RuntimeException', 'InvalidArgumentException', 'OutOfBoundsException',
        'Throwable', 'Traversable', 'Countable', 'Iterator', 'IteratorAggregate',
        'ArrayAccess', 'JsonSerializable', 'Stringable',
        'Closure', 'Generator', 'Fiber',
        'DateTime', 'DateTimeImmutable', 'DateInterval', 'DateTimeZone', 'DateTimeInterface',
        'Attribute', 'Deprecated', 'Override', 'AllowDynamicProperties',
        'SensitiveParameter', 'ReturnTypeWillChange', 'WeakMap', 'WeakReference',
        'UnitEnum', 'BackedEnum', 'Enum',
    ];

    public function test_every_project_class_used_in_app_is_imported(): void
    {
        $root = $this->projectRoot();
        $appDir = $root . DIRECTORY_SEPARATOR . 'app';

        $this->assertDirectoryExists($appDir, 'Folder app/ tidak ditemukan.');

        // Nama class yang ADA di project, untuk semua kedalaman folder.
        $projectClasses = $this->classNamesUnder($appDir);

        $this->assertNotEmpty(
            $projectClasses,
            'Tidak ada class yang ditemukan di app/ -- pemeriksaannya salah.'
        );

        $offenders = [];

        foreach ($this->phpFilesIn($appDir) as $file) {
            foreach ($this->unresolvedCalls($file, $projectClasses) as $problem) {
                $offenders[] = sprintf(
                    '%s baris %d memanggil %s:: tanpa import',
                    $this->relative($root, $file),
                    $problem['line'],
                    $problem['name']
                );
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Kelas milik project dipanggil tanpa import. PHP tidak akan gagal\n".
            "sampai class itu benar-benar dipanggil, jadi errornya muncul jauh dari\n".
            "sumbernya -- biasanya sebagai ViewException dari dalam Filament:\n\n".
            implode("\n", $offenders)
        );
    }

    /**
     * Kumpulkan pemanggilan yang tidak bisa di-resolve di satu file.
     *
     * @param  array<string, true>  $projectClasses
     * @return list<array{name: string, line: int}>
     */
    private function unresolvedCalls(string $path, array $projectClasses): array
    {
        $tokens = token_get_all((string) file_get_contents($path));

        $resolvable = $this->resolvableNames($path, $tokens);
        $found = [];

        foreach ($this->codeLines($tokens) as $codeLine) {
            if (! preg_match_all(
                '/(?<![\\\\A-Za-z0-9_$>\-\\\\])([A-Z][A-Za-z0-9_]*)\s*::/',
                $codeLine['text'],
                $matches
            )) {
                continue;
            }

            foreach ($matches[1] as $name) {
                if (isset($resolvable[$name]) || in_array($name, self::BUILTIN, true)) {
                    continue;
                }

                if (! isset($projectClasses[$name])) {
                    continue;
                }

                $found[] = ['name' => $name, 'line' => $codeLine['line']];
            }
        }

        return $found;
    }

    /**
     * Nama yang bisa dipanggil tanpa import di file ini.
     *
     * @param  array<int, array{int, string, int}|string>  $tokens
     * @return array<string, true>
     */
    private function resolvableNames(string $path, array $tokens): array
    {
        $names = [];

        // Class di folder yang SAMA dengan file ini.
        foreach (glob(dirname($path) . DIRECTORY_SEPARATOR . '*.php') ?: [] as $sibling) {
            $names[basename($sibling, '.php')] = true;
        }

        // Import, termasuk alias.
        foreach ($tokens as $token) {
            if (! is_array($token) || $token[0] !== T_USE) {
                continue;
            }

            $statement = '';

            for ($i = 0; $i < count($tokens); $i++) {
                if ($tokens[$i] === $token) {
                    // Kumpulkan sampai `;`.
                    for ($j = $i; $j < count($tokens); $j++) {
                        if ($tokens[$j] === ';') {
                            break 2;
                        }

                        $statement .= is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
                    }
                }
            }

            // Hanya import class/const, bukan `use function` atau trait inline.
            if (str_contains($statement, '(')) {
                continue;
            }

            $parts = preg_split('/\s+as\s+/i', trim($statement)) ?: [];

            $fqn = trim($parts[0]);
            $alias = isset($parts[1]) ? trim($parts[1]) : substr((string) strrchr('\\' . $fqn, '\\'), 1);

            if ($alias !== '') {
                $names[$alias] = true;
            }
        }

        return $names;
    }

    /**
     * Token -> daftar baris kode, dengan nomor baris ASLI dan tanpa komentar.
     *
     * Comment dan docblock dibuang, dan nomor baris tetap yang benar supaya
     * pesan kegagalan bisa langsung membuka file di baris yang salah.
     *
     * Spasi dari token whitespace DIPERTAHANKAN (termasuk yang berisi newline),
     * karena tanpa itu `Foo ::` bisa berubah bentuk dan ditulis sebagai
     * panggilan statis padahal di sumbernya berbeda.
     *
     * @param  array<int, array{int, string, int}|string>  $tokens
     * @return list<array{text: string, line: int}>
     */
    private function codeLines(array $tokens): array
    {
        $lines = [''];
        $current = 0;

        foreach ($tokens as $token) {
            if (is_array($token) && in_array(
                $token[0],
                [T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE],
                true
            )) {
                // String ikut dibuang: `Foo::` di dalam pesan atau nama route
                // bukan pemanggilan statis.
                continue;
            }

            $text = is_array($token) ? $token[1] : $token;

            // Token whitespace bisa jadi beberapa baris sekaligus.
            foreach (explode("\n", $text) as $i => $piece) {
                if ($i > 0) {
                    $lines[] = '';
                    $current++;
                }

                $lines[$current] .= $piece;
            }
        }

        $result = [];

        foreach ($lines as $i => $text) {
            $result[] = ['text' => $text, 'line' => $i + 1];
        }

        return $result;
    }

    /**
     * @return array<string, true>
     */
    private function classNamesUnder(string $dir): array
    {
        $names = [];

        foreach ($this->phpFilesIn($dir) as $file) {
            $names[basename($file, '.php')] = true;
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    private function phpFilesIn(string $dir): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * Akar proyek.
     *
     * `__DIR__` file ini adalah `tests/Unit/Shared/MissingImportsTest/`, jadi
     * naik empat tingkat: MissingImportsTest -> Shared -> Unit -> tests -> root.
     */
    private function projectRoot(): string
    {
        return dirname(__DIR__, 4);
    }

    private function relative(string $root, string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen($root) + 1));
    }
}
