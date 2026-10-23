<?php

namespace App\Support\PageStyles;

use App\Models\FmAppSetting\FmAppSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * MESIN KUSTOMISASI TAMPILAN PER-HALAMAN -- operasinya seperti
 * WordPress (Appearance/Customizer): pengaturan tersimpan di TEMPAT
 * satu, diatur dari panel admin, dan CSS hasilnya di-emit lewat SATU
 * hook (`AppFont::styles()`) sehingga Panel User, Welcome, Livewire,
 * dan blade views memakai hook yang sama ikut mewarisi -- tanpa
 * menyentuh satu file tampilan pun.
 *
 * PENYIMPANAN: SATU record `fm_app_settings.key = 'page_scope_styles'`
 * (RECORD TERPISAH: `AppPageStyle` menempati key `page_styles` untuk
 * tombol/border global dan keduanya menimpa record utuh -- dicampur
 * akan saling menghapus data). Cache sendiri: `app_page_scope_styles`.
 *
 * KONSUMEN:
 *   - `App\Livewire\Admin\PageCustomizer\PageCustomizer` -- panel
 *     kustomizer di bawah konten SETIAP halaman panel Admin (hook
 *     `PanelsRenderHook::PAGE_END` di AdminPanelProvider) + halaman
 *     terpusat "Tampilan". Perubahan select langsung ke `apply()`
 *     (tanpa menunggu simpan, seperti WordPress).
 *
 * PENYIMPANAN: satu record `fm_app_settings.key = 'page_styles'`,
 * peta per key halaman (scope). Key scope valid = [a-z][a-z0-9_-]{0,63}
 * (mis. `catalogs`, `helps`, `home`) -- TIDAK ada daftar scope
 * hardcode di mesin: halaman mana pun boleh kustomisasi halamannya
 * sendiri, dan CSS hanya menimpa elemen yang memang ditandai
 * `data-page-style="<key>"` di blade -- scope tanpa penandaan aman
 * tersimpan tanpa efek samping.
 *
 * BENTUK DATA satu scope:
 *
 *     "catalogs" => [
 *         // Teks dasar halaman
 *         'family' => 'Roboto' | '',  'size' => px | 0,
 *         'weight' => 300..700 | 0,   'transform' => 'none|uppercase|capitalize|lowercase',
 *         'color_mode' => 'theme|light|dark|custom',
 *         'color'  => '#hex' (dipakai saat 'custom'),
 *
 *         // Perataan/ukuran/tebal/kapital per PERAN dalam halaman ini
 *         // (heading & subheading & description di penandaan data-typo)
 *         'heading_align' => '',  'heading_size' => 0,  'heading_weight' => 0,  'heading_transform' => '',
 *         'subheading_align' => ..., 'subheading_size' => ..., 'subheading_weight' => ..., 'subheading_transform' => ...,
 *         'description_align' => ..., 'description_size' => ..., 'description_weight' => ..., 'description_transform' => ...,
 *
 *         // Tombol utama (Order now / Add to Cart)
 *         'button_size' => ''|'sm'|'md'|'lg', 'button_color_mode' => ..., 'button_color' => '#hex',
 *
 *         // Border view + komponen ("Border Views supaya rapih")
 *         'border_width' => px | 0, 'border_style' => ''|'solid'|'dashed'|'dotted',
 *         'border_radius' => px | 0,
 *         'border_color_mode' => 'theme|light|dark|custom', 'border_color' => '#hex',
 *     ],
 *
 * Nilai kosong (0/''/'theme') = ikut tema -- field yang belum diatur
 * tidak menghasilkan CSS. Halaman yang kembali netral semua bahkan
 * dihapus dari peta. Nilai di luar whitelist DIBUANG (pola AppFont:
 * satu nilai buruk tidak boleh mematikan halaman).
 *
 * SELEKTOR yang di-emit `styles()` (penandaan di blade storefront):
 *   [data-page-style="<key>"]                  -- teks dasar halaman
 *   [data-page-style="<key>"] [data-typo="heading|subheading|description"]
 *                                              -- peran Judul/Sub Judul/Deskripsi
 *   [data-chrome="<key>.button"]               -- tombol aksi utama
 *   [data-chrome="<key>.border"]               -- kartu/frame (Border Views)
 */
class PageStyles
{
    private const CACHE_KEY = 'app_page_scope_styles';
    private const CACHE_TTL = 60;

    /** Warna teks semantik light/dark untuk mode warna di luar tema. */
    public const LIGHT_COLOR = '#374151';
    public const DARK_COLOR  = '#e5e7eb';

