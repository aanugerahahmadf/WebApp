<?php

use App\Support\PdfLayout\PdfLayout;
use App\Support\ExportText\ExportText;
use App\Support\PdfLayout\PdfText;
use App\Support\ReportCategory\ReportCategory;

/*
 * Tata letak PDF (Google Sans Flex + margin 3/3/4/3 cm + header 2 cm + footer 1,65 cm).
 *
 * Yang diuji di sini BUKAN "kalimatnya kelihatan benar", tapi angka
 * sebenarnya di dalam file PDF -- karena di sinilah letak kesalahan
 * biasanya bersembunyi:
 *
 *   1. Dompdf TIDAK mendukung margin box CSS Paged Media (`@top-center`,
 *      `@bottom-center`). Aturannya dibaca lalu DILEWATI TANPA ERROR:
 *      header dan footer hilang, dan tidak ada apa pun yang gagal. Jadi
 *      "PDF-nya benar" tidak berarti "header/footernya ada".
 *
 *   2. `position: fixed` di Dompdf berposisi relatif terhadap KOTAK ISI,
 *      bukan tepi kertas. "Header 2 cm dari tepi atas" berarti
 *      `top: -1cm` (1 cm ke atas dari garis margin atas) -- bukan `top: 0`.
 *
 *   3. `counter(pages)` (jumlah halaman) tidak didukung Dompdf: nilainya
 *      selalu 0, jadi hasilnya "Halaman 1 dari 0".
 *
 * Pengukuran dilakukan dengan membaca matriks teks (Td) di dalam content
 * stream PDF -- sama seperti yang dilakukan viewer PDF sungguhan.
 */



/**
 * Render PDF dengan layout standar dan kembalikan isinya + baris teksnya.
 *
 * @return array{0: string, 1: list<array{0: float, 1: float, 2: float, 3: string}>}
 */
function renderStandardPdf(string $bodyHtml = ''): array
{
    $body = $bodyHtml !== '' ? $bodyHtml : '<p>Isi dokumen.</p>';

    $html = '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><style>'
        .PdfLayout::pageCss('portrait', 'body{font-size:11pt;line-height:1.5;}')
        .'</style></head><body>'
        .PdfLayout::header('HEADER-TEKS-UNIK')
        .PdfLayout::footer()
        .$body
        .'</body></html>';

    $pdf = PdfLayout::apply(new Dompdf\Dompdf);
    $pdf->loadHtml($html, 'UTF-8');
    $pdf->setPaper('A4', 'portrait');
    $pdf->render();

    $output = $pdf->output();

    return [$output, pdfTextRows($output)];
}

// ── Aturan @page ────────────────────────────────────────────────────────────

test('the page margins follow the printed specification', function (): void {
    $css = PdfLayout::pageCss('portrait');

    expect($css)
        ->toContain('margin-top: 3cm')
        ->toContain('margin-bottom: 3cm')
        ->toContain('margin-left: 4cm')
        ->toContain('margin-right: 3cm')
        ->toContain("font-family: 'Google Sans Flex'")
        ->not->toContain('Arial');
});

test('the header and footer offsets are derived from the margins', function (): void {
    // Header 2 cm dari tepi atas, dengan margin atas 3 cm -> top: -1cm.
    // Footer 1,65 cm dari tepi bawah, dengan margin bawah 3 cm -> bottom: -1,35cm.
    expect(PdfLayout::pageCss('portrait'))
        ->toContain('top: -1cm')
        ->toContain('bottom: -1.35cm');
});

// ── Hasil render ────────────────────────────────────────────────────────────

test('the rendered pdf uses google sans flex', function (): void {
    [$output] = renderStandardPdf();

    // Font Dompdf bawaan adalah DejaVu Sans. Kalau font-nya belum
    // Google Sans Flex, dirender DejaVu dan TIDAK ada nama font Google
    // di dalam stream. Font ter-embed muncul sebagai subset:
    // /BaseFont /XXXXXX+GoogleSansFlex24pt-Regular.
    expect($output)->toContain('GoogleSansFlex')->not->toContain('DejaVu');
});

test('bold text embeds the official bold instance, not the variable default', function (): void {
    // JEBAKAN: kalau family diregistrasi dari TTF VARIABLE, Dompdf
    // mengurainya cuma sampai instansi default (Regular, wght 400) --
    // teks <strong> dirender NORMAL dan hanya SATU font yang ter-embed.
    // Instansi resmi Google (24pt Regular + 24pt Bold) membuat KEDUA
    // BaseFont muncul di stream.
    [$output] = renderStandardPdf('<p>Isi <strong>tebal</strong>.</p>');

    expect($output)
        ->toContain('GoogleSansFlex24pt-Regular')
        ->toContain('GoogleSansFlex24pt-Bold');
});

