<?php

namespace App\Filament\Shared\Typography;

use App\Support\AppFont\AppFont;
use App\Support\PageStyles\PageStyles;
use App\Support\TextStyles\TextStyles;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;

/**
 * SATU SUMBER kontrol kustomisasi in-form, BER-KSCOPE PER HALAMAN.
 *
 * PENTING (aturan anti-tumbukan): SETIAP pemanggil WAJIB menentukan
 * scope halamannya sendiri -- mis. resource catalog menjalankan
 * `PageStyles::scopeFromSlug('catalogs')`. Perubahan apapun menulis
 * ke `PageStyles::apply($scope, ...)` dan CSS-nya hanya teremit untuk
 * `[data-page-style="<scope>"]`. Halaman lain TIDAK terpengaruh --
 * custom halaman A ya kena halaman A saja.
 *
 * Dua bentuk builder yang sama:
 *
 *   - `section($scope)`: blok "Tipografi Halaman Ini" + Gaya per-field
 *     DI ATAS input form resource (dipasang HasModelColumns untuk semua
 *     resource, dan 3 resource form-manual).
 *
 *   - `fieldStylesSection()`: gaya DI ATAS SATU input (lapisan per-
 *     field, key `tabel.kolom`, tetap dicakupkan ke scope).
 *
 * SEMUA select: `searchable()` (font 246+ keluarga), `dehydrated(false)`
 * (tidak menyentuh kolom model), `live()` + `afterStateUpdated` yang
 * LANGSUNG tersimpan ke sesuai mesin-mesin (pola Customizer WordPress
 * tanpa tombol simpan).
 */
class TypographyControls
{
    public const FIELD_PREFIX = '__ga_teks__';

    // ------------------------------------------------------------------
    // Section per-scope (DI ATAS input form resource)
    // ------------------------------------------------------------------

    public static function section(string $scope): Section
    {
        return Section::make(__('Tipografi Halaman Ini'))
            ->description(__('Mengatur tampilan halaman ini saja (scope :scope) -- halaman lain ikut temanya masing-masing.', ['scope' => $scope]))
            ->columns(3)
            ->collapsible()
            ->schema(
                // Fieldset SESUAI peran halaman -- bukan trio tetap:
                // halaman berjenjang dapat Head 2/Head 3, halaman
                // sederhana cukup Head 1 + Deskripsi.
                array_map(
                    fn (string $role): Fieldset => self::fieldset(
                        $scope,
                        PageStyles::roleLabel($role),
                        $role,
                    ),
                    PageStyles::rolesFor($scope),
                )
            );
    }

    /**
     * Satu peran: Perataan/Ukuran/Ketebalan/Kapital (4 select) -- selalu
     * menulis ke SATU scope yang ditentukan pemanggil.
     */
    public static function fieldset(string $scope, string $label, string $role): Fieldset
    {
        return Fieldset::make($label)
            ->columns(4)
            ->schema([
                self::scopeSelect($scope, "{$role}_align", __('Perataan'), self::alignOptions()),
                self::scopeSelect($scope, "{$role}_size", __('Ukuran'), self::sizeOptions()),
                self::scopeSelect($scope, "{$role}_weight", __('Ketebalan'), self::weightOptions()),
                self::scopeSelect($scope, "{$role}_transform", __('Kapital'), self::transformOptions()),
            ]);
    }

    // ------------------------------------------------------------------
    // Gaya per-field (key tetap `tabel.kolom`, tetap ber-scope)
    // ------------------------------------------------------------------

    public static function fieldStylesSection(string $scope, string $table, string $column): Section
    {
        $prefix = self::FIELD_PREFIX.$table.'__'.$column;

        return Section::make(__('Gaya Teks: :kolom', ['kolom' => self::humanizeColumn($column)]))
            ->description(__('Hanya berlaku di dalam halaman ini untuk kolom ini.'))
            ->compact()
            ->columns(3)
            ->collapsible()
            ->schema([
                self::fieldSelect($scope, $prefix, $table, $column, 'family', __('Font'), self::familyOptions()),
                self::fieldSelect($scope, $prefix, $table, $column, 'size', __('Ukuran'), self::sizeOptions()),
                self::fieldSelect($scope, $prefix, $table, $column, 'align', __('Perataan'), self::alignOptions()),
                self::fieldSelect($scope, $prefix, $table, $column, 'weight', __('Ketebalan'), self::weightOptions()),
                self::fieldSelect($scope, $prefix, $table, $column, 'color_mode', __('Mode Warna'), self::colorModeOptions()),
                ColorPicker::make("{$prefix}.color")
                    ->label(__('Warna Font'))
                    ->dehydrated(false)
                    ->live()
                    ->visible(fn (callable $get): bool => $get("{$prefix}.color_mode") === 'custom')
                    ->default((string) (TextStyles::get($scope, $table, $column)['color'] ?? ''))
                    ->afterStateUpdated(fn ($state) => TextStyles::apply($scope, $table, $column, ['color' => (string) $state])),
                self::fieldSelect($scope, $prefix, $table, $column, 'transform', __('Kapital'), self::transformOptions()),
            ]);
    }