    public const VALID_SIZES          = [11, 12, 13, 14, 15, 16, 17, 18, 20, 22, 24, 26, 28, 32, 36, 40, 48, 56];
    public const VALID_WEIGHTS        = [300, 400, 500, 600, 700];
    public const VALID_ALIGNS         = ['left', 'center', 'right', 'justify'];
    public const VALID_TRANSFORMS     = ['uppercase', 'lowercase', 'capitalize'];
    public const VALID_COLOR_MODES    = ['theme', 'light', 'dark', 'custom'];
    public const VALID_BUTTON_SIZES   = ['sm', 'md', 'lg'];
    public const VALID_BORDER_STYLES  = ['solid', 'dashed', 'dotted'];
    public const VALID_WIDTHS         = [1, 2, 3, 4, 6, 8];
    public const VALID_RADII          = [0, 4, 8, 12, 16, 20, 24, 32];

    /** Kenop TAMBAHAN (grade lengkap) yang juga diatur per halaman. */
    public const VALID_LINE_HEIGHTS    = [1.0, 1.2, 1.4, 1.5, 1.6, 1.8, 2.0];
    public const VALID_LETTER_SPACINGS = [0, 1, 2, 3, 5];
    public const VALID_SHADOWS         = ['', 'sm', 'md', 'lg'];

    /**
     * Peran teks dalam halaman yang punya pengaturan terpisah.
     *
     * `heading` = Judul utama (Head 1). `heading2`/`heading3` = judul
     * tingkat lanjut -- TIDAK semua halaman punya; masing-masing halaman
     * mendeklarasikan sendiri peran apa saja yang hidupnya (lihat
     * ROLES_BY_SCOPE). Silinder whitelist VALIDATOR tetap mengenal semua
     * di atas supaya peta nilai scope APAPUN lolos desain yang sama.
     */
    public const ROLES = ['heading', 'subheading', 'description', 'heading2', 'heading3'];

    /**
     * STRUKTUR TEKS PER HALAMAN -- beda-beda sebenarnya:
     *
     *   - Ada halaman cukup Head 1 lalu langsung Deskripsi;
     *   - Ada yang berjenjang Head 1, Head 2, Head 3, dan Deskripsi.
     *
     * SATU peta di sini (sisi Admin) mendeklarasikannya UNTUK SEMUA
     * halaman -- resource admin (scope = slug resource), storefront
     * Welcome/User, dan halaman Livewire: form kustomizer DI BAWAH
     * TABEL tiap halaman merender fieldset SESUAI DAFTAR DI SINI --
     * formnya satu-satu, bukan template gabungan. Halaman yang tidak
     * terdaftar memakai bentuk default (Head 1 + Deskripsi).
     */
    public const ROLES_BY_SCOPE = [
        // Halaman NON-resource (di-g-vari dari hook, bukan folder resource):
        'home' => ['heading', 'subheading', 'description'],
    ];

