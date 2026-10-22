<?php

/**
 * Label sidebar TIDAK BOLEH dipotong.
 *
 * Filament memberi kelas `truncate` pada `.fi-sidebar-item-label`, jadi label
 * panjang ("Kustomisasi Website, Mobile Android & iOS") tampil sebagai
 * "Kustomisasi Website, M...". Aturan penimpanya ada di Shared.css -- SATU
 * tempat untuk ketiga panel (file itu diimpor Admin.css, User.css, dan
 * Welcome.css).
 *
 * Test ini menjaga dua hal:
 *   1. Aturannya ada dan menang atas `truncate` (normal + visible), dan
 *   2. tidak ada aturan lain di file yang sama yang mengembalikan ellipsis.
 */

function sidebarCss(): string
{
    return file_get_contents(resource_path('css/Shared/Shared.css'));
}

test('label sidebar membungkus, bukan dipotong', function (): void {
    $css = sidebarCss();

    expect($css)->toContain('.fi-panel-admin .fi-sidebar-item-label')
        ->toContain('.fi-panel-user .fi-sidebar-item-label')
        ->toContain('.fi-panel-welcome .fi-sidebar-item-label');

    // Ambil blok aturan label sidebar saja (bukan seluruh file).
    preg_match(
        '/\.fi-panel-admin \.fi-sidebar-item-label,(.*?)\{(.*?)\}/s',
        $css,
        $matches
    );

    expect($matches)->not->toBeEmpty();

    $body = $matches[2];

    expect($body)->toContain('white-space: normal')
        ->toContain('overflow: visible')
        ->toContain('text-overflow: clip')
        ->toContain('overflow-wrap: anywhere');
});

test('tidak ada aturan sidebar yang memakai ellipsis', function (): void {
    $css = sidebarCss();

    // Setiap deklarasi text-overflow di file ini harus clip/awal, bukan
    // ellipsis, KECUALI pada breadcrumb (itu memang dipotong, dan bukan
    // sidebar). Ambil bagian sidebar saja.
    preg_match_all('/[^{}]*fi-sidebar[^{}]*\{[^}]*\}/s', $css, $matches);

    $sidebarBlocks = implode("\n", $matches[0]);

    expect($sidebarBlocks)->not->toContain('text-overflow: ellipsis');
});

test('ketiga panel memakai berkas CSS bersama yang sama', function (): void {
    foreach (['Admin', 'User', 'Welcome'] as $panel) {
        $css = file_get_contents(resource_path("css/{$panel}/{$panel}.css"));

        expect($css)->toContain('@import "../Shared/Shared.css"');
    }
});
