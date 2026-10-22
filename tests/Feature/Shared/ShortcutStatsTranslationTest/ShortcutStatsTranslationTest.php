<?php

/**
 * Guard: teks kartu ShortcutStats harus benar-benar terhubung ke sistem bahasa.
 *
 * `Stat::make(__('Catalog Bunga'), ...)` terlihat sudah "terhubung", tapi kalau
 * kuncinya tidak ada di lang/<locale>.json, __() diam-diam mengembalikan
 * string aslinya -- jadi user berbahasa Inggris masih baca "Catalog Bunga",
 * dan tidak ada error apa pun yang muncul.
 *
 * Itu persis yang terjadi sebelum test ini ada: ketiga kunci baru ada di
 * kode tapi belum ada di id.json maupun en.json.
 *
 * Daftar locale diambil dari config filament-language-switcher, bukan dari
 * daftar file di /lang -- jadi kalau bahasa baru diaktifkan lewat switcher,
 * test ini langsung ikut menuntut key-nya tersedia di sana.
 */

use App\Filament\Welcome\Widgets\ShortcutStats\ShortcutStats as WelcomeShortcutStats;
use App\Support\AppPlatform\AppPlatform;
use Filament\Facades\Filament;
use Livewire\Livewire;

/**
 * Bahasa yang benar-benar bisa dipilih pengguna.
 *
 * Sengaja dibaca saat test berjalan, bukan saat file dikumpulkan: file-level
 * dieksekusi sebelum container Laravel siap, jadi `config()` di sana belum
 * terikat.
 */
function shortcutStatsActiveLocales(): array
{
    return array_keys(config('filament-language-switcher.locals', []));
}

function shortcutStatsLang(string $locale): array
{
    $path = base_path('lang/'.$locale.'.json');

    expect(is_file($path))->toBeTrue("File bahasa {$locale}.json tidak ada");

    $translations = json_decode((string) file_get_contents($path), true);

    expect(json_last_error())->toBe(JSON_ERROR_NONE, "{$locale}.json bukan JSON yang valid");

    return $translations;
}

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('welcome'));
});

afterEach(function (): void {
    AppPlatform::reset();
    Filament::setCurrentPanel(null);
});

it('kunci label kartu katalog ada di setiap bahasa yang aktif', function (): void {
    $keys = ['Catalog Bunga', 'Package Bunga', 'Katalog', 'Paket'];

    expect(shortcutStatsActiveLocales())->not->toBeEmpty('Tidak ada locale aktif di filament-language-switcher');

    foreach (shortcutStatsActiveLocales() as $locale) {
        $translations = shortcutStatsLang($locale);

        foreach ($keys as $key) {
            expect(array_key_exists($key, $translations))->toBeTrue("Kunci '{$key}' belum ada di lang/{$locale}.json");
        }
    }
});

it('label kartu benar-benar berubah saat locale diganti', function (): void {
    $keys = ['Catalog Bunga', 'Package Bunga', 'Katalog'];

    $indonesian = shortcutStatsLang('id');

    $targets = array_values(array_filter(
        shortcutStatsActiveLocales(),
        fn (string $locale): bool => $locale !== 'id'
    ));

    expect($targets)->not->toBeEmpty('Hanya Bahasa Indonesia yang aktif -- test ini jadi tidak berarti');

    foreach ($targets as $locale) {
        app()->setLocale($locale);
        config(['app.locale' => $locale]);

        foreach ($keys as $key) {
            $translated = (string) __($key);

            expect($translated)->not->toBe('')
                ->and($translated)
                ->not
                ->toBe($indonesian[$key] ?? $key, "Kunci '{$key}' belum diterjemahkan untuk locale {$locale}");
        }
    }
});

it('kartu katalog menampilkan teks sesuai locale yang aktif', function (): void {
    $indonesian = shortcutStatsLang('id');

    expect(array_key_exists('Catalog Bunga', $indonesian))->toBeTrue("Kunci 'Catalog Bunga' belum ada di lang/id.json");

    foreach (shortcutStatsActiveLocales() as $locale) {
        app()->setLocale($locale);
        config(['app.locale' => $locale]);

        $expected = (string) __('Catalog Bunga');

        expect($expected)->not->toBe('');

        Livewire::test(WelcomeShortcutStats::class)
            ->assertSee($expected);
    }
});