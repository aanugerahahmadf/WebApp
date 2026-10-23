<?php

use App\Filament\Shared\Typography\TypographyControls;
use App\Models\FmAppSetting\FmAppSetting;
use App\Support\PageStyles\PageStyles;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;

/*
 * SECTION TIPOGRAFI DI FORM RESOURCE -- BER-SCOPE PER HALAMAN.
 *
 * Aturan UTAMA yang dijaga suite ini (permintaan pengguna, gaprak-paknya
 * keras): CUSTOM HALAMAN A HARUS hanya mengubah halaman A. Makanya:
 *
 *   - Section "Tipografi Halaman Ini" dipasang di ATAS input form
 *     resource (semua turunan HasModelColumns + Admin/Catalog/
 *     DeveloperStudio yang menulis form manual), dan SEMUA select-nya
 *     menulis ke PageStyles::apply($scope, ...) -- scope = halaman
 *     resource itu sendiri (slug -> key), bukan penyimpanan global.
 *   - CSS hasilnya SELALU ber-induk [data-page-style="<scope>"]:
 *     scope lain -- halaman lain -- tidak menerima satu baris pun.
 *   - Control TIDAK menyimpan apa pun ke kolom model (dehydrated(false)).
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    FmAppSetting::query()->whereIn('key', ['page_scope_styles', 'text_styles', 'typography'])->delete();
    PageStyles::forgetCache();
    \App\Support\TextStyles\TextStyles::forgetCache();
});

afterEach(function (): void {
    FmAppSetting::query()->whereIn('key', ['page_scope_styles', 'text_styles', 'typography'])->delete();
    PageStyles::forgetCache();
    \App\Support\TextStyles\TextStyles::forgetCache();
});

/**
 * Lipat pohon component jadi flat map: key = nama statePath, value =
 * komponennya.
 *
 * @param  iterable<Component>  $components
 * @return array<string, Component>
 */
function flatScopedFields(iterable $components): array
{
    $flat = [];

    foreach ($components as $component) {
        foreach (flatScopedFields($component->getChildComponents()) as $name => $deeper) {
            $flat[$name] = $deeper;
        }

        if ($component instanceof Select && $component->getName() !== null) {
            $flat[$component->getName()] = $component;
        }
    }

    return $flat;
}

/**
 * Flat fields dari form sebuah resource admin.
 *
 * @param  class-string  $resource
 * @return array<string, Component>
 */
function resourceScopedFields(string $resource): array
{
    // ComponentContainer::make() butuh HasForms apa pun -- stub anonim.
    $livewire = new class implements \Filament\Forms\Contracts\HasForms
    {
        use \Filament\Forms\Concerns\InteractsWithForms;

        public array $data = [];
    };

    $form = $resource::form(Form::make($livewire));

    return flatScopedFields($form->getComponents());
}

test('deklarasi peran tiap halaman HIDUP DI FOLDER resource masing-masing', function (): void {
    // ISI = file PageStyle.php di samping resource -- bukan peta mesin.
    expect(PageStyles::roleSetForResource(\App\Filament\Admin\Resources\CatalogResource\CatalogResource::class))
        ->toBe(['heading', 'subheading', 'description'])
        ->and(PageStyles::roleSetForResource(\App\Filament\Admin\Resources\HelpResource\HelpResource::class))
        ->toBe(['heading', 'subheading', 'heading2', 'heading3', 'description'])
        ->and(file_exists(__DIR__.'/../../../../app/Filament/Admin/Resources/CatalogResource/PageStyle.php'))
        ->toBeTrue();

    // Scope menunjuk folder resource penyandangnya:
    expect(PageStyles::rolesFor('catalogs'))
        ->toBe(PageStyles::roleSetForResource(\App\Filament\Admin\Resources\CatalogResource\CatalogResource::class))
        ->and(PageStyles::rolesFor('helps'))
        ->toBe(PageStyles::roleSetForResource(\App\Filament\Admin\Resources\HelpResource\HelpResource::class))
        // Peran di luar whitelist gugur diam-diam, boleh peran dihapus
        // dari deklarasi oleh mesin tanpa mecah halaman:
        ->and(PageStyles::rolesFor('scope-tak-terdaftar'))
        ->toBe(['heading', 'description']);
});

test('form kustomizer tiap halaman membawa peran sesuai struktur halamannya', function (): void {
    // Halaman UTUH (Head 1 + Sub Judul + Deskripsi):
    $fields = flatScopedFields([TypographyControls::section('catalogs')]);

    foreach (PageStyles::rolesFor('catalogs') as $role) {
        foreach ([
            "{$role}_align", "{$role}_size", "{$role}_weight", "{$role}_transform",
        ] as $key) {
            expect(array_key_exists($key, $fields))->toBeTrue("field {$key} hilang");

            /** @var Select $component */
            $component = $fields[$key];
            expect($component->isSearchable())->toBeTrue()
                ->and($component->isDehydrated())->toBeFalse();
        }
    }

    // Halaman BERJENJANG (Head 1..Head 3): punya Sub Judul, Head 2 & Head 3.
    $longform = flatScopedFields([TypographyControls::section('help')]);

    expect(array_key_exists('heading2_align', $longform))->toBeTrue()
        ->and(array_key_exists('heading3_weight', $longform))->toBeTrue();

    // Semua resource sekarang juga memiliki Sub Judul yang terscope sendiri.
    $simple = flatScopedFields([TypographyControls::section('orders')]);

    expect(array_key_exists('heading_align', $simple))->toBeTrue()
        ->and(array_key_exists('description_size', $simple))->toBeTrue()
        ->and(array_key_exists('subheading_align', $simple))->toBeTrue();
});