    // ------------------------------------------------------------------
    // Options (satu sumber untuk per-peran dan per-field)
    // ------------------------------------------------------------------

    /**
     * @return array<array-key, string>
     */
    public static function familyOptions(): array
    {
        $families = AppFont::families();

        return ['' => __('Ikuti Tema')] + array_combine($families, $families);
    }

    /**
     * @return array<array-key, string>
     */
    public static function sizeOptions(): array
    {
        $options = [0 => __('Ikuti Tema')];

        foreach (PageStyles::VALID_SIZES as $size) {
            $options[$size] = "{$size} px";
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function alignOptions(): array
    {
        return [
            ''        => __('Ikuti Tema'),
            'left'    => __('Kiri'),
            'center'  => __('Tengah'),
            'right'   => __('Kanan'),
            'justify' => __('Rata Kiri-kanan'),
        ];
    }

    /**
     * @return array<array-key, string>
     */
    public static function weightOptions(): array
    {
        $options = [0 => __('Ikuti Tema')];

        foreach (PageStyles::VALID_WEIGHTS as $weight) {
            $options[$weight] = match ($weight) {
                300 => __('Tipis (300)'),
                400 => __('Biasa (400)'),
                500 => __('Medium (500)'),
                600 => __('Semi Bold (600)'),
                700 => __('Bold (700)'),
                default => (string) $weight,
            };
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function colorModeOptions(): array
    {
        return [
            'theme'  => __('Ikuti Tema'),
            'light'  => __('Teks Gelap'),
            'dark'   => __('Teks Terang'),
            'custom' => __('Warna Kustom'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function transformOptions(): array
    {
        return [
            ''           => __('Ikuti Tema'),
            'uppercase'  => __('Kapital semua'),
            'lowercase'  => __('kecil semua'),
            'capitalize' => __('Huruf Awal Kata'),
        ];
    }

    // ------------------------------------------------------------------
    // Builder select
    // ------------------------------------------------------------------

    /** Select yang menulis ke key PageStyles SATU scope. */
    protected static function scopeSelect(string $scope, string $key, string $label, array $options): Select
    {
        return Select::make($key)
            ->label($label)
            ->options($options)
            ->native(false)
            ->searchable()
            ->dehydrated(false)
            ->live()
            ->default((string) (PageStyles::get($scope)[$key] ?? ''))
            ->afterStateUpdated(function (mixed $state) use ($scope, $key): void {
                PageStyles::apply($scope, [
                    $key => str_contains($key, '_size') || str_contains($key, '_weight')
                        ? (is_numeric($state) ? (int) $state : 0)
                        : (string) $state,
                ]);
            });
    }

    /** Select yang menulis ke gaya per-field SATU scope. */
    protected static function fieldSelect(string $scope, string $prefix, string $table, string $column, string $prop, string $label, array $options): Select
    {
        return Select::make("{$prefix}.{$prop}")
            ->label($label)
            ->options($options)
            ->native(false)
            ->searchable()
            ->dehydrated(false)
            ->live()
            ->default((string) (TextStyles::get($scope, $table, $column)[$prop] ?? ''))
            ->afterStateUpdated(function (mixed $state) use ($scope, $table, $column, $prop): void {
                TextStyles::apply($scope, $table, $column, [
                    $prop => in_array($prop, ['size', 'weight'], true)
                        ? (is_numeric($state) ? (int) $state : 0)
                        : (string) $state,
                ]);
            });
    }

    protected static function humanizeColumn(string $column): string
    {
        return (string) str_replace('_', ' ', $column);
    }
}
