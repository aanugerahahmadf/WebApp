<?php

use App\Models\FmAppSetting\FmAppSetting;
use App\Support\AppFont\AppFont;
use App\Support\PageStyles\PageStyles;
use App\Support\TextStyles\TextStyles;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;

/*
 * GAYA TEKS PER-FIELD YANG BER-SCOPE.
 *
 * Aturan UTAMA (permintaan pengguna): custom halaman A HARUS hanya
 * mengubah halaman A. Makanya kunci penyimpanannya
 * `<scope>:<tabel>.<kolom>` dan CSS yang di-emit SELALU di-cakup
 *
 *     [data-page-style="catalogs"] [data-field="catalogs.name"]
 *
 * -- pengecualian per-field dijamin mati di halaman lain.
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    FmAppSetting::query()->whereIn('key', ['text_styles', 'page_scope_styles'])->delete();
    Cache::forget('app_text_styles');
    PageStyles::forgetCache();
    TextStyles::forgetCache();
});

afterEach(function (): void {
    FmAppSetting::query()->whereIn('key', ['text_styles', 'page_scope_styles'])->delete();
    Cache::forget('app_text_styles');
    PageStyles::forgetCache();
    TextStyles::forgetCache();
});

test('perubahan select gaya langsung tersimpan per scope tanpa menyentuh kolom model', function (): void {
    TextStyles::apply('catalogs', 'catalogs', 'name', [
        'family'     => 'Roboto',
        'size'       => 20,
        'weight'     => 700,
        'align'      => 'center',
        'transform'  => 'uppercase',
        'color_mode' => 'light',
    ]);

    $stored = FmAppSetting::textStyles();

    expect($stored['catalogs:catalogs.name'])->toBe([
        'family'     => 'Roboto',
        'size'       => 20,
        'weight'     => 700,
        'align'      => 'center',
        'color_mode' => 'light',
        'transform'  => 'uppercase',
    ]);
});

test('field dibuang dari peta ketika semua nilainya ikut tema', function (): void {
    TextStyles::apply('catalogs', 'catalogs', 'name', ['family' => 'Roboto']);
    TextStyles::apply('catalogs', 'catalogs', 'name', ['family' => '']);

    expect(FmAppSetting::textStyles())->toBe([])
        ->and(TextStyles::get('catalogs', 'catalogs', 'name')['family'])->toBe('');
});

test('custom scope A tidak menyokong scope B dengan kolom yang sama', function (): void {
    TextStyles::apply('catalogs', 'catalogs', 'name', ['size' => 32]);

    // scope 'home' dengan kolom sama: NETRAL, tidak menangkap apa pun.
    expect(TextStyles::get('home', 'catalogs', 'name')['size'])->toBe(0)
        ->and(TextStyles::stylesheet())->toContain('[data-page-style="catalogs"] [data-field="catalogs.name"]')
        ->and(str_contains(TextStyles::stylesheet(), '[data-page-style="home"]'))->toBeFalse();
});

test('nilai di luar whitelist dibuang, tidak menembus css', function (): void {
    TextStyles::apply('catalogs', 'catalogs', 'name', [
        'family'     => 'Comic Sans Bahaya</style>',
        'size'       => 999,
        'weight'     => 3500,
        'align'      => 'diagonal',
        'transform'  => 'shouting',
        'color_mode' => 'neon',
    ]);

    expect(TextStyles::get('catalogs', 'catalogs', 'name'))->toBe(TextStyles::neutrals())
        ->and(FmAppSetting::textStyles())->toBe([])
        ->and(TextStyles::stylesheet())->toBe('');
});

test('gaya per-field di-emit setelah css per-scope (menang cascade)', function (): void {
    PageStyles::apply('catalogs', ['heading_transform' => 'uppercase']);
    TextStyles::apply('catalogs', 'catalogs', 'name', ['size' => 20]);

    $full = AppFont::styles();

    expect($full)->toContain('data-app-page-styles')
        ->and($full)->toContain('data-app-text-styles')
        ->and(strpos($full, 'data-app-page-styles') < strpos($full, 'data-app-text-styles'))->toBeTrue();
});

test('fieldKey attrs satu sumber untuk penandaan blade', function (): void {
    expect(TextStyles::fieldKey((new \App\Models\Catalog\Catalog)->getTable(), 'name'))->toBe('catalogs.name')
        ->and(TextStyles::attrs((new \App\Models\Catalog\Catalog)->getTable(), 'name'))
        ->toBe('data-field="catalogs.name"');
});
