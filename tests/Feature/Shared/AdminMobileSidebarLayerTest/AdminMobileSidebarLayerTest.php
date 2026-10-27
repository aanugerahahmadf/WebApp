<?php

/**
 * Drawer sidebar Admin harus berada DI ATAS topbar di layar kecil.
 *
 * Topbar Admin memakai `position: fixed` dengan `z-index: 40`. Sidebar adalah
 * drawer di bawah 1024px, jadi tanpa aturan khusus topbar menutupi header
 * sidebar -- logo dan tombol tutup sidebar tertimbun search bar, dan yang
 * terlihat justru chrome milik topbar. Test ini menjaga urutan lapisan itu.
 */

function adminCss(): string
{
    return file_get_contents(resource_path('css/Admin/Admin.css'));
}

test('topbar admin berada di atas konten dengan z-index tetap', function (): void {
    expect(adminCss())
        ->toContain('.fi-panel-admin .fi-topbar')
        ->toContain('z-index: 40 !important');
});

test('sidebar admin yang terbuka berada di atas topbar pada layar kecil', function (): void {
    $css = adminCss();

    // Hanya pada breakpoint mobile: di desktop sidebar adalah kolom biasa,
    // bukan lapisan yang menutupi topbar.
    expect($css)->toContain('@media (max-width: 1023px)');

    preg_match(
        '/@media\s*\(max-width:\s*1023px\)\s*\{(.*?)\n\}/s',
        $css,
        $matches
    );

    expect($matches)->not->toBeEmpty();

    $mobileBlock = $matches[1];

    expect($mobileBlock)
        ->toContain('.fi-panel-admin .fi-sidebar.fi-sidebar-open')
        ->toContain('z-index: 50 !important')
        ->toContain('.fi-panel-admin .fi-topbar.fi-admin-topbar-sidebar-open')
        ->toContain('display: none !important');
});