test('isi panel Gaya Teks Kolom dipanen dari ISI form resource', function (): void {
    // Resource Catalog: form-nya punya name + description (text/textarea)
    // -- panel di atas table harus membawa SATU baris gaya untuk itu.
    $rows = \App\Livewire\Admin\PageCustomizer\PageCustomizer::styleFieldSummary(
        \App\Filament\Admin\Resources\CatalogResource\CatalogResource::class
    );

    $columns = array_column($rows, 'column');

    expect($rows)->not->toBeEmpty()
        ->and($columns)->toContain('name')
        ->and($columns)->toContain('description')
        // Setiap baris membawa tabel & label terselibung rapi:
        ->and(array_column($rows, 'table'))->toBe(array_fill(0, count($rows), (new \App\Models\Catalog\Catalog)->getTable()));

    // Resource IDENTITAS (Admin): form-nya punya full_name + username
    // -- dua-duanya harus ikut. Inilah "sesuai isi" bukan template.
    $adminRows = \App\Livewire\Admin\PageCustomizer\PageCustomizer::styleFieldSummary(
        \App\Filament\Admin\Resources\AdminResource\AdminResource::class
    );

    expect(array_column($adminRows, 'column'))->toContain('full_name');
});

test('tiga resource dengan form manual TIDAK menyuntik gaya ke dalam form', function (): void {
    // ATURAN: kustomisasi tampilan TIDAK tinggal di halaman form --
    // panelnya hidup DI ATAS TABEL list resource (hook PAGE_START).
    // Form murni kolom model, tanpa section tipografi/gaya.
    foreach ([
        \App\Filament\Admin\Resources\AdminResource\AdminResource::class,
        \App\Filament\Admin\Resources\CatalogResource\CatalogResource::class,
        \App\Filament\Admin\Resources\DeveloperStudioResource\DeveloperStudioResource::class,
    ] as $resource) {
        $fields = resourceScopedFields($resource);

        expect(array_key_exists('heading_align', $fields))
            ->toBeFalse("{$resource} masih membawa section gaya di dalam form");
    }
});

test('custom halaman catalogs hanya menyentuh scopes catalogs -- scope lain TIDAK terkenal', function (): void {
    // Custom halaman A (catalogs).
    PageStyles::apply('catalogs', [
        'heading_align'    => 'justify',
        'heading_size'     => 28,
        'heading_weight'   => 700,
        'heading_transform' => 'uppercase',
        'subheading_align' => 'right',
        'description_align' => 'center',
    ]);

    // Halaman B (home) NETRAL penuh -- tidak menangkap apa pun.
    expect(PageStyles::get('home')['heading_align'])->toBe('')
        ->and(PageStyles::get('home')['heading_size'])->toBe(0)
        ->and(FmAppSetting::where('key', 'page_scope_styles')->first()?->value['home'] ?? null)
            ->toBeNull('scope halaman lain TIDAK boleh ikut kena');

    // CSS: semua aturan ber-induk data-page-style="catalogs" -- tidak ada
    // aturan dengan selector [data-page-style="home"].
    $css = PageStyles::styles();

    expect($css)->toContain('[data-page-style="catalogs"]')
        ->toContain('text-align: justify !important;')
        ->toContain('text-align: right !important;')
        ->toContain('text-align: center !important;')
        ->toContain('text-transform: uppercase !important;')
        ->and(str_contains($css, '[data-page-style="home"]'))->toBeFalse();
});

test('nilai di luar whitelist jatuh ke ikuti tema -- tidak masuk ke css dari mana pun', function (): void {
    PageStyles::apply('catalogs', [
        'heading_size'     => 999,
        'heading_align'    => 'diagonal',
        'heading_weight'   => 3500,
        'heading_transform' => 'shouting',
        'family'           => 'Comic Sans Bahaya</style>',
    ]);

    $style = PageStyles::get('catalogs');

    expect($style['heading_size'])->toBe(0)
        ->and($style['heading_align'])->toBe('')
        ->and($style['heading_weight'])->toBe(0)
        ->and($style['heading_transform'])->toBe('')
        ->and($style['family'])->toBe('');

    // Tidak ada teks segar dari input yang lolos ke CSS.
    expect(PageStyles::styles())->not->toContain('Bahaya');
});

test('simpan vila endpoint: scope lebih tegas daripada asal-asalan', function (): void {
    // scope yang normalisasi: uppercase/space -> dash.
    expect(PageStyles::scopeFromSlug('Flower Decorations Catalog'))->toBe('flower-decorations-catalog')
        // slug panel User yang memetakan ke scope storefront yang sama.
        ->and(PageStyles::scopeFromSlug('flowerdecorationscatalog'))->toBe('catalogs')
        // halaman tanpa slug -> fallback netral.
        ->and(PageStyles::scopeFromSlug(''))->toBe('home');
});