    /**
     * PEMETAAN scope (slug) -> resource penyandang. KONSEN ARAH: ISI
     * (daftar peran) tetap hidup di FOLDER resource masing-masing
     * (file PageStyle.php di samping file resource); pemetaan di sini
     * HANYA penunjuk jalan supaya scope yang diitemukan hook PAGE_END
     * mengetahui file mana yang harus dibaca. Alien tabrakan dipinang
     * bersamaan di PAGE_SLUG_SCOPES.
     */
    public const SCOPE_RESOURCES = [
        'catalogs'                  => \App\Filament\Admin\Resources\CatalogResource\CatalogResource::class,
        'flowerdecorationscatalog'  => \App\Filament\Admin\Resources\CatalogResource\CatalogResource::class,
        'flower-decorations-catalog' => \App\Filament\Admin\Resources\CatalogResource\CatalogResource::class,
        'orders'                    => \App\Filament\Admin\Resources\OrderResource\OrderResource::class,
        'transactions'              => \App\Filament\Admin\Resources\TransactionResource\TransactionResource::class,
        'reports'                   => \App\Filament\Admin\Resources\ReportResource\ReportResource::class,
        'reviews'                   => \App\Filament\Admin\Resources\ReviewResource\ReviewResource::class,
        'users'                     => \App\Filament\Admin\Resources\UserResource\UserResource::class,
        'admins'                    => \App\Filament\Admin\Resources\AdminResource\AdminResource::class,
        'wishlists'                 => \App\Filament\Admin\Resources\WishlistResource\WishlistResource::class,
        'vouchers'                  => \App\Filament\Admin\Resources\VoucherResource\VoucherResource::class,
        'discounts'                 => \App\Filament\Admin\Resources\DiscountResource\DiscountResource::class,
        'carts'                     => \App\Filament\Admin\Resources\OrderResource\OrderResource::class,
        'histories'                 => \App\Filament\Admin\Resources\TransactionResource\TransactionResource::class,
        'maintenances'             => \App\Filament\Admin\Resources\MaintenanceResource\MaintenanceResource::class,
        'helps'                     => \App\Filament\Admin\Resources\HelpResource\HelpResource::class,
        'help'                      => \App\Filament\Admin\Resources\HelpResource\HelpResource::class,
        'payment-gateways'          => \App\Filament\Admin\Resources\PaymentGatewayResource\PaymentGatewayResource::class,
        'payment-methods'           => \App\Filament\Admin\Resources\PaymentMethodResource\PaymentMethodResource::class,
        'reference-options'         => \App\Filament\Admin\Resources\ReferenceOptionResource\ReferenceOptionResource::class,
        'database-studios'          => \App\Filament\Admin\Resources\DatabaseStudioResource\DatabaseStudioResource::class,
        'developer-studios'         => \App\Filament\Admin\Resources\DeveloperStudioResource\DeveloperStudioResource::class,
        'privacies'                 => \App\Filament\Admin\Resources\PrivacyPolicyResource\PrivacyPolicyResource::class,
        'privacy-policy'            => \App\Filament\Admin\Resources\PrivacyPolicyResource\PrivacyPolicyResource::class,
        'terms'                     => \App\Filament\Admin\Resources\TermsOfServiceResource\TermsOfServiceResource::class,
        'terms-of-service'          => \App\Filament\Admin\Resources\TermsOfServiceResource\TermsOfServiceResource::class,
        'wedding-decoration-policy' => \App\Filament\Admin\Resources\WeddingDecorationPolicyResource\WeddingDecorationPolicyResource::class,
        'wedding-decoration-policies' => \App\Filament\Admin\Resources\WeddingDecorationPolicyResource\WeddingDecorationPolicyResource::class,
    ];

