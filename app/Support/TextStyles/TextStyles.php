<?php

namespace App\Support\TextStyles;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * GAYA TEKS PER-FIELD YANG BER-SCOPE (anti-tumbukan).
 *
 * Admin mis. menyentuh select gaya DI ATAS input `name` di form
 * resource Catalog -- dan itu HARUS hanya mengubah field `name` DI
 * HALAMAN yang disetel itu, bukan semua halaman yang memuat kolom
 * `name`.
 *
 * Penyimpanan: SATU record `fm_app_settings.key = 'text_styles'`,
 * kunci penyimpanannya GAJAH-gajah per scope:
 *
 *     "catalogs:catalogs.name" => [
 *         'family' => 'Roboto' | '',  'size' => 20 | 0,
 *         'weight' => 700 | 0,        'align' => 'center' | '',
 *         'transform' => 'none|uppercase|lowercase|capitalize',
 *         'color_mode' => 'theme|light|dark|custom',
 *         'color' => '#hex' (dipakai saat custom),
 *     ],
 *
 * (format: `<scope>:<tabel>.<kolom>`).
 *
 * CSS di-emit `stylesheet()`: SELALU DI-CAKUP WARNA halaman dengan
 * selector bertindih:
 *
 *     [data-page-style="catalogs"] [data-field="catalogs.name"] { ... }
 *
 * Skiema "[page-style] [data-field]" memastikan custom halaman A
 * TIDAK JEMUK halaman B: elemen penandai `data-field` di halaman B
 * tidak memiliki induk [data-page-style="catalogs"], jadi aturan
 * A tidak mendengar apa pun di sana.
 *
 * Nilai netral (semua ikut tema) menjatuhkan entri dari peta -- pola
 * PageStyles.
 */
class TextStyles
{
    const CACHE_KEY = 'app_text_styles';
    const CACHE_TTL = 60;

    public const STORE_KEY = 'text_styles';

    public const VALID_SIZES = [11, 12, 13, 14, 15, 16, 17, 18, 20, 22, 24, 26, 28, 32, 36, 40, 48, 56];
    public const VALID_WEIGHTS = [300, 400, 500, 600, 700];
    public const VALID_ALIGNS = ['left', 'center', 'right', 'justify'];
    public const VALID_TRANSFORMS = ['uppercase', 'lowercase', 'capitalize'];
    public const VALID_COLOR_MODES = ['theme', 'light', 'dark', 'custom'];

    public const LIGHT_COLOR = '#374151';
    public const DARK_COLOR  = '#e5e7eb';

    /**
     * Bentuk netral satu field: semuanya ikut tema.
     *
     * @return array{family: string, size: int, weight: int, align: string, color_mode: string, transform: string}
     */
    public static function neutrals(): array
    {
        return [
            'family' => '',
            'size' => 0,
            'weight' => 0,
            'align' => '',
            'color_mode' => 'theme',
            'color' => '',
            'transform' => 'none',
        ];
    }

    /**
     * Kombinasi tabel + kolom -> bagian field dari key penyimpanan /
     * atribut blade. Satu FONT konsistensi: data-field di grid, detail,
     * dan harga selalu merata (anti dobel-tulis string yang bisa lepas).
     */
    public static function fieldKey(string $table, string $column): string
    {
        return $table.'.'.trim($column);
    }

    /**
     * Atribut HTML untuk elemen blade: `data-field="tabel.kolom"`
     * (statis -- scoping halamannya lewat induk data-page-style).
     */
    public static function attrs(string $table, string $column): string
    {
        return 'data-field="'.self::fieldKey($table, $column).'"';
    }

    /** Key penyimpanan lengkap: `<scope>:<tabel>.<kolom>`. */
    protected static function storeKey(string $scope, string $table, string $column): string
    {
        return $scope.':'.self::fieldKey($table, $column);
    }

