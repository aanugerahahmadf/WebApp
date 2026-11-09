<?php

namespace App\Support\SqlExpression;

/**
 * Ekspresi SQL yang ekuivalen di MySQL, MariaDB, PostgreSQL, dan SQLite.
 *
 * `FIELD(id, 3, 1, 2)` hanya dikenal MySQL/MariaDB. PostgreSQL memakai
 * `array_position()` dan SQLite tidak punya keduanya, jadi urutan peringkat
 * hasil CBIR dibangun lewat `CASE`, yang dipahami keempat driver.
 */
class SqlExpression
{
    /**
     * Ekspresi ORDER BY yang menyusun $ids persis pada urutan yang diberikan.
     *
     * @param  array<int, int|string>  $ids
     */
    public static function orderByIds(array $ids, string $column = 'id'): string
    {
        if ($ids === []) {
            return $column;
        }

        $branches = [];

        foreach (array_values($ids) as $index => $id) {
            $branches[] = 'WHEN '.(int) $id.' THEN '.($index + 1);
        }

        return 'CASE '.$column.' '.implode(' ', $branches).' ELSE '.(count($ids) + 1).' END';
    }
}