    /**
     * BACA deklarasi peran teks dari FOLDER resource itu (PageStyle
     * file, teks peran TIDAK dituliskan di mesin ini).
     *
     * @param  class-string  $resourceClass
     * @return list<string>|null
     */
    public static function roleSetForResource(string $resourceClass): ?array
    {
        try {
            $styleClass = (new \ReflectionClass($resourceClass))->getNamespaceName().'\PageStyle';

            if (! defined("{$styleClass}::TEXT_ROLES")) {
                return null;
            }

            $roles = $styleClass::TEXT_ROLES;

            if (! is_array($roles) || $roles === []) {
                return null;
            }

            $roles = array_values(array_intersect($roles, self::ROLES));

            return $roles !== [] ? $roles : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Peran yang hidup di satu halaman (scope): baca deklarasi di
     * FOLDER resource penyandang lewat SCOPE_RESOURCES; kalau tidak
     * ada (halaman bukan resource: Home, dsb.) pakai fallback
     * ROLES_BY_SCOPE / bentuk default.
     *
     * @return list<string>
     */
    public static function rolesFor(string $scope): array
    {
        $key = self::normalizeKey($scope);

        if (isset(self::SCOPE_RESOURCES[$key])) {
            $declared = self::roleSetForResource(self::SCOPE_RESOURCES[$key]);

            if ($declared !== null) {
                return $declared;
            }
        }

        if (isset(self::ROLES_BY_SCOPE[$key])) {
            return self::ROLES_BY_SCOPE[$key];
        }

        return ['heading', 'description'];
    }

    /**
     * Label yang manis satu operasi semua panen (kustomizer, dan mesin
     * CSS admin) menulis satu-sama.
     */
    public static function roleLabel(string $role): string
    {
        return match ($role) {
            'heading'     => __('Judul Utama (Head 1)'),
            'heading2'    => __('Judul Tingkat 2 (Head 2)'),
            'heading3'    => __('Judul Tingkat 3 (Head 3)'),
            'subheading'  => __('Sub Judul'),
            'description' => __('Deskripsi'),
            default       => \Illuminate\Support\Str::headline($role),
        };
    }

    /**
     * Kelas header Filament untuk tiap peran di halaman ADMIN/panel --
     * Custom UI khas semua resource tanpa mengedit satu file pun:
     * judul halaman (fi-header-heading), judul section
     * (fi-section-header-heading), dan deskripsi section
     * (fi-section-header-description).
     */
    private const ROLE_ADMIN_SELECTORS = [
        'heading'     => '.fi-header-heading',
        'subheading'  => '.fi-section-header-heading',
        'description' => '.fi-section-header-description',
    ];

    /**
     * Pintu scope KEDUA: penanda render-hook `[data-page-style-mount]`
     * (view scope-marker di hook PAGE_END tiap panel) yang hadir
     * OTOMATIS di halaman mana pun -- WordPress: page apapun,
     * resource apapun, tanpa mengedit blade-nya satu file pun.
     */
    protected static function mountSelector(string $pageKey): string
    {
        return 'body:has([data-page-style-mount="' . $pageKey . '"])';
    }

    /**
     * Rules ukuran tombol (font + padding) -- dari konvensi Filament
     * button kecil/sedang/besar, tanpa membangun ulang Tailwind.
     *
     * @return array<string, string>
     */
    public static function buttonSizeRules(): array
    {
        return [
            'sm' => 'font-size: 0.8rem !important; padding: 0.35rem 0.7rem !important;',
            'md' => 'font-size: 0.9rem !important; padding: 0.55rem 0.95rem !important;',
            'lg' => 'font-size: 1.05rem !important; padding: 0.75rem 1.3rem !important;',
        ];
    }

    /**
     * Key scope DAFTAR UI (kustomizer terpusat): SEMUA resource + page
     * panel storefront (Welcome) dan panel Admin -- setiap halaman
     * punya scope-nya sendiri (WordPress-style), PLUS scope yang sudah
     * PERNATUR disimpan di database.
     *
     * Daftar runtime panel dilipat try/catch: form kustomizer boleh
     * dibangun sebelum panel siap (tests) -- ладangnya fallback ke
     * scope yang tersimpan saja.
     *
     * @return array<string, string>
     */
    public static function scopes(): array
    {
        $scopes = ['home' => __('Beranda')];

        try {
            foreach (['welcome', 'admin'] as $panelId) {
                $panel = \Filament\Facades\Filament::getPanel($panelId);

                foreach ([...$panel->getResources(), ...$panel->getPages()] as $entry) {
                    try {
                        $slug   = self::scopeFromSlug((string) $entry::getSlug());
                        $label  = (string) ($entry::getNavigationLabel() ?? $entry::getTitle());

                        if ($label !== '' && ! isset($scopes[$slug])) {
                            $scopes[$slug] = $label;
                        }
                    } catch (\Throwable $e) {
                        continue;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Panel belum siap: list minimal tetap bisa dibangun.
        }

        foreach (self::all() as $key => $style) {
            $scopes[$key] ??= $key;
        }

        ksort($scopes);

        return $scopes;
    }

    /**
     * Bentuk netral satu halaman: semuanya ikut tema.
     *
     * @return array<string, string|int>
     */
    public static function netrals(): array
    {
        return array_merge([
            'family' => '', 'size' => 0, 'weight' => 0, 'align' => '',
            'transform' => 'none', 'color_mode' => 'theme', 'color' => '',
            // Kenop TAMBAHAN: tinggi baris, spasi huruf, latar bayangan.
            'line_height' => 0.0, 'letter_spacing' => 0,
            'bg_color_mode' => 'theme', 'bg_color' => '',
        ], array_merge(
            ...array_map(fn (string $role): array => [
                "{$role}_align" => '',
                "{$role}_size" => 0,
                "{$role}_weight" => 0,
                "{$role}_transform" => '',
            ], self::ROLES)
        ), [
            'button_size' => '', 'button_color_mode' => 'theme', 'button_color' => '',
            'button_radius' => 0,
            'border_width' => 0, 'border_style' => '', 'border_radius' => 0,
            'border_color_mode' => 'theme', 'border_color' => '',
            // Bayangan kartu/section (chrome border target juga).
            'shadow' => '',
        ]);
    }

    // ------------------------------------------------------------------
    // API kustomizer (baca / simpan / reset)
    // ------------------------------------------------------------------

    /**
     * Style satu halaman, selalu bentuk lengkap dengan nilai netral.
     *
     * @return array<string, string|int>
     */
    public static function get(string $pageKey): array
    {
        return array_merge(self::netrals(), self::all()[self::normalizeKey($pageKey)] ?? []);
    }

    /**
     * Patch sebagian properti satu halaman -- dipanggil dari
     * `afterStateUpdated` kustomizer, jadi perubahan LANGSUNG
     * tersimpan (operasinya seperti WordPress).
     *
     * @param array<string, mixed> $patch
     */
    public static function apply(string $pageKey, array $patch): void
    {
        $pageKey = self::normalizeKey($pageKey);

        if (! self::validPageKey($pageKey)) {
            return;
        }

        $current    = self::all()[$pageKey] ?? [];
        $whitelist  = self::netrals();

        foreach ($patch as $k => $v) {
            if (is_string($k) && array_key_exists($k, $whitelist)) {
                $current[$k] = $v;
            }
        }

        $clean = self::validate($current);

        $map = self::rawAll();
        $map = is_array($map) ? $map : [];

        if ($clean === null) {
            unset($map[$pageKey]);
        } else {
            $map[$pageKey] = $clean;
        }

        self::store($map);
    }

    /** Reset halaman ke netral (hapus dari peta). */
    public static function reset(string $pageKey): void
    {
        $pageKey = self::normalizeKey($pageKey);

        $map = self::rawAll();

        if (array_key_exists($pageKey, $map)) {
            unset($map[$pageKey]);
            self::store($map);
        }
    }

    /**
     * Scope dari nama route aktif -- dipakai hook kustomizer supaya
     * panel di bawah SETIAP halaman admin mengedit scope halaman itu
     * (WordPress-style: konteks mengikuti halaman, tanpa konfigurasi).
     *
     *   filament.admin.resources.catalogs.index   -> catalogs
     *   filament.admin.pages.font-settings        -> font-settings
     *   di luar panel admin (fallback)            -> home
     */
    public static function scopeFromRequest(): string
    {
        $name = (string) (\Illuminate\Support\Facades\Route::currentRouteName() ?? '');

        if (preg_match('/\.(?:resources|pages)\.([a-z0-9-]+)(?:\.|$)/i', $name, $m)) {
            return self::scopeFromSlug($m[1]);
        }

        return 'home';
    }

    /**
     * Slug halaman resource admin -> key scope yang dipakai penandaan
     * blade (`data-page-style="..."`). SATU PETA, satu sumber: custom
     * halaman A menulis ke scope A, dan hanya penandaan blade DENGAN
     * key yang sama yang menerima CSS-nya -- scope lain netral penuh.
     *
     *   'flowerdecorationscatalog' (panel User) -> catalogs
     *   'catalogs' (panel Admin)                -> catalogs (sama)
     */
    public const PAGE_SLUG_SCOPES = [
        'flowerdecorationscatalog' => 'catalogs',
    ];

    public static function scopeFromSlug(string $slug): string
    {
        $slug = self::normalizeKey($slug);

        if ($slug === '') {
            return 'home';
        }

        // ATURAN SATU-MAP: hanya slug khusus yang dipetakan DEMI kembali
        // ke scope yang sama. Slug lain DIPERTAHANKAN apa adanya -- slug
        // YM beda = scope beda (halaman A TIDAK pernah menghalaman B).
        return self::PAGE_SLUG_SCOPES[$slug] ?? $slug;
    }

    /**
     * key scope: lowercase, spasi -> dash, buang karakter ganjil.
     * Karakter TERLARANG (di luar huruf/angka/spasi/dash/underscore)
     * membuat key GUGUR SELURUHNYA ('' bukan hasil potong-membedah)
     * -- key rusak tidak boleh sampai masuk peta.
     */
    public static function normalizeKey(string $key): string
    {
        $key = trim($key);

        if ($key === '' || preg_match('/[^A-Za-z0-9_ -]/', $key) === 1) {
            return '';
        }

        return trim(str_replace(' ', '-', strtolower($key)), '-_ ');
    }

    public static function validPageKey(string $key): bool
    {
        return (bool) preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $key);
    }

    // ------------------------------------------------------------------
    // Penyimpanan (cache 60s, pola AppFont)
    // ------------------------------------------------------------------

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected static function rawAll(): array
    {
        try {
            if (! Schema::hasTable('fm_app_settings')) {
                return [];
            }

            $setting = FmAppSetting::where('key', 'page_scope_styles')->first();

            return ($setting && is_array($setting->value)) ? $setting->value : [];
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * Timpa peta scope. Record & cache mengikuti AppPageStyle polanya,
     * tapi key & cache terpisah (lihat docblock kelas).
     *
     * @param array<string, array<string, mixed>> $map
     */
    protected static function store(array $map): void
    {
        $setting = FmAppSetting::firstOrNew(['key' => 'page_scope_styles']);
        $setting->value = $map;
        $setting->save();

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Semua peta yang valid.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): array {
                $out = [];

                foreach (self::rawAll() as $pageKey => $style) {
                    if (! is_array($style) || ! is_string($pageKey) || ! self::validPageKey($pageKey)) {
                        continue;
                    }

                    $clean = self::validate($style);

                    if ($clean !== null) {
                        $out[$pageKey] = $clean;
                    }
                }

                return $out;
            });
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    // ------------------------------------------------------------------
    // Validasi
    // ------------------------------------------------------------------

    /**
     * Bersihkan satu style halaman. `null` = semuanya netral (entri
     * dibuang dari peta).
     *
     * @param  array<string, mixed>  $style
     * @return array<string, string|int>|null
     */
    protected static function validate(array $style): ?array
    {
        $clean = [];
        $dirt  = false;

        foreach (self::netrals() as $key => $fallback) {
            $raw  = $style[$key] ?? null;
            $bcd = match (true) {
                str_contains($key, 'color_mode')  => self::pick($raw, self::VALID_COLOR_MODES, 'theme'),
                str_contains($key, '_transform')  => self::pick($raw, array_merge([''], self::VALID_TRANSFORMS), is_string($fallback) ? (string) $fallback : ''),
                $key === 'transform'              => self::pick($raw, array_merge(['none'], self::VALID_TRANSFORMS), 'none'),
                $key === 'family'                 => self::pickFamily($raw),
                str_contains($key, 'border_style') => self::pick($raw, array_merge([''], self::VALID_BORDER_STYLES), ''),
                str_contains($key, '_align')      => self::pick($raw, array_merge([''], self::VALID_ALIGNS), ''),
                $key === 'align'                    => self::pick($raw, array_merge([''], self::VALID_ALIGNS), ''),
                str_contains($key, 'button_size') => self::pick($raw, array_merge([''], self::VALID_BUTTON_SIZES), ''),
                $key === 'shadow'                 => self::pick($raw, self::VALID_SHADOWS, ''),
                $key === 'line_height'            => self::pickFloat($raw, self::VALID_LINE_HEIGHTS, 0.0),
                str_contains($key, 'button_radius') => self::pickInt($raw, self::VALID_RADII),
                str_contains($key, 'letter_spacing') => self::pickInt($raw, self::VALID_LETTER_SPACINGS),
                str_contains($key, '_weight')     => self::pickInt($raw, self::VALID_WEIGHTS),
                $key === 'weight'                 => self::pickInt($raw, self::VALID_WEIGHTS),
                str_contains($key, 'border_width') => self::pickInt($raw, self::VALID_WIDTHS),
                str_contains($key, 'border_radius') => self::pickInt($raw, self::VALID_RADII),
                str_ends_with($key, '_size')      => self::pickInt($raw, self::VALID_SIZES),
                $key === 'size'                   => self::pickInt($raw, self::VALID_SIZES),
                str_ends_with($key, '_color')     => self::pickHex($raw),
                $key === 'color'                  => self::pickHex($raw),
                default                           => null,
            };

            $clean[$key] = $bcd ?? $fallback;

            if ((string) $clean[$key] !== (string) $fallback) {
                $dirt = true;
            }
        }

        return $dirt ? $clean : null;
    }

    /** Nilai dari whitelist, else fallback. */
    protected static function pick(mixed $raw, array $allowed, string|int $fallback): string|int
    {
        return is_string($raw) && in_array($raw, $allowed, true) ? $raw : $fallback;
    }

    /** Float (line-height) dari whitelist; fallback 0.0 = ikut tema. */
    protected static function pickFloat(mixed $raw, array $allowed, float $fallback): float
    {
        $raw = is_numeric($raw) ? (float) $raw : 0.0;

        if ($raw <= 0.0) {
            return $fallback;
        }

        foreach ($allowed as $value) {
            if (abs($value - $raw) < 0.001) {
                return $value;
            }
        }

        return $fallback;
    }

    /**
     * Angka dari whitelist. 0 = "ikut tema" (netral): untuk weight/
     * ukuran/width/radius 0 dan null maknanya sama di CSS.
     */
    protected static function pickInt(mixed $raw, array $allowed): int
    {
        $raw = is_numeric($raw) ? (int) $raw : 0;

        if ($raw <= 0) {
            return 0;
        }

        return in_array($raw, $allowed, true) ? $raw : 0;
    }

    /** Family: whitelist katalog font lokal (AppFont). */
    protected static function pickFamily(mixed $raw): string
    {
        if (! is_string($raw) || $raw === '') {
            return '';
        }

        return in_array($raw, \App\Support\AppFont\AppFont::families(), true) ? $raw : '';
    }

    /** Hex 3/6 digit saja; tanpa itu buang nilainya. */
    protected static function pickHex(mixed $raw): string
    {
        if (! is_string($raw) || $raw === '') {
            return '';
        }

        if (preg_match('/^#(?:[0-9a-fA-F]{6}|[0-9a-fA-F]{3})$/', $raw)) {
            return strtolower($raw);
        }

        if (preg_match('/^(?:[0-9a-fA-F]{6}|[0-9a-fA-F]{3})$/', $raw)) {
            return '#'.strtolower($raw);
        }

        return '';
    }

    // ------------------------------------------------------------------
    // Stylesheet (emit via AppFont::styles() -- ketiga panel + publik)
    // ------------------------------------------------------------------

    public static function styles(): string
    {
        $rules = [];

        foreach (self::all() as $pageKey => $s) {
            $legacy  = '[data-page-style="' . $pageKey . '"]';
            $mount   = self::mountSelector($pageKey);

            // --- Teks dasar halaman ---
            $rootDecls = self::baseDeclarations($s);

            if ($rootDecls !== '') {
                // DUA pintu scope: penandaan eksplisit di blade
                // [data-page-style] DAN penanda render-hook
                // [data-page-style-mount] yang otomatis hadir di
                // SETIAP halaman ketiga panel.
                $rules[$legacy . ', ' . $mount] = $rootDecls;
            }

            // --- Peran: Judul / Sub Judul / Deskripsi ---
            foreach (self::ROLES as $role) {
                $decls = [];

                if (($s["{$role}_align"] ?? '') !== '') {
                    $decls[] = 'text-align: ' . $s["{$role}_align"] . ' !important;';
                }

                if ((int) ($s["{$role}_size"] ?? 0) > 0) {
                    $decls[] = 'font-size: ' . (int) $s["{$role}_size"] . 'px !important;';
                }

                if ((int) ($s["{$role}_weight"] ?? 0) > 0) {
                    $decls[] = 'font-weight: ' . (int) $s["{$role}_weight"] . ' !important;';
                }

                if (($s["{$role}_transform"] ?? '') !== '') {
                    $decls[] = 'text-transform: ' . $s["{$role}_transform"] . ' !important;';
                }

                if ($decls !== []) {
                    // Storefront: penandaan [data-typo] di blade. Panel/
                    // resource mana pun: kelas header Filament (const
                    // ROLE_ADMIN_SELECTORS) -- tanpa edit file halaman.
                    $selectors = [
                        $legacy . ' [data-typo="' . $role . '"]',
                        $mount . ' [data-typo="' . $role . '"]',
                    ];

                    $admin = self::ROLE_ADMIN_SELECTORS[$role] ?? null;

                    if ($admin !== null) {
                        $selectors[] = $mount . ' ' . $admin;
                    }

                    $rules[implode(', ', $selectors)] = implode(' ', $decls);
                }
            }

            // --- Tombol utama (ukuran & warna) ---
            $btnDecls = [];

            $bsize = (string) ($s['button_size'] ?? '');

            if ($bsize !== '' && isset(self::buttonSizeRules()[$bsize])) {
                $btnDecls[] = self::buttonSizeRules()[$bsize];
            }

            if ((int) ($s['button_radius'] ?? 0) > 0) {
                $btnDecls[] = 'border-radius: ' . (int) $s['button_radius'] . 'px !important;';
            }

            $bmode = $s['button_color_mode'] ?? 'theme';

            if ($bmode === 'custom' && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $s['button_color'])) {
                $bhex = strtolower((string) $s['button_color']);
                $btnDecls[] = 'background-color: ' . $bhex . ' !important;';
                $btnDecls[] = 'border-color: ' . $bhex . ' !important;';
                $btnDecls[] = 'color: ' . self::contrastColor($bhex) . ' !important;';
            } elseif ($bmode === 'light') {
                $btnDecls[] = 'background-color: #e5e7eb !important;';
                $btnDecls[] = 'border-color: #e5e7eb !important;';
                $btnDecls[] = 'color: #111827 !important;';
            } elseif ($bmode === 'dark') {
                $btnDecls[] = 'background-color: #1f2937 !important;';
                $btnDecls[] = 'border-color: #1f2937 !important;';
                $btnDecls[] = 'color: #e5e7eb !important;';
            }

            if ($btnDecls !== []) {
                // Tombol Filament (.fi-btn) + elemen bertanda
                // [data-chrome] di blade (CTA storefront).
                $rules['[data-chrome="' . $pageKey . '.button"], ' . $mount . ' .fi-btn'] = implode(' ', $btnDecls);
            }

            // --- Border view & komponen ("Border Views supaya rapih") ---
            $brdDecls = [];

            if ((int) ($s['border_width'] ?? 0) > 0 && ($s['border_style'] ?? '') !== '') {
                $brdDecls[] = 'border-width: ' . (int) $s['border_width'] . 'px !important;';
                $brdDecls[] = 'border-style: ' . $s['border_style'] . ' !important;';
            }

            if ((int) ($s['border_radius'] ?? 0) > 0) {
                $brdDecls[] = 'border-radius: ' . (int) $s['border_radius'] . 'px !important;';
            }

            $bbmode = $s['border_color_mode'] ?? 'theme';

            if ($bbmode === 'custom' && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $s['border_color'])) {
                $brdDecls[] = 'border-color: ' . strtolower((string) $s['border_color']) . ' !important;';
            }

            // BAYANGAN kartu/section dari kustomizer.
            $shadow = (string) ($s['shadow'] ?? '');

            if ($shadow !== '' && isset(self::shadowRules()[$shadow])) {
                $brdDecls[] = self::shadowRules()[$shadow];
            }

            if ($brdDecls !== []) {
                // Kartu section Filament (.fi-section -- semua resource
                // punya) + elemen bertanda [data-chrome] di blade.
                $rules['[data-chrome="' . $pageKey . '.border"], ' . $mount . ' .fi-section'] = implode(' ', $brdDecls);
            }
        }

        if ($rules === []) {
            return '';
        }

        $out = "\n<!-- App\\Support\\PageStyles: kustomisasi tampilan per halaman (panel kustomizer admin) -->\n";
        $out .= "<style data-app-page-styles>\n";

        foreach ($rules as $selectors => $decls) {
            $out .= "    {$selectors} {\n        " . rtrim($decls) . "\n    }\n";
        }

        $out .= "</style>\n";

        return $out;
    }

    /**
     * Deklarasi teks dasar satu halaman (family/size/weight/transform/
     * line-height/letter-spacing/warna/latar), string kosong kalau semua
     * default.
     */
    protected static function baseDeclarations(array $s): string
    {
        $decls = [];

        if (($s['family'] ?? '') !== '') {
            $decls[] = 'font-family: \'' . $s['family'] . '\', sans-serif !important;';
        }

        if ((int) ($s['size'] ?? 0) > 0) {
            $decls[] = 'font-size: ' . (int) $s['size'] . 'px !important;';
        }

        if ((int) ($s['weight'] ?? 0) > 0) {
            $decls[] = 'font-weight: ' . (int) $s['weight'] . ' !important;';
        }

        if (($s['align'] ?? '') !== '') {
            $decls[] = 'text-align: ' . $s['align'] . ' !important;';
        }

        $lh = (float) ($s['line_height'] ?? 0);

        if ($lh > 0) {
            $decls[] = 'line-height: ' . rtrim(rtrim(number_format($lh, 2, '.', ''), '0'), '.') . ' !important;';
        }

        if ((int) ($s['letter_spacing'] ?? 0) > 0) {
            $decls[] = 'letter-spacing: ' . (int) $s['letter_spacing'] . 'px !important;';
        }

        if (($s['transform'] ?? 'none') !== 'none') {
            $decls[] = 'text-transform: ' . $s['transform'] . ' !important;';
        }

        $mode = $s['color_mode'] ?? 'theme';

        if ($mode === 'custom' && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $s['color'])) {
            $decls[] = 'color: ' . strtolower((string) $s['color']) . ' !important;';
        } elseif ($mode === 'light') {
            $decls[] = 'color: ' . self::LIGHT_COLOR . ' !important;';
        } elseif ($mode === 'dark') {
            $decls[] = 'color: ' . self::DARK_COLOR . ' !important;';
        }

        // LATAR halaman (mode warna + kustom).
        $bgMode = $s['bg_color_mode'] ?? 'theme';

        if ($bgMode === 'custom' && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $s['bg_color'])) {
            $decls[] = 'background-color: ' . strtolower((string) $s['bg_color']) . ' !important;';
        } elseif ($bgMode === 'light') {
            $decls[] = 'background-color: #ffffff !important;';
        } elseif ($bgMode === 'dark') {
            $decls[] = 'background-color: #111827 !important;';
        }

        return implode(' ', $decls);
    }

    /**
     * CSS box-shadow satu tingkat (chrome kartu/section). '' = ikut tema.
     */
    public static function shadowRules(): array
    {
        return [
            'sm' => 'box-shadow: 0 1px 2px rgba(0, 0, 0, 0.10) !important;',
            'md' => 'box-shadow: 0 4px 12px rgba(0, 0, 0, 0.14) !important;',
            'lg' => 'box-shadow: 0 12px 28px rgba(0, 0, 0, 0.20) !important;',
        ];
    }

    /**
     * Teks putih di atas warna gelap, gelap di atas warna terang -- dipakai
     * tombol dengan latar kustom supaya labelnya selalu terbaca.
     */
    public static function contrastColor(string $hex): string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (strlen($hex) !== 6) {
            return '#ffffff';
        }

        [$r, $g, $b] = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
        $brightness = ($r * 299 + $g * 587 + $b * 114) / 1000;

        return $brightness > 150 ? '#111827' : '#ffffff';
    }
}
