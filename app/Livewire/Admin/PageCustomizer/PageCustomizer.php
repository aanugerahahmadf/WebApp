<?php

namespace App\Livewire\Admin\PageCustomizer;

use App\Support\AppFont\AppFont;
use App\Support\PageStyles\PageStyles;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * KUSTOMIZER GAYA WORDPRESS: satu panel pengaturan tampilan halaman
 * publik yang muncul DI BAWAH TABEL/TIAP-KONTEN pada SETIAP halaman
 * panel admin -- termasuk SEMUA resource di app/Filament/Admin/Resources
 * (Admin, Catalog, Database Studio, Developer Studio, Discount, Help,
 * Maintenance, Order, Payment Gateway, Payment Method, Privacy Policy,
 * Reference Option, Report, Review, Terms of Service, Transaction, User,
 * Voucher, Wedding Decoration Policy, Wishlist).
 *
 * Mesin rendernya SATU render hook (`PanelsRenderHook::PAGE_END`) di
 * AdminPanelProvider memanggil view `Livewire.Admin.page-customizer.hook`
 * -- pola WordPress: satu plugin, semua halaman dapat -- TANPA satu
 * resource pun diedit manual.
 *
 * OPERASI seperti WordPress Customizer:
 *   - SETIAP select/picker punya `afterStateUpdated` yang langsung ke
 *     `PageStyles::apply()` -- perubahan TERSIMPAN begitu diganti,
 *     tanpa menunggu tombol Simpan.
 *   - Scope mengikuti halaman tempat panel dibuka (dari route aktif);
 *     bisa diganti lewat select "Halaman yang diatur".
 *   - "Reset" mengembalikan halaman aktif ke semuanya ikut tema.
 *
 * Bagian yang bisa diatur (lihat `PageStyles` untuk bentuk data):
 *   - Teks dasar: Font (searchable, katalog font lokal), Ukuran,
 *     Ketebalan, Kapital, Mode Warna (+ warna kustom).
 *   - Peran Judul/Sub Judul/Deskripsi (data-typo di halaman publik):
 *     Perataan, Ukuran, Ketebalan, Kapital -- masing-masing terpisah.
 *   - Tombol: ukuran (kecil/sedang/besar) + mode & warna.
 *   - Border view & komponen (data-chrome): gaya (solid/putus/titik),
 *     ketebalan, radius, mode & warna.
 *
 * Efek tampilan: CSS di-emit `PageStyles::styles()` via hook AppFont yang
 * dipakai SEMUA panel + Livewire + views, menarget penandaan
 * `[data-page-style="..."]`, `[data-typo="..."]`, `[data-chrome="..."]`
 * di blade storefront (Welcome/User) -- termasuk halaman detail katalog
 * dan komponen-komponennya.
 */
class PageCustomizer extends Component implements HasForms
{
    use InteractsWithForms;

    /**
     * Scope halaman yang sedang diedit. Dikirim oleh hook/prop (lihat
     * mount()); 'Locked' mencegah client mengubahnya dari wire.
     */
    #[Locked]
    public string $scope = 'catalogs';

    public ?array $data = [];

    /** @var list<string>|null peran milik halaman ini (deklarasi resource). */
    #[Locked]
    public ?array $roleSet = null;

    /** @var class-string|null resource penyandang halaman ini. */
    #[Locked]
    public ?string $resourceClass = null;

