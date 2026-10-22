<?php

namespace Tests\Feature\Admin\AdminSelectsAreSearchableTest;

use Tests\TestCase;

/**
 * SETIAP Select di panel admin harus bisa DICARI.
 *
 * Kenapa ini dijaga dengan test:
 *
 *   - Panel admin punya tabel besar (Katalog 500+, Order, User, Voucher).
 *     Select tanpa `->searchable()` memaksa orang menggulir daftar untuk
 *     menemukan satu nilai -- dan di daftar 500 baris itu praktis mustahil.
 *   - Filter tabel (SelectFilter) yang tidak searchable jauh lebih parah:
 *     filternya ada, tapi tidak ada cara mengetik.
 *
 * Test ini memindai BERKAS panel admin (bukan daftar manual), jadi Select
 * baru di halaman mana pun ikut terjaga tanpa perlu disentuh test ini.
 */
class AdminSelectsAreSearchableTest extends TestCase
{
    public function test_every_select_in_the_admin_panel_is_searchable(): void
    {
        $missing = [];

        foreach (self::adminPhpFiles() as $file) {
            $lines = file($file);
            $total = count($lines);

            for ($i = 0; $i < $total; $i++) {
                if (! preg_match('/(Select|SelectFilter)::make\(/', $lines[$i])) {
                    continue;
                }

                // Kumpulkan rantai komponennya: dari baris Select::make()
                // sampai baris yang menutup dengan `;`.
                $chain = '';
                $depth = substr_count($lines[$i], '(') - substr_count($lines[$i], ')');

                for ($j = $i; $j < $total; $j++) {
                    $chain .= $lines[$j];
                    $depth += substr_count($lines[$j], '(') - substr_count($lines[$j], ')');

                    if ($depth <= 0 && str_contains($lines[$j], ';')) {
                        break;
                    }
                }

                if (! str_contains($chain, '->searchable(')) {
                    $missing[] = str_replace(base_path().'/', '', $file).':'.($i + 1);
                }
            }
        }

        $this->assertSame([], $missing, 'Select di panel admin yang belum searchable: '.implode(', ', $missing));
    }

    /**
     * @return list<string>
     */
    private static function adminPhpFiles(): array
    {
        $root = base_path('app/Filament/Admin');

        if (! is_dir($root)) {
            return [];
        }

        $files = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
