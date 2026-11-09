<?php

namespace App\Support\PostgresCaseInsensitiveCollation;

use Illuminate\Support\Facades\DB;

/**
 * Collate teks PostgreSQL agar setara dengan MySQL/MariaDB.
 *
 * Kolom teks MySQL di aplikasi ini memakai `utf8mb4_0900_ai_ci`: `=` dan `LIKE`
 * tidak peka huruf, dan unique constraint memperlakukan `A@Mail.COM` sama
 * dengan `a@mail.com`. Kolom teks PostgreSQL memakai collation default database
 * yang peka huruf, jadi pencarian katalog/order dan lookup login
 * `where('email', $input)` mengembalikan 0 baris tanpa error.
 *
 * Collation nondeterministic tidak boleh menjadi default collation database,
 * jadi dipasang per kolom. `und-u-ks-level2` (ICU secondary level) menormalkan
 * huruf besar-kecil DAN aksen, sama seperti `utf8mb4_0900_ai_ci`.
 */
class PostgresCaseInsensitiveCollation
{
    public const LOCALE = 'und-u-ks-level2';

    public static function collationName(): string
    {
        return 'wed_ci';
    }

    public static function isPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }

    /**
     * Skema tempat kolom aplikasi berada (search_path koneksi aktif).
     */
    public static function schema(): string
    {
        return (string) DB::selectOne('select current_schema() as s')->s;
    }

    /**
     * Nama collation ter-quote yang siap dipakai di SQL.
     */
    public static function qualifiedName(): string
    {
        return self::quoteIdentifier(self::schema()).'.'.self::quoteIdentifier(self::collationName());
    }

    public static function ensureCollation(): void
    {
        DB::statement(
            'create collation if not exists '.self::qualifiedName().
            " (provider = icu, locale = '".self::LOCALE."', deterministic = false)"
        );
    }

    public static function collationOid(): ?int
    {
        $row = DB::selectOne(
            'select c.oid
               from pg_collation c
               join pg_namespace n on n.oid = c.collnamespace
              where c.collname = ? and n.nspname = ?',
            [self::collationName(), self::schema()]
        );

        return $row === null ? null : (int) $row->oid;
    }

    /**
     * Kolom teks yang belum memakai collation ini.
     *
     * @return array<int, object{table: string, column: string, sql_type: string}>
     */
    public static function pendingColumns(): array
    {
        $oid = self::collationOid();

        if ($oid === null) {
            return [];
        }

        return DB::select(
            "select c.relname as table, a.attname as column, format_type(a.atttypid, a.atttypmod) as sql_type
               from pg_attribute a
               join pg_class c on c.oid = a.attrelid
               join pg_namespace n on n.oid = c.relnamespace
              where n.nspname = ?
                and c.relkind in ('r', 'p')
                and a.attnum > 0
                and not a.attisdropped
                and a.atttypid in ('text'::regtype, 'varchar'::regtype, 'bpchar'::regtype)
                and a.attcollation <> ?
              order by c.relname, a.attnum",
            [self::schema(), $oid]
        );
    }

    /**
     * Foreign key yang kolomnya bertipe teks.
     *
     * PostgreSQL menolak perubahan collation kalau salah satu sisi FK masih
     * memakai collation lain ("Key columns ... have incompatible collations"),
     * dan mengubah kedua sisi dalam satu statement tidak mungkin karena ALTER
     * TABLE hanya menyentuh satu tabel. Karena itu FK ini dilepas dulu, lalu
     * dibuat ulang dengan definisi aslinya setelah semua kolom ter-collate.
     *
     * @return array<int, object{constraint: string, table: string, definition: string}>
     */
    public static function textForeignKeys(): array
    {
        return DB::select(
            "select con.conname as constraint, c.relname as table, pg_get_constraintdef(con.oid) as definition
               from pg_constraint con
               join pg_class c on c.oid = con.conrelid
               join pg_namespace n on n.oid = c.relnamespace
              where con.contype = 'f'
                and n.nspname = ?
                and exists (
                    select 1
                      from unnest(con.conkey) as key(attnum)
                      join pg_attribute a on a.attrelid = con.conrelid and a.attnum = key.attnum
                     where a.atttypid in ('text'::regtype, 'varchar'::regtype, 'bpchar'::regtype)
                )
              order by c.relname, con.conname",
            [self::schema()]
        );
    }

    /**
     * Terapkan collation ke semua kolom teks yang masih memakai collation lain.
     *
     * Satu statement ALTER per tabel supaya tiap tabel hanya ditulis ulang
     * sekali; seluruh perubahan dalam satu transaction agar kegagalan di
     * tengah tidak menyisakan skema setengah case-insensitive.
     *
     * @return array<int, string> daftar `tabel.kolom` yang diubah
     */
    public static function apply(): array
    {
        if (! self::isPostgres()) {
            return [];
        }

        self::ensureCollation();

        $columns = self::pendingColumns();

        if ($columns === []) {
            return [];
        }

        $byTable = [];

        foreach ($columns as $column) {
            $byTable[$column->table][] = $column;
        }

        $collate = self::qualifiedName();
        $relation = self::quoteIdentifier(self::schema()).'.';
        $foreignKeys = self::textForeignKeys();
        $altered = [];

        DB::transaction(function () use ($byTable, $collate, $relation, $foreignKeys, &$altered): void {
            foreach ($foreignKeys as $foreignKey) {
                DB::statement('alter table '.$relation.self::quoteIdentifier($foreignKey->table).
                    ' drop constraint '.self::quoteIdentifier($foreignKey->constraint));
            }

            foreach ($byTable as $table => $tableColumns) {
                $actions = [];

                foreach ($tableColumns as $column) {
                    $actions[] = 'alter column '.self::quoteIdentifier($column->column).
                        " type {$column->sql_type} collate {$collate}";
                    $altered[] = $table.'.'.$column->column;
                }

                DB::statement('alter table '.$relation.self::quoteIdentifier($table).implode(', ', $actions));
            }

            foreach ($foreignKeys as $foreignKey) {
                DB::statement('alter table '.$relation.self::quoteIdentifier($foreignKey->table).
                    ' add constraint '.self::quoteIdentifier($foreignKey->constraint).' '.$foreignKey->definition);
            }
        });

        return $altered;
    }

    /**
     * Kembalikan kolom ke collation default database.
     *
     * @return array<int, string> daftar `tabel.kolom` yang dikembalikan
     */
    public static function reset(): array
    {
        if (! self::isPostgres()) {
            return [];
        }

        $oid = self::collationOid();

        if ($oid === null) {
            return [];
        }

        $columns = DB::select(
            "select c.relname as table, a.attname as column, format_type(a.atttypid, a.atttypmod) as sql_type
               from pg_attribute a
               join pg_class c on c.oid = a.attrelid
               join pg_namespace n on n.oid = c.relnamespace
              where n.nspname = ?
                and c.relkind in ('r', 'p')
                and a.attnum > 0
                and not a.attisdropped
                and a.attcollation = ?
              order by c.relname, a.attnum",
            [self::schema(), $oid]
        );

        $relation = self::quoteIdentifier(self::schema()).'.';
        $foreignKeys = self::textForeignKeys();
        $byTable = [];

        foreach ($columns as $column) {
            $byTable[$column->table][] = $column;
        }

        $restored = [];

        DB::transaction(function () use ($byTable, $relation, $foreignKeys, &$restored): void {
            foreach ($foreignKeys as $foreignKey) {
                DB::statement('alter table '.$relation.self::quoteIdentifier($foreignKey->table).
                    ' drop constraint '.self::quoteIdentifier($foreignKey->constraint));
            }

            foreach ($byTable as $table => $tableColumns) {
                $actions = [];

                foreach ($tableColumns as $column) {
                    // Jangan tentukan collate apa-apa — biarkan PostgreSQL pakai
                    // collation default database tabel ini (tanpa FK cross-database
                    // reference seperti pg_catalog.default yang menyebabkan error).
                    $actions[] = 'alter column '.self::quoteIdentifier($column->column).
                        " type {$column->sql_type}";
                    $restored[] = $table.'.'.$column->column;
                }

                DB::statement('alter table '.$relation.self::quoteIdentifier($table).implode(', ', $actions));
            }

            foreach ($foreignKeys as $foreignKey) {
                DB::statement('alter table '.$relation.self::quoteIdentifier($foreignKey->table).
                    ' add constraint '.self::quoteIdentifier($foreignKey->constraint).' '.$foreignKey->definition);
            }
        });

        DB::statement('drop collation if exists '.self::qualifiedName());

        return $restored;
    }

    public static function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
}
