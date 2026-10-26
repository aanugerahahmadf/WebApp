<?php

/**
 * Penjaga "tidak ada bold di resources": seluruh `resources/` memakai
 * Instrument Sans dan setiap tebal yang tadinya 700/800/900 (atau `bold`)
 * sudah menjadi semibold 600.
 *
 * Kenapa sumbernya yang dipindai, bukan HTML: bold di resources muncul dalam
 * tiga bentuk yang tidak semuanya bisa dilihat dari satu halaman --
 * utilitas Tailwind (`font-bold`) di Blade, `font-weight: 700` di `<style>`
 * inline/email/PDF, dan nilai yang dipilih PHP (`? '700' : '400'`). Halaman
 * yang diuji (Sign In) hanya memuat sebagian kecil dari ketiganya, jadi
 * pemeriksaan berkas adalah satu-satunya cara memastikan seluruh direktori
 * benar-benar bersih.
 *
 * PENGECUALIAN: tidak ada. Kalau suatu saat butuh bold sungguhan, ubah
 * test ini sekalian dan jelaskan alasannya -- jangan diam-diam menambah
 * `font-bold` baru di resources.
 */

use Illuminate\Support\Facades\File;

/**
 * Kumpulkan pelanggaran bold dari semua berkas teks di resources.
 *
 * @return list<string> format: "path:baris: potongan baris"
 */
function resourceBoldViolations(): array
{
    $violations = [];

    $files = collect(File::allFiles(resource_path()))
        ->filter(fn (SplFileInfo $file): bool => in_array($file->getExtension(), ['css', 'js', 'php'], true));

    foreach ($files as $file) {
        $relative = str_replace(resource_path().DIRECTORY_SEPARATOR, '', $file->getPathname());

        foreach (file($file->getPathname()) ?: [] as $index => $line) {
            if (preg_match('/font-(?:bold|extrabold|black)\b|font-weight\s*:\s*(?:700|800|900|bold)\b/i', $line)) {
                $violations[] = $relative.':'.($index + 1).': '.trim($line);
            }
        }
    }

    return $violations;
}

test('resources contains no bold utilities or bold font weights', function (): void {
    $violations = resourceBoldViolations();

    $this->assertSame(
        [],
        $violations,
        "Masih ada bold di resources; ganti ke semibold (600):\n".implode("\n", array_slice($violations, 0, 20))
    );
});

test('the global panel css clamps runtime bold to semibold', function (): void {
    // Filament bisa menghasilkan `font-bold` dari enum FontWeight::Bold yang
    // tidak lewat Blade resources. Aturan global di AppFont::styles() yang
    // menahannya, jadi kontraknya diuji lewat halaman yang benar-benar dirender.
    $html = $this->get('/signin')->assertOk()->getContent();

    expect($html)
        ->toContain('data-app-instrument-sans-global')
        ->toContain('.font-bold,')
        ->toContain('.font-extrabold,')
        ->toContain('.font-black,')
        ->toContain('font-weight: 600 !important');
});
