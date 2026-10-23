<?php

/*
 * MESIN kustomisasi tampilan per-halaman (`App\Support\PageStyles`).
 *
 * Baca ARSITEKTURnya di docblock kelas: penyimpanan peta per
 * scope di `fm_app_settings.key = 'page_scope_styles'` (record
 * TERPISAH dari kustom global milik AppPageStyle yang menempati key
 * `page_styles` -- keduanya menimpa record utuh, jadi key dibeda-bedakan),
 * whitelist ketat, dan CSS emit `[data-page-style]` / `[data-typo]` /
 * `[data-chrome]`.
 */

use App\Models\FmAppSetting\FmAppSetting;
use App\Support\AppFont\AppFont;
use App\Support\PageStyles\PageStyles;

beforeEach(function (): void {
    FmAppSetting::query()->where('key', 'page_scope_styles')->delete();
    PageStyles::forgetCache();
});

afterEach(function (): void {
    FmAppSetting::query()->where('key', 'page_scope_styles')->delete();
    PageStyles::forgetCache();
});

it('get() mengembalikan bentuk netral penuh saat scope belum diatur', function (): void {
    $style = PageStyles::get('catalogs');

    expect($style['family'])->toBe('')
        ->and($style['size'])->toBe(0)
        ->and($style['weight'])->toBe(0)
        ->and($style['transform'])->toBe('none')
        ->and($style['color_mode'])->toBe('theme')
        ->and($style['heading_align'])->toBe('')
        ->and($style['subheading_align'])->toBe('')
        ->and($style['description_align'])->toBe('')
        ->and($style['button_size'])->toBe('')
        ->and($style['border_style'])->toBe('')
        ->and($style['border_width'])->toBe(0)
        ->and($style['border_radius'])->toBe(0);
});

it('apply() menyimpan patch dan stylesheet men-target penandaan scope', function (): void {
    PageStyles::apply('catalogs', [
        'family' => 'Roboto',
        'size' => 18,
        'weight' => 600,
        'transform' => 'uppercase',
        'color_mode' => 'custom',
        'color' => '#112233',
        'heading_align' => 'center',
        'heading_size' => 32,
        'heading_weight' => 700,
        'heading_transform' => 'uppercase',
        'subheading_align' => 'right',
        'description_align' => 'justify',
        'button_size' => 'lg',
        'button_color_mode' => 'custom',
        'button_color' => '#6b21a8',
        'border_style' => 'dashed',
        'border_width' => 3,
        'border_radius' => 16,
        'border_color_mode' => 'custom',
        'border_color' => '#332211',
    ]);

    $saved = FmAppSetting::query()->where('key', 'page_scope_styles')->sole()->value;

    expect($saved['catalogs']['family'])->toBe('Roboto')
        ->and($saved['catalogs']['size'])->toBe(18)
        ->and($saved['catalogs']['button_size'])->toBe('lg');

    $css = PageStyles::styles();

    expect($css)->toContain('data-page-style="catalogs"')
        ->and($css)->toContain('font-family: \'Roboto\'')
        ->and($css)->toContain('font-size: 18px')
        ->and($css)->toContain('font-weight: 600')
        ->and($css)->toContain('text-transform: uppercase')
        ->and($css)->toContain('color: #112233')
        ->and($css)->toContain('[data-chrome="catalogs.button"]')
        ->and($css)->toContain('font-size: 1.05rem') // aturan lg Tombol
        ->and($css)->toContain('[data-chrome="catalogs.border"]')
        ->and($css)->toContain('border-style: dashed')
        ->and($css)->toContain('border-width: 3px')
        ->and($css)->toContain('border-radius: 16px')
        ->and($css)->toContain('border-color: #332211')
        ->and($css)->toContain('[data-typo="heading"]')
        ->and($css)->toContain('text-align: center');

    // Peran footer/side selector (subheading/description).
    expect($css)->toContain('text-align: right')
        ->and($css)->toContain('text-align: justify');
});

it('nilai di luar whitelist dibuang; tak ada CSS untuk sampah', function (): void {
    PageStyles::apply('catalogs', [
        'size' => 99999,          // di luar whitelist -> 0
        'family' => 'Comic Sans', // bukan dari katalog font -> ''
        'color' => 'malicious;">',  // bukan hex -> ''
        'border_style' => 'zig-zag', // bukan solid/dashed/dotted -> ''
        'button_size' => 'xxl',   // bukan sm/md/lg -> ''
    ]);

    expect(PageStyles::get('catalogs')['size'])->toBe(0)
        ->and(PageStyles::get('catalogs')['family'])->toBe('')
        ->and(PageStyles::get('catalogs')['color'])->toBe('')
        ->and(PageStyles::get('catalogs')['border_style'])->toBe('')
        ->and(PageStyles::get('catalogs')['button_size'])->toBe('')
        // Tidak ada penyimpanan sama sekali karena semuanya netral:
        ->and(PageStyles::all())->toBe([])
        ->and(PageStyles::styles())->toBe('');
});

it('halaman yang kembali netral dihapus dari peta', function (): void {
    PageStyles::apply('catalogs', ['family' => 'Roboto']);
    expect(PageStyles::all())->toHaveKey('catalogs');

    PageStyles::apply('catalogs', ['family' => '']);

    expect(PageStyles::all())->not->toHaveKey('catalogs');
    expect(PageStyles::get('catalogs')['family'])->toBe('');
});

it('reset() menghapus scope dari peta', function (): void {
    PageStyles::apply('catalogs', ['family' => 'Roboto']);

    PageStyles::reset('catalogs');

    expect(PageStyles::all())->toBe([]);
});

it('scope key validasi: karakter terlarang ditolak', function (): void {
    PageStyles::apply('<script>alert(1)</script>', ['family' => 'Roboto']);

    // normalizeKey membosankan menghapus karakter ilegal; scope key tanpa
    // sudah aman + key scope RUSAK tidak boleh masuk peta.
    expect(PageStyles::all())->toBe([]);
});

it('scope dari route: tanpa route aktif jadi "home"', function (): void {
    // Halaman publik/Livewire update tidak punya nama route panel; scope
    // fallback aman = 'home' (TIDAK pernah scope Random halamannya).
    expect(PageStyles::scopeFromRequest())->toBe('home');
});