    /**
     * FIELD teks milik form resource ini -- dipanen OTOMATIS dari schema
     * form, jadi isinya SESUAI ISI (bukan cuman Head & Deskripsi).
     *
     * @return array<int, array{table: string, column: string, label: string}>
     */
    public static function styleFieldSummary(string $resourceClass): array
    {
        try {
            $livewire = new class implements \Filament\Forms\Contracts\HasForms
            {
                use \Filament\Forms\Concerns\InteractsWithForms;

                public array $data = [];
            };

            $form = $resourceClass::form(\Filament\Forms\Form::make($livewire));

            $table = (new ($resourceClass::getModel()))->getTable();

            $rows = [];
            $seen = [];

            foreach (self::flatten($form->getComponents()) as $component) {
                $isTextish = $component instanceof \Filament\Forms\Components\TextInput
                    || $component instanceof \Filament\Forms\Components\Textarea
                    || $component instanceof \Filament\Forms\Components\RichEditor;

                if (! $isTextish) {
                    continue;
                }

                $name = (string) $component->getName();

                if ($name === '' || isset($seen[$name]) || $component instanceof \Filament\Forms\Components\Hidden) {
                    continue;
                }

                $seen[$name] = true;

                $label = $component->getLabel();

                $rows[] = [
                    'table' => $table,
                    'column' => $name,
                    'label' => is_string($label) && $label !== '' ? $label : \Illuminate\Support\Str::headline($name),
                ];
            }

            // Field turunan/relasi yang dirender view publik (contoh Catalog:
            // category, price, rating, stock) bukan input form, tetapi tetap
            // bisa dideklarasikan oleh PageStyle::TEXT_FIELDS.
            $styleClass = (new \ReflectionClass($resourceClass))->getNamespaceName().'\\PageStyle';

            if (defined("{$styleClass}::TEXT_FIELDS") && is_array($styleClass::TEXT_FIELDS)) {
                foreach ($styleClass::TEXT_FIELDS as $column => $label) {
                    $column = (string) $column;

                if ($column === '' || isset($seen[$column])) {
                    continue;
                }

                    // TEXT_FIELDS may use an explicit table prefix when a
                    // resource page also customizes related records (Catalog
                    // tiers are stored in packages, not catalogs).
                    $fieldTable = $table;
                    $fieldColumn = $column;

                    if (str_contains($column, '.')) {
                        [$fieldTable, $fieldColumn] = explode('.', $column, 2);
                        $fieldTable = trim($fieldTable) ?: $table;
                        $fieldColumn = trim($fieldColumn);
                    }

                    if ($fieldColumn === '') {
                        continue;
                    }

                    $fieldKey = $fieldTable.'.'.$fieldColumn;
                    $seen[$fieldKey] = true;
                    $rows[] = [
                        'table' => $fieldTable,
                        'column' => $fieldColumn,
                        'label' => is_string($label) && $label !== ''
                            ? __($label)
                            : \Illuminate\Support\Str::headline($column),
                    ];
                }
            }

            return $rows;
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * Lipat pohon komponen Filament jadi list datar.
     */
    protected static function flatten(iterable $components): array
    {
        $out = [];

        foreach ($components as $component) {
            $out[] = $component;

            foreach (self::flatten($component->getChildComponents()) as $child) {
                $out[] = $child;
            }
        }

        return $out;
    }

    /**
     * Resource penyandang halaman ini (untuk memanen field form-nya).
     */
    protected static function currentResource(): ?string
    {
        $page = \Livewire\Livewire::current();

        if ($page === null || ! method_exists($page, 'getResource')) {
            return null;
        }

        try {
            return $page::getResource();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Resolve the Admin resource that owns a selected page scope. This keeps
     * the central customizer correct when an administrator switches from one
     * resource to another instead of leaving the fields from the original
     * page mounted.
     */
    protected static function resourceForScope(string $scope): ?string
    {
        $key = PageStyles::normalizeKey($scope);

        return PageStyles::SCOPE_RESOURCES[$key] ?? null;
    }

    public function mount(?string $scope = null): void
    {
        // Scope bawaan = route aktif (panel di bawah halaman mana pun
        // mengedit halamannya sendiri). Prop `scope` boleh memaksa --
        // halaman terpusat "Tampilan" memakai 'catalogs' (pratinjau
        // storefront publik, padanan Appearance WordPress).
        $normalized = PageStyles::normalizeKey((string) $scope);

        $this->scope = ($scope !== null && PageStyles::validPageKey($normalized))
            ? $normalized
            : PageStyles::scopeFromRequest();

        // Peran milik halaman ini -- diambil dari deklarasi resource
        // (folder resource, file PageStyle.php) lewat scope slug.
        $this->roleSet = PageStyles::rolesFor($this->scope);

        // Resource penyandang: sumber "isi form" untuk bagian
        // Gaya Teks Kolom.
        // A custom Livewire table (the Catalog tiers/packages tab) can be the
        // current component instead of the parent Filament resource page.
        // Fall back to the scope's declared resource so its PageStyle fields
        // still appear in the kustomizer.
        $this->resourceClass = self::currentResource()
            ?? self::resourceForScope($this->scope);

        $this->resetFill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Select::make('scope')
                    ->label(__('Halaman yang diatur'))
                    ->helperText(__('Setiap halaman punya pengaturannya sendiri. Panel di bawah halaman mana pun otomatis memilih halaman itu.'))
                    // SEMUA halaman boleh diatur (WordPress): daftar
                    // umum + scope halaman yang SEDANG dibuka, meski
                    // belum terdaftar di scopes().
                    ->options(
                        array_merge(
                            PageStyles::scopes(),
                            ['home' => __('Beranda')],
                            [$this->scope => \Illuminate\Support\Str::headline($this->scope)],
                        )
                    )
                    // Daftar halaman cukup panjang; jangan gunakan native
                    // browser select karena tidak menyediakan pencarian.
                    ->searchable()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function (?string $state): void {
                        if ($state !== null && $state !== '') {
                            $this->scope = $state;
                            // The selected page has its own text structure.
                            // Refresh this explicitly; otherwise Livewire
                            // keeps rendering the roles from the page that
                            // opened the customizer (for example Catalogs).
                            $this->roleSet = PageStyles::rolesFor($this->scope);
                            // Do not fall back to the resource that originally
                            // opened this component. A non-resource page such
                            // as Home/Settings must not inherit Catalog fields.
                            $this->resourceClass = self::resourceForScope($state);
                        }

                        $this->resetFill();
                    }),

                Section::make(__('Teks Halaman'))
                    ->description(__('Font, ukuran, ketebalan, kapital, dan mode warna teks dasar halaman publik.'))
                    ->collapsible()
                    ->columns(3)
                    ->schema([
                        Select::make('family')
                            ->label(__('Font'))
                            ->searchable()
                            ->native(false)
                            ->options(AppFont::families())
                            ->afterStateUpdated(fn (?string $state) => $this->store('family', $state)),

                        Select::make('size')
                            ->label(__('Ukuran Font'))
                            ->searchable()
                            ->native(false)
                            ->options(self::sizeOptions())
                            ->afterStateUpdated(fn ($state) => $this->store('size', $state)),

                        Select::make('weight')
                            ->label(__('Ketebalan'))
                            ->searchable()
                            ->native(false)
                            ->options(self::weightOptions())
                            ->afterStateUpdated(fn ($state) => $this->store('weight', $state)),

                        Select::make('align')
                            ->label(__('Perataan Font'))
                            ->searchable()
                            ->native(false)
                            ->options(self::alignOptions())
                            ->afterStateUpdated(fn (?string $state) => $this->store('align', $state)),

                        Select::make('transform')
                            ->label(__('Kapital'))
                            ->searchable()
                            ->native(false)
                            ->options(self::transformOptions())
                            ->afterStateUpdated(fn (?string $state) => $this->store('transform', $state)),

                        Select::make('line_height')
                            ->label(__('Tinggi Baris Teks'))
                            ->searchable()
                            ->native(false)
                            ->options(self::lineHeightOptions())
                            ->afterStateUpdated(fn ($state) => $this->store('line_height', $state !== null ? (is_numeric($state) ? (float) $state : 0.0) : 0.0)),

                        Select::make('letter_spacing')
                            ->label(__('Spasi Huruf'))
                            ->searchable()
                            ->native(false)
                            ->options(self::letterSpacingOptions())
                            ->afterStateUpdated(fn ($state) => $this->store('letter_spacing', is_numeric($state) ? (int) $state : 0)),

                        Select::make('color_mode')
                            ->label(__('Mode Warna Font'))
                            ->searchable()
                            ->native(false)
                            ->options(self::colorModeOptions())
                            ->afterStateUpdated(fn (?string $state) => $this->store('color_mode', $state)),

                        Select::make('bg_color_mode')
                            ->label(__('Latar Halaman'))
                            ->searchable()
                            ->native(false)
                            ->options(self::bgColorModeOptions())
                            ->afterStateUpdated(fn (?string $state) => $this->store('bg_color_mode', $state)),

                        ColorPicker::make('bg_color')
                            ->label(__('Warna Latar Kustom'))
                            ->visible(fn (callable $get): bool => $get('bg_color_mode') === 'custom')
                            ->afterStateUpdated(fn ($state) => $this->store('bg_color', $state)),

                        ColorPicker::make('color')
                            ->label(__('Warna Kustom'))
                            ->visible(fn (callable $get): bool => $get('color_mode') === 'custom')
                            ->afterStateUpdated(fn ($state) => $this->store('color', $state)),
                    ]),

                Section::make(__('Teks Berperan'))
                    ->description(__('Perataan, ukuran, ketebalan, dan kapital per JENIS teks halaman ini. Daftar jenisnya mengikuti STRUKTUR HALAMAN masing-masing (Head 1 / Head 2 / Head 3 / Deskripsi) -- bukan template gabungan.'))
                    ->collapsible()
                    ->columns(4)
                    ->schema(
                        // CLOSURE: daftar peran di-evaluasi ulang tiap
                        // render/di-evaluasi Forms -- ganti scope lewat
                        // select di atas, fieldset-nya ikut berubah
                        // mengikuti struktur halaman yang dipilih.
                        fn (): array => array_map(
                            fn (string $role): Fieldset => Fieldset::make(PageStyles::roleLabel($role))
                                ->columns(3)
                                ->schema(self::roleFields($role)),
                            $this->roleSet ?? PageStyles::rolesFor($this->scope),
                        )
                    ),

                Section::make(__('Gaya Teks Kolom (sesuai isi form)'))
                    ->description(__('Satu baris gaya untuk SETIAP field teks di form resource ini -- diambil otomatis dari schema form, dikirim ke halaman publik lewat penanda data-field.'))
                    ->collapsible()
                    ->columnSpanFull()
                    ->schema(
                        fn (): array => array_map(
                            fn (array $row): Section => \App\Filament\Shared\Typography\TypographyControls::fieldStylesSection(
                                $this->scope,
                                $row['table'],
                                $row['column'],
                            )->compact()->collapsed(),
                            $this->resourceClass !== null
                                ? self::styleFieldSummary($this->resourceClass)
                                : []
                        )
                    ),

                Section::make(__('Tombol'))
                    ->description(__('Ukuran tombol aksi (Order now / Add to Cart), warna, dan radius sudutnya.'))
                    ->collapsible()
                    ->columns(3)
                    ->schema([
                        Select::make('button_size')
                            ->label(__('Ukuran Tombol'))
                            ->searchable()
                            ->native(false)
                            ->options(self::buttonSizeOptions())
                            ->afterStateUpdated(fn (?string $state) => $this->store('button_size', $state)),

                        Select::make('button_color_mode')
                            ->label(__('Mode Warna Tombol'))
                            ->searchable()
                            ->native(false)
                            ->options(self::colorModeOptions())
                            ->afterStateUpdated(fn (?string $state) => $this->store('button_color_mode', $state)),

                        Select::make('button_radius')
                            ->label(__('Radius Tombol'))
                            ->searchable()
                            ->native(false)
                            ->options(self::borderRadiusOptions())
                            ->afterStateUpdated(fn ($state) => $this->store('button_radius', is_numeric($state) ? (int) $state : 0)),

                        ColorPicker::make('button_color')
                            ->label(__('Warna Kustom Tombol'))
                            ->dehydrated()
                            ->visible(fn (callable $get): bool => $get('button_color_mode') === 'custom')
                            ->afterStateUpdated(fn ($state) => $this->store('button_color', $state)),
                    ]),

                Section::make(__('Border, Bingkai & Bayangan'))
                    ->description(__('Border kartu, bingkai galeri, panel section -- gaya garis, ketebalan, radius, warna, dan bayangan lembut.'))
                    ->collapsible()
                    ->columns(3)
                    ->schema([
                        Select::make('border_style')
                            ->label(__('Gaya Border'))
                            ->searchable()
                            ->native(false)
                            ->options(self::borderStyleOptions())
                            ->afterStateUpdated(fn (?string $state) => $this->store('border_style', $state)),

                        Select::make('border_width')
                            ->label(__('Ketebalan Border'))
                            ->searchable()
                            ->native(false)
                            ->options(self::borderWidthOptions())
                            ->afterStateUpdated(fn ($state) => $this->store('border_width', $state)),

                        Select::make('border_radius')
                            ->label(__('Radius Border'))
                            ->searchable()
                            ->native(false)
                            ->options(self::borderRadiusOptions())
                            ->afterStateUpdated(fn ($state) => $this->store('border_radius', $state)),

                        Select::make('shadow')
                            ->label(__('Bayangan Kartu'))
                            ->searchable()
                            ->native(false)
                            ->options(self::shadowOptions())
                            ->afterStateUpdated(fn (?string $state) => $this->store('shadow', $state ?? '')),

                        Select::make('border_color_mode')
                            ->label(__('Mode Warna Border'))
                            ->searchable()
                            ->native(false)
                            ->options(self::colorModeOptions())
                            ->afterStateUpdated(fn (?string $state) => $this->store('border_color_mode', $state)),

                        ColorPicker::make('border_color')
                            ->label(__('Warna Kustom Border'))
                            ->dehydrated()
                            ->visible(fn (callable $get): bool => $get('border_color_mode') === 'custom')
                            ->afterStateUpdated(fn ($state) => $this->store('border_color', $state)),
                    ]),
            ]);
    }

    /**
     * Field per-peran (Perataan/Size/Weight/Kapital) -- dipakai fieldset
     * Judul / Sub Judul / Deskripsi. Penyimpanan langsung via
     * `PageStyles::apply()` (WordPress-style tanpa tombol).
     *
     * @return array<int, \Filament\Forms\Components\Select>
     */
    protected static function roleFields(string $role): array
    {
        $store = fn (string $key, mixed $state) => PageStyles::apply(
            self::currentScope(),
            [$key => $state],
        );

        return [
            Select::make("{$role}_align")
                ->label(__('Perataan'))
                ->searchable()
                ->native(false)
                ->options(self::alignOptions())
                ->afterStateUpdated(fn (?string $state) => $store("{$role}_align", $state)),

            Select::make("{$role}_size")
                ->label(__('Ukuran'))
                ->searchable()
                ->native(false)
                ->options(self::sizeOptions())
                ->afterStateUpdated(fn ($state) => $store("{$role}_size", $state)),

            Select::make("{$role}_weight")
                ->label(__('Ketebalan'))
                ->searchable()
                ->native(false)
                ->options(self::weightOptions())
                ->afterStateUpdated(fn ($state) => $store("{$role}_weight", $state)),

            Select::make("{$role}_transform")
                ->label(__('Kapital'))
                ->searchable()
                ->native(false)
                ->options(self::plainTransformOptions())
                ->afterStateUpdated(fn (?string $state) => $store("{$role}_transform", $state)),
        ];
    }

    /**
     * Simpan langsung (WordPress-style): satu perubahan select = satu
     * patch tersimpan; cache halaman publik membersih sendiri di mesin.
     */
    public function store(string $key, mixed $state): void
    {
        PageStyles::apply($this->scope, [$key => $state]);

        // Pratinjau langsung (halaman "Tampilan") memuat ulang iframe
        // halaman publik setiap kali pengaturan berubah -- khas WordPress.
        $this->dispatch('page-style-updated');
    }

    /** Reset semua pengaturan halaman ini ke ikut tema. */
    public function resetPage(): void
    {
        PageStyles::reset($this->scope);
        $this->resetFill();

        $this->dispatch('page-style-updated');

        Notification::make()
            ->title(__('Tampilan halaman ini dikembalikan ke tema'))
            ->success()
            ->send();
    }

    /** Simpan eksplisit seluruh bagian (untuk admin yang suka tombol). */
    public function save(): void
    {
        $data = $this->form->getState();
        unset($data['scope']);

        PageStyles::apply($this->scope, array_filter($data, fn ($v) => $v !== null && $v !== []));

        $this->dispatch('page-style-updated');

        Notification::make()
            ->title(__('Pengaturan tampilan berhasil disimpan'))
            ->body(__('Buka halaman publik untuk melihat hasilnya.'))
            ->success()
            ->send();
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('Livewire.Admin.page-customizer.page-customizer-component');
    }

    // ------------------------------------------------------------------
    // Bantuan
    // ------------------------------------------------------------------

    /**
     * @return array<string, string|int>
     */
    protected function values(): array
    {
        return PageStyles::get($this->scope);
    }

    /** Form diisi ulang dari mesin (+ scope aktif). */
    protected function resetFill(): void
    {
        $this->form->fill($this->values() + ['scope' => $this->scope]);
    }

    /**
     * Scope komponen Livewire yang sedang LIVE -- dipakai fieldset per-
     * peran (statis konteksnya), Livewire punya "current".
     */
    protected static function currentScope(): string
    {
        $current = \Livewire\Livewire::current();

        return ($current instanceof self) ? $current->scope : 'catalogs';
    }

    /** @return array<int|string, string> */
    protected static function sizeOptions(): array
    {
        $options = [0 => __('Ikuti Tema')];

        foreach (PageStyles::VALID_SIZES as $size) {
            $options[$size] = "{$size} px";
        }

        return $options;
    }

    /** @return array<int|string, string> */
    protected static function weightOptions(): array
    {
        return [
            0   => __('Ikuti Tema'),
            300 => __('Tipis (300)'),
            400 => __('Biasa (400)'),
            500 => __('Medium (500)'),
            600 => __('Semi Bold (600)'),
            700 => __('Bold (700)'),
        ];
    }

    /** @return array<int|string, string> */
    protected static function alignOptions(): array
    {
        return [
            '' => __('Ikuti Tema'),
            'left' => __('Rata Kiri'),
            'center' => __('Rata Tengah'),
            'right' => __('Rata Kanan'),
            'justify' => __('Rata Kanan Kiri'),
        ];
    }

    /** @return array<int|string, string> */
    protected static function plainTransformOptions(): array
    {
        return [
            '' => __('Ikuti Tema'),
            'uppercase' => __('Semua Kapital'),
            'lowercase' => __('Semua Kecil'),
            'capitalize' => __('Kapital di Awal Kata'),
        ];
    }

    /** @return array<int|string, string> */
    protected static function transformOptions(): array
    {
        return ['none' => __('Ikuti Tema')] + self::plainTransformOptions();
    }

    /** @return array<int|string, string> */
    protected static function borderWidthOptions(): array
    {
        $options = [0 => __('Ikuti Tema')];

        foreach (PageStyles::VALID_WIDTHS as $width) {
            $options[$width] = "{$width} px";
        }

        return $options;
    }

    /** @return array<int|string, string> */
    protected static function borderRadiusOptions(): array
    {
        $options = [0 => __('Ikuti Tema')];

        foreach (PageStyles::VALID_RADII as $radius) {
            $options[$radius] = "{$radius} px";
        }

        return $options;
    }

    /** @return array<int|string, string> */
    protected static function borderStyleOptions(): array
    {
        return [
            ''        => __('Ikuti Tema'),
            'solid'   => __('Garis Penuh'),
            'dashed'  => __('Garis Putus'),
            'dotted'  => __('Titik-Titik'),
        ];
    }

    /** @return array<int|string, string> */
    protected static function buttonSizeOptions(): array
    {
        return [
            ''   => __('Ikuti Tema'),
            'sm' => __('Kecil'),
            'md' => __('Sedang'),
            'lg' => __('Besar'),
        ];
    }

    /** @return array<int|string, string> */
    protected static function colorModeOptions(): array
    {
        return [
            'theme'  => __('Ikuti Tema'),
            'light'  => __('Mode Terang'),
            'dark'   => __('Mode Gelap'),
            'custom' => __('Warna Kustom'),
        ];
    }

    /** @return array<float, string> */
    protected static function lineHeightOptions(): array
    {
        $options = [0 => __('Ikuti Tema')];

        foreach (PageStyles::VALID_LINE_HEIGHTS as $value) {
            $options[$value] = rtrim(rtrim((string) number_format($value, 2, '.', ''), '0'), '.');
        }

        return $options;
    }

    /** @return array<int, string> */
    protected static function letterSpacingOptions(): array
    {
        $options = [0 => __('Ikuti Tema')];

        foreach (PageStyles::VALID_LETTER_SPACINGS as $value) {
            $options[$value] = ($value > 0 ? "+{$value}px" : (string) $value);
        }

        return $options;
    }

    /** @return array<string, string> */
    protected static function bgColorModeOptions(): array
    {
        return [
            'theme'  => __('Ikuti Tema'),
            'light'  => __('Putih Bersih'),
            'dark'   => __('Gelap Pekat'),
            'custom' => __('Warna Kustom'),
        ];
    }

    /** @return array<string, string> */
    protected static function shadowOptions(): array
    {
        return [
            ''   => __('Ikuti Tema'),
            'sm' => __('Tipis (sm)'),
            'md' => __('Sedang (md)'),
            'lg' => __('Tebal (lg)'),
        ];
    }
}
