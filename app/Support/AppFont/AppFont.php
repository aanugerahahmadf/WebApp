<?php

namespace App\Support\AppFont;

use App\Models\FmAppSetting\FmAppSetting;
use App\Support\LocalFontProvider\LocalFontProvider;
use Filament\FontProviders\BunnyFontProvider;
use Illuminate\Support\Facades\Cache;

/**
 * Sumber tunggal untuk tampilan teks dan akcent panel Welcome & User.
 *
 * Ketiga panel provider memanggil kelas ini, jadi mengubah
 * nilai di database (lewat halaman Admin) mengubah utamanya
 * panel User dan Welcome sekaligus.
 *
 * Layout (glass, navbar, table) panel tetap memakai CSS masing-masing
 * (`Welcome.css`, `User.css`, `Admin.css`) — kelas ini hanya
 * menimpa variabel tipografi dan warna aksen yang aman.
 *
 * Kosakata tampilan:
 *   - keluarga, ukuran, berat, warna, capitalisasi
 *   - warna aksen (aksent panel), warna latar (opsional)
 *   - radius sudut (px)
 *   - CSS tambahan (custom, opsional, admin-only)
 */
class AppFont
{
    public const VALID_WEIGHTS = [100,200,300,400,500,600,700,800,900];
    public const VALID_RADIUS   = [0,2,4,6,8,10,12,16,20,24,32];

    /**
     * Keluarga huruf yang boleh dipilih admin.
     *
     * SEMUA turunan dari aset font LOKAL `assets/fonts/`: TTF variable
     * resmi Google (keluarga dasar berfolder) + SEMUA instansi static/
     * sebagai keluarga ter-nama dari nama filenya ('Asap Sharp Condensed
     * Black', 'Google Sans Flex 24pt Bold', ...). Daftar & pemasangan
     * berkas dihasilkan `scripts/generate-fonts.py` (jalankan ulang
     * setelah menambah folder/berkas font):
     *
     *   assets/fonts/<Keluarga>/          -> sumber (paket resmi Google)
     *   public/fonts/<Keluarga>/          -> salinan yang dilayani HTTP
     *   public/css/app-font.css           -> @font-face per keluarga
     *   app/Support/AppFont/font-catalog.php -> daftar nama keluarga
     *
     * Daftar itu dipakai sebagai: whitelist validasi (nilai keluarga masuk
     * ke CSS, bukan input bebas), dan opsi select font
     * (FontSettingsComponent + WebsiteMobileCustomizerResource) --
     * select-nya searchable, ratusan pilihan tetap nyaman.
     */
    private const CATALOG = __DIR__ . '/font-catalog.php';

    /** Keluarga bawaan aplikasi -- dipakai kalau setting kosong/tidak valid. */
    public const DEFAULT_FAMILY = 'Google Sans Flex';

    private const CACHE_KEY = 'app_font_settings';
    private const CACHE_TTL = 60;

