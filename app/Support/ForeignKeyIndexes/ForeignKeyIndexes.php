<?php

namespace App\Support\ForeignKeyIndexes;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drop index yang aman untuk rollback migrasi.
 *
 * InnoDB membutuhkan sebuah index yang berawalan sama dengan kolom foreign key.
 * Ketika index composite ditambahkan di kolom FK yang sama, MySQL memakai index
 * baru itu dan membuang index implisit buatan constraint. Index composite tidak
 * bisa di-drop lagi (errno 1553) kecuali ada index lain yang masih menopang FK.
 * Helper ini menyiapkan index pengganti sebelum index composite dilepas.
 */
class ForeignKeyIndexes
{
    /**
     * @param  list<string>  $columns
     */
    public static function dropIndex(string $table, array $columns, ?string $name = null): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $name ??= static::findIndexName($table, $columns);

        if ($name === null) {
            return;
        }

        static::keepForeignKeyIndexed($table, $columns, $name);

        Schema::table($table, function (Blueprint $blueprint) use ($name): void {
            $blueprint->dropIndex($name);
        });
    }

    /**
     * @param  list<string>  $columns
     */
    private static function findIndexName(string $table, array $columns): ?string
    {
        $index = collect(Schema::getIndexes($table))
            ->first(fn (array $value) => array_map('strtolower', $value['columns']) === array_map('strtolower', $columns));

        return $index['name'] ?? null;
    }

    /**
     * @param  list<string>  $columns
     */
    private static function keepForeignKeyIndexed(string $table, array $columns, string $name): void
    {
        // Perilaku index implisit FK hanya ada di MySQL/MariaDB; di PostgreSQL
        // tidak ada index yang perlu diselamatkan.
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $leading = $columns[0] ?? null;

        if ($leading === null || ! static::hasForeignKeyOn($table, $leading)) {
            return;
        }

        $coveredByOtherIndex = collect(Schema::getIndexes($table))
            ->reject(fn (array $index) => $index['name'] === $name)
            ->contains(fn (array $index) => ($index['columns'][0] ?? null) === $leading);

        if ($coveredByOtherIndex) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($leading): void {
            $blueprint->index($leading);
        });
    }

    private static function hasForeignKeyOn(string $table, string $column): bool
    {
        return collect(Schema::getForeignKeys($table))
            ->contains(fn (array $key) => in_array($column, $key['columns'] ?? [], true));
    }
}
