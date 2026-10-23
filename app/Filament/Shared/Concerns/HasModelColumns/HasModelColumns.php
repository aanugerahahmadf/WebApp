<?php

namespace App\Filament\Shared\Concerns\HasModelColumns;

use App\Filament\Shared\Typography\TypographyControls;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Skema form + kolom tabel yang DITURUNKAN dari skema tabel model.
 *
 * Kenapa bukan ditulis manual di setiap resource:
 *
 *   - Ada 22 resource di panel admin dan hampir semuanya punya bentuk yang
 *     sama: daftar kolom model, kolom ID, dan beberapa kolom tanggal. Kalau
 *     ditulis manual, 22 file mengulang hal yang sama dan kolom yang BARU
 *     tidak akan muncul sampai seseorang ingat menambahkannya.
 *   - Turunan dari skema otomatis ikut saat tabelnya berubah.
 *
 * Yang SENGAJA dikecualikan:
 *
 *   - `password` dan `remember_token` (jangan pernah ditampilkan),
 *   - kolom `_id` (foreign key) di form -- formnya diisi lewat relation
 *     manager atau select, bukan input angka mentah,
 *   - kolom JSON/blob (bukan input yang masuk akal).
 */
trait HasModelColumns
{
    /**
     * @return array<\Filament\Forms\Components\Component>
     */
    public static function formSchema(): array
    {
        $model = static::getModel();

        if (! class_exists($model)) {
            return [];
        }

        $instance = new $model;

        $columns = static::modelColumnListing();

        // LAPISAN KUSTOMISASI BER-SCOPE (WordPress-style, anti-tumbukan):
        // scope = halaman resource ini sendiri (slug -> key `PageStyles`),
        // MIS. form Catalog menyetel scope 'catalogs'. Custom halaman A
        // menulis ke scope A; scope lain (halaman resource lain) tidak
        // terpengaruh. SETIAP TextInput/Textarea juga membawa section
        // "Gaya Teks" per-field di atasnya (dehydrated(false) semua --
        // tidak menyentuh kolom model).
        $scope = \App\Support\PageStyles\PageStyles::scopeFromSlug(
            (string) static::getSlug()
        );

        $fields = [
            TypographyControls::section($scope),
        ];

        // TANPA lapisan gaya di dalam form: kustomisasi tampilan tidak
        // tinggal di halaman form (tiap rekam judulnya beda-beda) --
        // panelnya hidup DI ATAS TABEL halaman list resource, dari hook
        // `PAGE_START` di AdminPanelProvider. Form ini murni kolom model.
        return collect($columns)
            ->reject(fn (string $column): bool => str_ends_with($column, '_id')
                || str_ends_with($column, '_at')
                || in_array($column, ['id', 'created_at', 'updated_at', 'deleted_at'], true))
            ->map(function (string $column) use ($instance): mixed {
                $type = Schema::getColumnType($instance->getTable(), $column);

                return match (true) {
                    $type === 'boolean' => Toggle::make($column),
                    $type === 'text', $type === 'json' => Textarea::make($column),
                    default => TextInput::make($column),
                };
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<\Filament\Tables\Columns\Column>
     */
    public static function tableColumns(): array
    {
        $model = static::getModel();

        if (! class_exists($model)) {
            return [];
        }

        $instance = new $model;

        return collect(static::modelColumnListing())
            ->reject(fn (string $column): bool => in_array($column, ['password', 'remember_token'], true))
            ->map(fn (string $column): TextColumn => TextColumn::make($column)->label(static::humanizeColumn($column)))
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function getAllSearchableAttributes(): array
    {
        $model = static::getModel();

        if (! class_exists($model)) {
            return [];
        }

        $instance = new $model;

        return collect(static::modelColumnListing())
            ->reject(fn (string $column): bool => in_array($column, ['id', 'password', 'remember_token'], true))
            ->take(6)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected static function modelColumnListing(): array
    {
        static $cache = [];

        $model = static::getModel();

        if (! class_exists($model)) {
            return [];
        }

        $table = (new $model)->getTable();

        if (isset($cache[$table])) {
            return $cache[$table];
        }

        if (! Schema::hasTable($table)) {
            return $cache[$table] = [];
        }

        return $cache[$table] = Schema::getColumnListing($table);
    }

    protected static function humanizeColumn(string $column): string
    {
        return (string) str_replace('_', ' ', $column);
    }
}