    /**
     * Pengaturan tampilan, dengan fallback ke default kalau sumbernya belum
     * tersedia.
     *
     * Kenapa ada try/catch di sini:
     *
     *   Nilai keluarga huruf & brand diambil di `Panel::brandName()` /
     *   `brandLogo()`, yang dipanggil SAAT PANEL DIBANGUN -- yaitu dari dalam
     *   middleware `panel:*`, jauh sebelum controller dan jauh sebelum
     *   migrations sempat berjalan. Begitu juga `cache.store` bisa berarti
     *   tabel `cache` yang belum ada.
     *
     *   Tanpa penjaga, satu query yang gagal membuat SETIAP halaman di ketiga
     *   panel ikut 500 -- termasuk halaman yang tidak ada hubungannya dengan
     *   font. Dan gejalanya menyesatkan, karena error-nya muncul sebagai
     *   "no such table" yang sama persis di mana-mana.
     *
   *   Font/brand adalah pengaturan presentasi. Default-nya di-hardcode di
     *   bawah, nilainya sama persis dengan fallback di setiap accessor, jadi
     *   hasil akhirnya di sini identik dengan hasil saat tabel ada tapi
     *   kosong. Tidak ada yang berubah secara visual.
     *
     *   Kegagalan DILEWATI, bukan dilempar: default selalu valid, jadi tidak
     *   ada alasan kuat untuk membuat seluruh panel mati gara-gara tabel
     *   pengaturan belum siap.
     */
    public static function settings(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
                return FmAppSetting::appearance();
            });
        } catch (\Throwable $e) {
            report($e);

            return self::defaults();
        }
    }

    /**
     * Nilai yang dipakai kalau pengaturan belum terbaca.
     *
     * Harus sama dengan fallback yang dipakai setiap accessor di bawah
     * (`g('family', 'Google Sans Flex')` dst), supaya jalur ini dan jalur normal
     * menghasilkan nilai yang sama -- tidak ada "lonjakan tampilan" begitu
     * cache terisi.
     *
     * @return array<string, mixed>
     */
    private static function defaults(): array
    {
        return [
            'family'       => self::DEFAULT_FAMILY,
            'admin_family' => self::DEFAULT_FAMILY,
            'size' => 16,
            'weight' => 400,
            'color' => '#1f2937',
            'accent_color' => '#6366f1',
            'bg_color' => '',
            'radius' => 8,
            'transform' => 'none',
            'brand_name' => (string) config('app-font.brand_name', 'Dekorasi Bunga Pernikahan'),
            'brand_logo' => '/images/logo.png',
            'custom_css' => '',
        ];
    }

    private static function g(string $key, mixed $default = null): mixed
    {
        return self::settings()[$key] ?? $default;
    }

    /**
     * Keluarga huruf STOREFRONT (Website = panel Welcome/User + Dekstop App).
     *
     * Dibaca dari pengaturan admin (halaman Tipografi / Website Mobile
     * Customizer, record `fm_app_settings.key = appearance`), lalu DIPERIKSA
     * terhadap whitelist `FAMILIES`: keluarga masuk ke CSS, jadi nilainya
     * harus daftar tertutup -- nilai apa pun yang tidak dikenal (nilai lama,
     * input bebas, database rusak) JATUH KE DEFAULT, bukan lempar error
     * (font adalah presentasi; satu nilai buruk tidak boleh mematikan
     * panel).
     *
     * File font selalu LOKAL -- TTF variable resmi Google yang diunduh dari
     * fonts.google.com dan berada di:
     *
     *   assets/fonts/<Keluarga>/... (sumber)
     *   public/fonts/<Keluarga>/... (salinan untuk HTTP)
     *
     * didefinisikan di `public/css/app-font.css` (dilink dari `styles()`
     * di bawah).
     */
    public static function family(): string
    {
        return self::resolvedFamily(self::g('family'));
    }

    /**
     * Keluarga huruf PANEL ADMIN (Dekstop Admin App) -- setting TERPISAH
     * dari storefront: admin men-tune panel kerjanya sendiri tanpa menimpa
     * tampilan pembeli (id-nya `admin_family` di appearance).
     */
    public static function adminFamily(): string
    {
        return self::resolvedFamily(self::g('admin_family'));
    }

    /** Daftar keluarga yang boleh dipilih (untuk select di halaman admin). */
    public static function families(): array
    {
        static $catalog = null;
        $catalog ??= (array) require self::CATALOG;

        // Penjaga runtime: katalog hasil generator WAJIB memuat kelas
        // bawaan. Kalau file katalog hilang/kosong, jatuh ke satu-satunya
        // keluarga yang pasti terpasang.
        if (! in_array(self::DEFAULT_FAMILY, array_values($catalog), true)) {
            return [self::DEFAULT_FAMILY];
        }

        return array_values($catalog);
    }

    /**
     * Nilai keluarga yang AMAN untuk CSS: hanya yang terdaftar di katalog.
     * Katalog berisi SEMUA keluarga dari aset lokal, dan default
     * 'Google Sans Flex' adalah nilai legacy -- setting lama tetap valid
     * tanpa migrasi data. Nilai asing (isko, input bebas, database rusak)
     * JATUH KE DEFAULT, bukan lempar error: font adalah presentasi, satu
     * nilai buruk tidak boleh mematikan panel.
     */
    private static function resolvedFamily(mixed $value): string
    {
        $v = trim((string) ($value ?? ''));
        return in_array($v, self::families(), true) ? $v : self::DEFAULT_FAMILY;
    }

    public static function size(): int { return max(10, min(32, (int) (self::g('size',16)))); }
    public static function weight(): int {
        $v = (int) self::g('weight',400);
        if (! in_array($v, self::VALID_WEIGHTS, true)) return 400;
        return max($v, 300);
    }
    public static function color(): string {
        $v = (string) self::g('color','#1f2937');
        return preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? $v : '#1f2937';
    }
    public static function accentColor(): string {
        $v = (string) self::g('accent_color','#6366f1');
        return preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? $v : '#6366f1';
    }
    public static function bgColor(): string {
        $v = trim((string) self::g('bg_color',''));
        return ($v === '' || preg_match('/^#[0-9a-fA-F]{6}$/', $v)) ? $v : '';
    }
    public static function radius(): int {
        $v = (int) self::g('radius',8);
        if (! in_array($v, self::VALID_RADIUS, true)) {
            return 8;
        }
        return $v;
    }
    public static function transform(): string {
        $v = (string) self::g('transform','none');
        return in_array($v, ['none','capitalize','uppercase','lowercase'], true) ? $v : 'none';
    }
    public static function brandName(): string
    {
        $v = trim((string) self::g('brand_name', 'Dekorasi Bunga Pernikahan'));
        $name = $v === '' ? 'Dekorasi Bunga Pernikahan' : $v;

        // Nama brand ikut bahasa.
        //
        // Kunci terjemahannya sudah ada ("Dekorasi Bunga Pernikahan" ->
        // "Wedding Flower Decorations" di lang/en.json). Tanpa ini, judul
        // jendela aplikasi desktop (Electron memakai <title>) tetap Bahasa
        // Indonesia walau panelnya sudah English -- jadi judulnya tidak
        // tersambung ke language switcher.
        //
        // Nama yang dikustomisasi pengguna tidak punya kunci terjemahan, jadi
        // __() mengembalikan nilainya apa adanya.
        return __($name);
    }

    public static function brandLogo(): string
    {
        $v = trim((string) self::g('brand_logo', '/images/logo.png'));
        return $v === '' ? '/images/logo.png' : $v;
    }

    public static function customCss(): string { return (string) self::g('custom_css',''); }

    /**
     * Provider font panel: SENGAJA TIDAK MENGUNDUH APA PUN.
     *
     * Font aplikasi LOKAL (public/fonts/<Keluarga>/), dimuat oleh
     * `public/css/app-font.css` yang dipasang `<link>` oleh `styles()` di
     * bawah. Kalau provider ini mengembalikan CSS CDN (Bunny/Google),
     * browser mengunduh font dua kali dan menyia-nyiakan bandwidth -- dan
     * CDN mati begitu aplikasi dipakai offline.
     */
    public static function provider(): string
    { return LocalFontProvider::class; }

    /**
     * CSS + <link> font untuk satu panel.
     *
     * @param string|null $panel 'admin' = keluarga panel admin
     *        (adminFamily, Dekstop Admin App); null/APAPUN yang lain =
     *        keluarga storefront (ViewerFont::current() -- Website,
     *        Dekstop App, dan halaman publik semuanya storefront).
     *
     * Kenapa storefront pakai `ViewerFont::current()` dan bukan `family()`:
     * `family()` = default GLOBAL yang diatur admin; `ViewerFont` =
     * pilihan pribadi pengunjung (session untuk tamu, `users.font_preference`
     * untuk user). TAMU pun boleh memilih hurufnya sendiri di halaman
     * Pengaturan, jadi CSS storefront harus mengikuti pilihan itu.
     */
    public static function styles(?string $panel = null): string
    {
        $family    = htmlspecialchars(
            $panel === 'admin'
                ? self::adminFamily()
                : \App\Support\ViewerFont\ViewerFont::current(),
            ENT_QUOTES,
            'UTF-8'
        );
        $weight    = self::weight();
        $size      = self::size();
        $color     = self::color();
        $transform = self::transform();
        $accent    = self::accentColor();
        $bg        = self::bgColor();
        $radius    = self::radius();
        $cssExtra  = self::customCss();

        // @font-face Google Sans Flex (TTF variable lokal) tidak berada di
        // sini -- satu sumber: public/css/app-font.css. Tautannya
        // di-version bust mtime supaya browser memuat ulang setelah berkas
        // font/css diganti.
        $cssPath = public_path('css/app-font.css');
        $cssVer  = is_file($cssPath) ? filemtime($cssPath) : '1';

        $bgDecl = $bg !== '' ? "background-color: {$bg};" : '';
        $css = <<<HTML
<!-- App\\Support\\AppFont: @font-face lokal (app-font.css) + DB settings (family, size, weight, color, transform, accent, radius, bg, custom_css) -->
<link rel="stylesheet" href="/css/app-font.css?v={$cssVer}">
<style>
    :root {
        --app-font-family: '{$family}';
        --app-font-size: {$size}px;
        --app-font-weight: {$weight};
        --app-text-color: {$color};
        --app-text-transform: {$transform};
        --app-accent-color: {$accent};
        --app-border-radius: {$radius}px;
    }

    body {
        font-family: var(--app-font-family), sans-serif;
        font-size: var(--app-font-size);
        font-weight: var(--app-font-weight);
        color: var(--app-text-color);
        text-transform: var(--app-text-transform);
    }

    .fi,
    .fi-layout,
    .fi-sidebar,
    .fi-topbar {
        border-radius: var(--app-border-radius);
    }
</style>
HTML;
        if ($bg !== '') {
            $css .= "\n<style data-app-bg>\n    body { background-color: {$bg} !important; }\n</style>\n";
        }
        if (trim($cssExtra) !== '') {
            $css .= "\n<style data-app-custom-css>\n" . $cssExtra . "\n</style>\n";
        }

        // LAPISAN GAYA HALAMAN -- sistem WordPress-style: diatur dari
        // panel kustomizer di bawah konten tiap halaman Admin, CSS di-emit
        // DI SINI (satu hook, ketiga panel + User/Welcome/Livewire/views
        // memakai hook yang sama):
        //   1. PageStyles   -- teks dasar/peran/tombol/border per-scope
        //      (key 'page_scope_styles'); keluarannya SELALU ber-induk
        //      [data-page-style="<scope>"] -- custom halaman A netral
        //      untuk halaman B.
        //   2. TextStyles   -- gaya per-field, WAJIB SETELAH #1, juga
        //      di-cakup per scope ([data-page-style] [data-field]).
        $css .= \App\Support\PageStyles\PageStyles::styles();
        $css .= \App\Support\TextStyles\TextStyles::stylesheet();

        return $css;
    }

    public static function forgetCache(): void { Cache::forget(self::CACHE_KEY); }
}