    /**
     * Gaya satu field pada SATU scope, selalu bentuk lengkap dengan netral.
     *
     * @return array{family: string, size: int, weight: int, align: string, color_mode: string, transform: string}
     */
    public static function get(string $scope, string $table, string $column): array
    {
        return array_merge(self::neutrals(), self::all()[self::storeKey($scope, $table, $column)] ?? []);
    }

    /**
     * Patch sebagian properti satu field di SATU scope -- dipanggil dari
     * `afterStateUpdated` select di atas input; perubahan LANGSUNG
     * tersimpan.
     *
     * @param array<string, mixed> $patch
     */
    public static function apply(string $scope, string $table, string $column, array $patch): void
    {
        $scope = trim($scope);

        if ($scope === '' || preg_match('/^[a-z][a-z0-9_:-]{0,63}$/', $scope) !== 1) {
            // Scope gugur: terkunci "home" supaya tidak bocor ke peta luas
            // apa pun -- perlu VALID (lihat stylesheet()).
            $scope = 'home';
        }

        $key     = self::storeKey($scope, $table, $column);
        $current = self::all()[$key] ?? [];
        $merged  = array_merge(self::neutrals(), $current, $patch);
        $cleaned = self::validate($merged);

        // Semuanya netral -> field DIBUANG dari peta.
        $map = self::all();

        if ($cleaned === null) {
            unset($map[$key]);
        } else {
            $map[$key] = $cleaned;
        }

        self::store($map);
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    protected static function store(array $map): void
    {
        \App\Models\FmAppSetting\FmAppSetting::saveTextStyles($map);

        self::forgetCache();
    }

    /**
     * Peta per-field yang valid (cache 60s, pola AppFont).
     *
     * @return array<string, array<string, string|int>>
     */
    public static function all(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): array {
                try {
                    if (! Schema::hasTable('fm_app_settings')) {
                        return [];
                    }

                    $setting = \App\Models\FmAppSetting\FmAppSetting::where('key', self::STORE_KEY)->first();
                    $stored = ($setting && is_array($setting->value)) ? $setting->value : [];

                    $out = [];

                    foreach ($stored as $key => $value) {
                        if (! is_string($key) || ! preg_match('/^[a-z][a-z0-9_-]{0,63}:[a-z0-9_]+\.[a-z0-9_]+$/i', $key) || ! is_array($value)) {
                            continue;
                        }

                        $clean = self::validate($value);

                        if ($clean !== null) {
                            $out[$key] = $clean;
                        }
                    }

                    return $out;
                } catch (\Throwable $e) {
                    return [];
                }
            });
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Bersihkan satu gaya field; `null` = semua netral (entri dibuang).
     *
     * @param  array<string, mixed>  $style
     * @return array{family: string, size: int, weight: int, align: string, color_mode: string, transform: string}|null
     */
    protected static function validate(array $style): ?array
    {
        $neutral = self::neutrals();

        $family = trim((string) ($style['family'] ?? ''));
        $neutral['family'] = ($family !== '' && in_array($family, \App\Support\AppFont\AppFont::families(), true))
            ? $family
            : '';

        $size = is_numeric($style['size'] ?? null) ? (int) $style['size'] : 0;
        $neutral['size'] = ($size > 0 && in_array($size, self::VALID_SIZES, true)) ? $size : 0;

        $weight = is_numeric($style['weight'] ?? null) ? (int) $style['weight'] : 0;
        $neutral['weight'] = ($weight > 0 && in_array($weight, self::VALID_WEIGHTS, true)) ? $weight : 0;

        $align = trim((string) ($style['align'] ?? ''));
        $neutral['align'] = in_array($align, self::VALID_ALIGNS, true) ? $align : '';

        $mode = trim((string) ($style['color_mode'] ?? 'theme'));
        $neutral['color_mode'] = in_array($mode, self::VALID_COLOR_MODES, true) ? $mode : 'theme';

        $tx = trim((string) ($style['transform'] ?? 'none'));
        $neutral['transform'] = in_array($tx, array_merge(['none'], self::VALID_TRANSFORMS), true) ? $tx : 'none';

        // Bandingkan ke NETRAL ASLI (bukan diri sendiri) -- lalu simpan
        // properti yang BEDA saja.
        $defaults = self::neutrals();
        $cleaned = [];

        foreach ($neutral as $key => $value) {
            if ((string) $value !== (string) ($defaults[$key] ?? $value)) {
                $cleaned[$key] = $value;
            }
        }

        // Warna custom: hanya hex yang boleh lewat, sisanya tema.
        if ($neutral['color_mode'] === 'custom') {
            $color = trim((string) ($style['color'] ?? ''));

            if (preg_match('/^#(?:[0-9a-fA-F]{6}|[0-9a-fA-F]{3})$/', $color)) {
                $cleaned['color_mode'] = 'custom';
                $cleaned['color'] = strtolower($color);
            }
        }

        return $cleaned === [] ? null : $cleaned;
    }

