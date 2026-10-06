<?php

/**
 * Guard: label navigasi panel harus mengikuti language switcher.
 *
 * Bug yang dijaga di sini: panel dibangun oleh middleware `panel:user`, yang
 * jalan SEBELUM SetLocale. Kalau label ditulis sebagai `->label(__('Pengaturan'))`
 * -- yang memanggil __() saat itu juga -- maka string-nya dibekukan memakai
 * locale default (APP_LOCALE=id). Locale 'en' memang baru disetel belakangan
 * oleh SetLocale, tapi label sudah jadi string biasa dan tidak lagi berubah: di
 * UK user tetap melihat "Pengaturan" walaupun lang/en.json sudah punya
 * "Settings".
 *
 * MenuItem::getLabel() memanggil evaluate(), jadi label harus lewat CLOSURE
 * supaya dievaluasi ulang saat render -- yaitu setelah SetLocale sempat jalan.
 *
 * Test ini sengaja memakai instance panel yang sudah dibangun lalu mengganti
 * locale, meniru urutan middleware di request sebenarnya.
 */

use Filament\Facades\Filament;

/**
 * Source file dengan komentar dibuang.
 *
 * Penting: komentar di provider memang sengaja menyebut `->label(__('...'))`
 * sebagai contoh salah. Tanpa dibuang dulu, regex akan menandai file itu
 * sebagai salah padahal yang salah adalah baris komentar, bukan kode.
 */
function panelProviderCode(string $path): string
{
    $source = (string) file_get_contents(base_path($path));

    // Blok /* ... */ lalu baris // ...
    $source = preg_replace('#/\*.*?\*/#s', '', $source) ?? $source;

    return implode("\n", array_filter(
        array_map(
            fn (string $line): string => preg_replace('#^\s*//.*$#', '', $line) ?? $line,
            explode("\n", $source)
        ),
        fn (string $line): bool => trim($line) !== ''
    ));
}

function panelUserMenuLabel(string $panelId, string $key, string $locale): string
{
    $panel = Filament::getPanel($panelId);

    app()->setLocale($locale);
    config(['app.locale' => $locale]);

    return (string) $panel->getUserMenuItems()[$key]->getLabel();
}

it('label user menu mengikuti locale yang aktif', function (string $key, string $id, string $en): void {
    expect(panelUserMenuLabel('user', $key, 'en'))->toBe($en);
    expect(panelUserMenuLabel('user', $key, 'id'))->toBe($id);
})->with([
    ['pengaturan', 'Pengaturan', 'Settings'],
    ['riwayat', 'Riwayat', 'History'],
    ['ulasan', 'Ulasan Saya', 'My Review'],
    ['privacy', 'Privasi & Ketentuan', 'Privacy & Terms'],
    ['bantuan', 'Pusat Bantuan', 'Help Center'],
]);

it('label SignOut tetap "Sign Out" di kedua bahasa', function (): void {
    // Dikunci lewat RedirectsLogoutToWelcomeHome::SIGN_OUT_LABEL, sengaja tidak
    // ikut diterjemahkan agar sama di mana pun -- jadi harus TIDAK berubah
    // saat locale diganti.
    expect(panelUserMenuLabel('user', 'logout', 'en'))->toBe('Sign Out')
        ->and(panelUserMenuLabel('user', 'logout', 'id'))->toBe('Sign Out');
});

it('tidak ada label navigasi panel yang dibekukan sebagai string', function (string $path): void {
    // Menjaga agar tidak ada yang menuliskannya lagi sebagai string biasa.
    // Pengecualian: ->signOutMenuItem() memang literal (dikunci "Sign Out").
    $code = panelProviderCode($path);

    preg_match_all('/->label\(\s*__\(/', $code, $matches);

    expect($matches[0])->toBe([], "{$path} masih punya ->label(__('...')) eager");
})->with([
    'app/Providers/Filament/UserPanelProvider/UserPanelProvider.php',
    'app/Providers/Filament/AdminPanelProvider/AdminPanelProvider.php',
    'app/Providers/Filament/WelcomePanelProvider/WelcomePanelProvider.php',
]);

it('navigation group label juga closure di ketiga panel', function (string $path): void {
    // NavigationGroup::make()->label(__('...')) punya masalah yang sama.
    $code = panelProviderCode($path);

    preg_match_all('/NavigationGroup::make\(\)->label\(\s*__\(/', $code, $matches);

    expect($matches[0])->toBe([], "{$path} punya NavigationGroup dengan label eager");
})->with([
    'app/Providers/Filament/UserPanelProvider/UserPanelProvider.php',
    'app/Providers/Filament/AdminPanelProvider/AdminPanelProvider.php',
    'app/Providers/Filament/WelcomePanelProvider/WelcomePanelProvider.php',
]);