test('the header sits two centimetres from the top edge', function (): void {
    [, $rows] = renderStandardPdf();

    $header = collect($rows)->first(fn ($row) => str_contains($row[3], 'HEADER-TEKS-UNIK'));

    expect($header)->not->toBeNull('header tidak muncul di PDF');

    // A4 tegak = 841,89 pt. Baseline teks 9pt berada ~0,48 cm di bawah
    // kotak elemen, jadi kotak elemennya ada di 2,00 cm dari tepi atas.
    $fromTop = (841.89 - $header[1]) / 28.3465;

    expect($fromTop)->toBeGreaterThan(2.0)->toBeLessThan(2.6);
});

test('the footer sits one point six five centimetres from the bottom edge', function (): void {
    [, $rows] = renderStandardPdf();

    $footer = collect($rows)->first(fn ($row) => str_contains($row[3], 'Halaman'));

    expect($footer)->not->toBeNull('footer tidak muncul di PDF');

    $fromBottom = $footer[1] / 28.3465;

    // Baseline ~0,12 cm di atas kotak elemen (descender turun ke bawahnya).
    expect($fromBottom)->toBeGreaterThan(1.6)->toBeLessThan(1.9);
});

test('the body starts three centimetres from the top and four from the left', function (): void {
    [, $rows] = renderStandardPdf('<p>Isi dokumen.</p>');

    $body = collect($rows)->first(fn ($row) => str_contains($row[3], 'Isi dokumen'));

    expect($body)->not->toBeNull('isi dokumen tidak muncul di PDF');

    // Kiri: 4 cm dari tepi kiri.
    expect($body[0] / 28.3465)->toBeGreaterThan(3.9)->toBeLessThan(4.1);

    // Atas: baris pertama TIDAK BOLEH naik ke atas garis margin 3 cm.
    // Baseline-nya sendiri berada ~0,6 cm di bawah garis itu (ascent +
    // margin default paragraf), jadi rentang yang masuk akal 3,0-4,2 cm.
    $fromTop = (841.89 - $body[1]) / 28.3465;

    expect($fromTop)->toBeGreaterThan(3.0)->toBeLessThan(4.2);
});

test('the page number is rendered by the counter', function (): void {
    [, $rows] = renderStandardPdf();

    // "Halaman 1" -- angkanya berasal dari counter(page).
    $number = collect($rows)->first(fn ($row) => trim($row[3]) === '1');

    expect($number)->not->toBeNull('nomor halaman tidak muncul');

    // counter(pages) tidak didukung Dompdf (nilainya 0), jadi teksnya
    // harus "Halaman 1" saja -- BUKAN "Halaman 1 dari 0".
    $all = collect($rows)->map(fn ($row) => $row[3])->implode(' ');

    expect($all)->not->toContain('dari0');
});

/**
 * Render PDF dengan CSS tambahan tertentu, lalu kembalikan baris teksnya.
 *
 * @return list<array{0: float, 1: float, 2: float, 3: string}>
 */
function renderWithExtraCss(string $extraCss): array
{
    $html = '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><style>'
        .PdfLayout::pageCss('portrait', $extraCss)
        .'</style></head><body>'
        .PdfLayout::header('HEADER-TEKS-UNIK')
        .PdfLayout::footer()
        .'<p>Isi dokumen.</p>'
        .'</body></html>';

    $pdf = PdfLayout::apply(new Dompdf\Dompdf);
    $pdf->loadHtml($html, 'UTF-8');
    $pdf->setPaper('A4', 'portrait');
    $pdf->render();

    return pdfTextRows($pdf->output());
}