    // ------------------------------------------------------------------
    // Stylesheet (emit via AppFont::styles(), SETELAH PageStyles)
    // ------------------------------------------------------------------

    /**
     * CSS per-field yang DI-CAKUP: setiap aturan padamkan selector
     * `[data-page-style="<scope>"] [data-field="<kolom>"]`, jadi aturan
     * TIDAK boleh keluar dari halaman yang di-settle.
     */
    public static function stylesheet(): string
    {
        $rules = [];

        foreach (self::all() as $key => $style) {
            if (preg_match('/^([a-z0-9_-]+):([a-z0-9_]+\.[a-z0-9_]+)$/i', $key, $m) !== 1) {
                continue;
            }

            $scope = $m[1];
            $field = $m[2];

            if (! \App\Support\PageStyles\PageStyles::validPageKey($scope)) {
                continue;
            }

            $decls = [];

            if (($style['family'] ?? '') !== '') {
                $decls[] = "font-family: '".(string) $style['family']."', sans-serif !important;";
            }

            if ((int) ($style['size'] ?? 0) > 0) {
                $decls[] = 'font-size: '.(int) $style['size'].'px !important;';
            }

            if ((int) ($style['weight'] ?? 0) > 0) {
                $decls[] = 'font-weight: '.(int) $style['weight'].' !important;';
            }

            if (($style['align'] ?? '') !== '') {
                $decls[] = 'text-align: '.(string) $style['align'].' !important;';
            }

            if (($style['transform'] ?? 'none') !== 'none') {
                $decls[] = 'text-transform: '.(string) $style['transform'].' !important;';
            }

            $mode = $style['color_mode'] ?? 'theme';
            $color = (string) ($style['color'] ?? '');

            if ($mode === 'custom' && preg_match('/^#(?:[0-9a-fA-F]{6}|[0-9a-fA-F]{3})$/', $color)) {
                $decls[] = 'color: '.$color.' !important;';
            } elseif ($mode === 'light') {
                $decls[] = 'color: '.self::LIGHT_COLOR.' !important;';
            } elseif ($mode === 'dark') {
                $decls[] = 'color: '.self::DARK_COLOR.' !important;';
            }

            if ($decls !== []) {
                $rules[] = "    [data-page-style=\"{$scope}\"] [data-field=\"{$field}\"],\n    body:has([data-page-style-mount=\"{$scope}\"]) [data-field=\"{$field}\"] {\n        ".implode(' ', $decls)."\n    }";
            }
        }

        if ($rules === []) {
            return '';
        }

        $out = "\n<!-- App\\Support\\TextStyles: gaya per-field, di-cakup per scope (fm_app_settings.key=text_styles) -->\n";
        $out .= "<style data-app-text-styles>\n";
        $out .= implode("\n", $rules)."\n";
        $out .= "</style>\n";

        return $out;
    }
}
