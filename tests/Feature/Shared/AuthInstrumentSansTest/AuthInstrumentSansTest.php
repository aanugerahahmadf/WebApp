<?php

/**
 * Halaman auth Admin & User memakai Instrument Sans LOKAL
 * (`assets/fonts/Instrument_Sans/`, disajikan lewat `public/fonts/...`) dan
 * tidak memakai bold: semua teks yang tadinya 700/800/900 menjadi semibold
 * (600).
 *
 * Kenapa dites lewat HTML: CSS-nya tidak berada di file terpisah, melainkan
 * di-emit `AppFont::styles()` ke hook `panels::styles.after`. Tidak ada tempat
 * lain untuk memastikan aturannya benar-benar sampai ke browser. Penanda
 * `data-auth-page` berasal dari layout `simple`; tanpa penanda itu aturan
 * semibold tidak pernah aktif walau CSS-nya ada di halaman.
 *
 * Yang TIDAK diuji di sini: hasil render piksel. Font-family hasil hitung
 * browser sudah diverifikasi manual; test ini menjaga kontrak markup + CSS-nya
 * supaya perubahan layout/hook tidak diam-diam menghapus keduanya.
 */
test('admin auth pages load the local instrument sans and the auth typography layer', function (): void {
    $html = $this->get('/admin/signin')->assertOk()->getContent();

    expect($html)
        // Layout `simple` menandai dirinya sebagai halaman auth...
        ->toContain('data-auth-page')
        // ...dan AppFont mengirim lapisan tipografi khusus auth.
        ->toContain('data-auth-typography')
        // @font-face Instrument Sans berasal dari berkas CSS lokal ini.
        ->toContain('/css/app-font.css')
        ->toContain("font-family: 'Instrument Sans', sans-serif !important");
});

test('user auth pages load the local instrument sans and the auth typography layer', function (): void {
    // `/signin` dirender panel Welcome tetapi memakai kelas
    // App\Filament\User\Auth\SignIn\SignIn -- persis halaman auth User yang
    // dimaksud. `/auth` adalah halaman pintu masuknya.
    foreach (['/signin', '/auth'] as $url) {
        $html = $this->get($url)->assertOk()->getContent();

        expect($html)
            ->toContain('data-auth-page')
            ->toContain('data-auth-typography')
            ->toContain('/css/app-font.css')
            ->toContain("font-family: 'Instrument Sans', sans-serif !important");
    }
});

test('the auth typography layer maps every bold utility to semibold', function (): void {
    $html = $this->get('/admin/signin')->assertOk()->getContent();

    $start = strpos($html, '<style data-auth-typography>');
    $end = strpos($html, '</style>', $start);

    expect($start)->not->toBeFalse()
        ->and($end)->not->toBeFalse();

    $css = substr($html, $start, $end - $start);

    // Sumber bold yang lazim di halaman auth: tag semantik, utilitas
    // Tailwind, dan inline style dari komponen pihak ketiga.
    expect($css)
        ->toContain('[data-auth-page] b')
        ->toContain('[data-auth-page] strong')
        ->toContain('[data-auth-page] .font-bold')
        ->toContain('[data-auth-page] .font-extrabold')
        ->toContain('[data-auth-page] .font-black')
        ->toContain('[data-auth-page] [style*="font-weight: 700"]')
        // Semua pemetaan di atas berakhir di 600, bukan 700/800/900.
        ->toContain('font-weight: 600 !important');
});
