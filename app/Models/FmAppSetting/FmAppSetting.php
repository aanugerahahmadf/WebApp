<?php

namespace App\Models\FmAppSetting;

use App\Support\PageStyles\PageStyles;
use Illuminate\Database\Eloquent\Model;

class FmAppSetting extends Model
{
    protected $table = 'fm_app_settings';
    protected $fillable = ['key', 'value'];
    protected $casts = ['value' => 'array'];

    public static function defaults(): array
    {
        return [
            'family'       => config('app-font.family', \App\Support\AppFont\AppFont::DEFAULT_FAMILY),
            'admin_family' => \App\Support\AppFont\AppFont::DEFAULT_FAMILY,
            'size'         => 16,
            'weight'       => (int) config('app-font.weight', 400),
            'color'        => '#1f2937',    // warna teks isi
            'transform'    => 'none',
            'accent_color' => '#6366f1',   // warna tegas/primary panel
            'bg_color'     => '',          // background body, kosong = bawaan glass
            'radius'       => 8,           // border-radius (px)
            'custom_css'   => '',          // CSS tambahan dari admin
            'brand_name'   => 'Dekorasi Bunga Pernikahan',
            'brand_logo'   => '/images/logo.png',
        ];
    }

    public static function appearance(): array
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('fm_app_settings')) {
                return self::defaults();
            }

            $setting = self::where('key', 'appearance')->first();
            if (! $setting || ! is_array($setting->value)) {
                return self::defaults();
            }

            return array_merge(self::defaults(), array_filter($setting->value, fn ($v) => $v !== null));
        } catch (\Throwable $e) {
            return self::defaults();
        }
    }

    public static function saveAppearance(array $data): void
    {
        $current = self::appearance();
        $merged = array_merge($current, $data);

        $merged['family']       = trim((string) ($merged['family'] ?? \App\Support\AppFont\AppFont::DEFAULT_FAMILY));
        $merged['admin_family'] = trim((string) ($merged['admin_family'] ?? \App\Support\AppFont\AppFont::DEFAULT_FAMILY));

        // Whitelist keluarga: nilainya masuk ke CSS (--app-font-family),
        // jadi bukan input bebas. Keluarga tidak dikenal (nilai lama yang
        // begitu saja diubah manual, isko) jatuh ke default. Daftar dinamis
        // dari katalog aset lokal (AppFont::families()).
        $allowed = \App\Support\AppFont\AppFont::families();
        foreach (['family', 'admin_family'] as $famKey) {
            if (! in_array($merged[$famKey], $allowed, true)) {
                $merged[$famKey] = \App\Support\AppFont\AppFont::DEFAULT_FAMILY;
            }
        }

        $merged['size']         = max(10, min(32, (int) ($merged['size'] ?? 16)));
        $merged['weight']       = max(100, min(900, (int) ($merged['weight'] ?? 400)));
        $merged['color']        = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($merged['color'] ?? '')) ? $merged['color'] : self::defaults()['color'];
        $merged['transform']    = in_array($merged['transform'], ['none', 'capitalize', 'uppercase', 'lowercase'], true) ? $merged['transform'] : 'none';

        $colors = ['accent_color'];
        foreach ($colors as $c) {
            $val = trim((string) ($merged[$c] ?? ''));
            if ($val === '' || ! preg_match('/^#[0-9a-fA-F]{6}$/', $val)) {
                $merged[$c] = self::defaults()[$c] ?? '#6366f1';
            }
        }

        $bg = trim((string) ($merged['bg_color'] ?? ''));
        $merged['bg_color']     = ($bg === '' || preg_match('/^#[0-9a-fA-F]{6}$/', $bg)) ? $bg : '';
        $merged['radius']       = max(0, min(32, (int) ($merged['radius'] ?? 8)));
        $merged['custom_css']   = (string) ($merged['custom_css'] ?? '');
        $merged['brand_name']    = trim((string) ($merged['brand_name'] ?? 'Dekorasi Bunga Pernikahan'));
        $merged['brand_logo']    = trim((string) ($merged['brand_logo'] ?? '/images/logo.png'));

        $setting = self::firstOrNew(['key' => 'appearance']);
        $setting->value = $merged;
        $setting->save();

        \Cache::forget('app_font_settings');
    }

    /**
     * Record mentah `key='typography'` -- peta per-peran milik
     * `App\Support\AppTypography\AppTypography` (lapisan tipografi
     * per-peran). TANPA validasi di sini: nilai yang masuk sudah
     * dibersihkan mesin penyimpannya (pola `savePageStyles()`).
     */
    public static function typography(): array
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('fm_app_settings')) {
                return [];
            }

            $setting = self::where('key', 'typography')->first();

            return ($setting && is_array($setting->value)) ? $setting->value : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function saveTypography(array $map): void
    {
        $setting = self::firstOrNew(['key' => 'typography']);
        $setting->value = $map;
        $setting->save();

        \Cache::forget('app_typography');
    }

    /**
     * Record mentah `key='text_styles'` -- peta per-field milik
     * `App\Support\TextStyles\TextStyles` (lapisan gaya per input).
     */
    public static function textStyles(): array
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('fm_app_settings')) {
                return [];
            }

            $setting = self::where('key', 'text_styles')->first();

            return ($setting && is_array($setting->value)) ? $setting->value : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function saveTextStyles(array $map): void
    {
        $setting = self::firstOrNew(['key' => 'text_styles']);
        $setting->value = $map;
        $setting->save();

        \Cache::forget('app_text_styles');
    }

    /** Settings for the public User and Welcome panel background. */
    public static function backgroundDefaults(): array
    {
        return [
            'active' => false,
            'type' => 'image',
            'image' => null,
            'video' => null,
            'overlay' => '#000000',
            'overlay_opacity' => 20,
            'blur' => 2,
            'parallax_strength' => 18,
            'parallax_speed' => 0.12,
        ];
    }

    public static function background(): array
    {
        try {
            $setting = self::where('key', 'background')->first();
            $value = $setting?->value;

            return array_merge(self::backgroundDefaults(), is_array($value) ? $value : []);
        } catch (\Throwable) {
            return self::backgroundDefaults();
        }
    }

    public static function saveBackground(array $data): void
    {
        $defaults = self::backgroundDefaults();
        $data = array_merge($defaults, $data);
        $data['active'] = (bool) $data['active'];
        $data['type'] = in_array($data['type'], ['image', 'video'], true) ? $data['type'] : 'image';
        $data['overlay'] = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $data['overlay'])
            ? $data['overlay'] : $defaults['overlay'];
        $data['overlay_opacity'] = max(0, min(100, (int) $data['overlay_opacity']));
        $data['blur'] = max(0, min(40, (int) $data['blur']));
        $data['parallax_strength'] = max(0, min(60, (int) $data['parallax_strength']));
        $data['parallax_speed'] = max(0.02, min(0.5, (float) $data['parallax_speed']));

        self::updateOrCreate(['key' => 'background'], ['value' => $data]);
    }
}