test('a universal margin reset silently pushes the header off the paper', function (): void {
    // Jebakan Dompdf: style dasar halaman mewarisi style elemen root,
    // dan `*` ikut cocok dengan root itu. Jadi `* { margin: 0 }` menimpa
    // margin @page -- header keluar dari atas kertas dan footer keluar
    // dari bawah kertas, TANPA ERROR APA PUN.
    //
    // Test ini mengunci gejalanya, supaya jebakan ini tidak dipakai
    // diam-diam di view PDF lain.
    $rows = renderWithExtraCss('* { margin: 0; padding: 0; box-sizing: border-box; }');

    $header = collect($rows)->first(fn ($row) => str_contains($row[3], 'HEADER-TEKS-UNIK'));
    $footer = collect($rows)->first(fn ($row) => str_contains($row[3], 'Halaman'));

    expect($header)->not->toBeNull();
    expect($footer)->not->toBeNull();

    // Baseline header DI ATAS tepi kertas (841,89 pt)...
    expect($header[1])->toBeGreaterThan(841.89);

    // ...dan footer DI BAWAH tepi bawah (y negatif).
    expect($footer[1])->toBeLessThan(0);
});

test('scoping the margin reset to body keeps the page margins intact', function (): void {
    // `body *` tidak cocok dengan elemen root, jadi margin @page tetap
    // utuh. Inilah yang dipakai view invoice.
    $rows = renderWithExtraCss('body * { margin: 0; padding: 0; box-sizing: border-box; }');

    $header = collect($rows)->first(fn ($row) => str_contains($row[3], 'HEADER-TEKS-UNIK'));
    $footer = collect($rows)->first(fn ($row) => str_contains($row[3], 'Halaman'));

    expect($header)->not->toBeNull();
    expect($footer)->not->toBeNull();

    // Keduanya tetap DI DALAM kertas.
    expect($header[1])->toBeLessThan(841.89);
    expect($footer[1])->toBeGreaterThan(0);
});

// ── Teks PDF: tidak boleh ada "_" maupun "/" ───────────────────────────────

test('report categories are shown as human labels', function (): void {
    expect(ReportCategory::label('general_question'))->toBe('Pertanyaan Umum')
        ->and(ReportCategory::label('bug_report'))->toBe('Lapor Bug')
        ->and(ReportCategory::label('decor_consultation'))->toBe('Konsultasi Dekorasi')
        ->and(ReportCategory::label(null))->toBe('-');
});

test('an unknown report category still has no underscore', function (): void {
    // Kategori baru yang belum masuk daftar tidak boleh membocorkan
    // slug mentah ke dokumen.
    $label = ReportCategory::label('brand_new_category');

    expect($label)->not->toContain('_')->not->toContain('/')
        ->and($label)->toBe('Brand New Category');
});

test('dates are written in the long indonesian format', function (): void {
    // "21/10/2026 09:12" -> "21 Oktober 2026, 09.12"
    expect(PdfText::date('2026-10-21 09:12:33'))->toBe('21 Oktober 2026, 09.12')
        ->and(PdfText::date('2026-10-21'))->toBe('21 Oktober 2026, 00.00')
        ->and(PdfText::date(null))->toBe('-');
});

test('attachment urls are reduced to a clean file name', function (): void {
    // Prefix penyimpanan Filament ({id}_{timestamp}_) dibuang, sisa
    // garis bawah diganti spasi.
    expect(PdfText::fileName('https://host.test/media/avatars/1_1792351644_Onx6Q.jpg'))
        ->toBe('Onx6Q.jpg')
        ->and(PdfText::fileName('https://host.test/media/uploads/payment_proof_2026.jpg'))
        ->toBe('payment proof 2026.jpg')
        ->and(PdfText::fileName('https://host.test/media/a/b/'))->toBe('Lampiran');
});

test('export column headings are humanised', function (): void {
    expect(ExportText::heading('full_name'))->toBe('Full Name')
        ->and(ExportText::heading('created_at'))->toBe('Created At')
        ->and(ExportText::heading('email_verified_at'))->toBe('Email Verified At')
        // Singkatan tetap kapital.
        ->and(ExportText::heading('id'))->toBe('ID')
        ->and(ExportText::heading('avatar_url'))->toBe('Avatar URL')
        ->and(ExportText::heading('ktp_photo_url'))->toBe('KTP Photo URL');
});

test('no pdf heading, date or file name contains an underscore or a slash', function (): void {
    $columns = [
        'id', 'full_name', 'username', 'email', 'email_verified_at', 'created_at',
        'updated_at', 'avatar_url', 'ktp_photo_url', 'active_status', 'total_price',
        'general_question', 'super_admin',
    ];

    foreach ($columns as $column) {
        $heading = ExportText::heading($column);

        expect($heading)->not->toContain('_')->not->toContain('/');
    }

    $date = PdfText::date('2026-10-21 09:12:33');

    expect($date)->not->toContain('_')->not->toContain('/');
});
