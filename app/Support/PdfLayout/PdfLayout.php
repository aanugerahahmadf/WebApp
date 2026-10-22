<?php

namespace App\Support\PdfLayout;

use Dompdf\Dompdf;

/**
 * Satu-satunya tempat yang mengatur tampilan kertas PDF.
 *
 * Semua dokumen PDF aplikasi ini (laporan pengguna, pusat unduhan data,
 * invoice, formulir kebutuhan acara) memakai spec yang sama:
 *
 *   - Font   : Google Sans Flex (TTF instansi resmi dari Google)
 *   - Margin : atas 3 cm, bawah 3 cm, kiri 4 cm, kanan 3 cm
 *   - Header : 2 cm dari tepi atas
 *   - Footer : 1,65 cm dari tepi bawah
 *
 * Kenapa helper, bukan ditulis ulang di tiap generator:
 *
 * 1. Dompdf TIDAK mendukung margin box CSS Paged Media (`@top-center`,
 *    `@bottom-center`). Aturannya dibaca, lalu DILEWATI TANPA ERROR --
 *    jadi header dan footer hilang begitu saja, dan tidak ada apa pun
 *    yang gagal. Satu-satunya gejalanya: PDF-nya benar, tapi tidak ada
 *    header/footer.
 *
 * 2. Header/footer di Dompdf harus dibuat sebagai elemen
 *    `position: fixed`. Elemen itu diulang di SETIAP halaman, dan
 *    posisinya relatif terhadap KOTAK ISI -- bukan tepi kertas. Jadi
 *    "header 2 cm dari tepi atas" berarti `top: -1cm` (1 cm ke atas dari
 *    garis margin atas), dan "footer 1,65 cm dari tepi bawah" berarti
 *    `bottom: -1,35cm` (1,35 cm ke bawah dari garis margin bawah).
 *
 * 3. Font default Dompdf adalah DejaVu Sans, dan Dompdf HANYA membaca
 *    TTF/OTF -- woff2 dilempar diam-diam (PDF tetap "bagus" dengan DejaVu
 *    dan tidak ada error). Karena itu font di sini diregistrasi dari
 *    instansi STATIS resmi Google: `assets/fonts/Google_Sans_Flex/static/`
 *    (GoogleSansFlex_24pt-Regular.ttf / -Bold.ttf), bukan dari TTF
 *    variable-nya -- Dompdf mengurai TTF variable cuma sampai instansi
 *    default-nya, jadi teks tebal akan jatuh ke normal.
 *
 * 4. `* { margin: 0 }` di Dompdf MEWARISI style dasar halaman, jadi
 *    margin @page ikut tertimpa: header keluar dari atas kertas dan
 *    footer keluar dari bawah kertas -- tanpa error. Pakai `body *`
 *    kalau memang perlu mereset margin elemen di dalam dokumen.
 */
class PdfLayout
{
    /** Font dokumen (CSS). Fokusnya Google Sans Flex; Arial cuma fallback. */
    public const FONT_FAMILY = "'Google Sans Flex'";

    /**
     * Nama keluarga untuk registrasi Dompdf. HARUS identik dengan entri
     * CSS FONT_FAMILY (tanpa kutip dan tanpa fallback), karena Dompdf
     * mencocokkan nama keluarga antara registrasi dan CSS.
     */
    public const FONT_NAME = 'Google Sans Flex';

    /** Margin halaman dalam cm. */
    public const MARGIN_TOP = 3;
    public const MARGIN_BOTTOM = 3;
    public const MARGIN_LEFT = 4;
    public const MARGIN_RIGHT = 3;

    /** Jarak header dari tepi atas kertas, dalam cm. */
    public const HEADER_FROM_EDGE = 2;

    /** Jarak footer dari tepi bawah kertas, dalam cm. */
    public const FOOTER_FROM_EDGE = 1.65;

    /**
     * Aturan `@page` + gaya dasar header/footer.
     *
     * @param  string  $orientation  'portrait' | 'landscape'
     * @param  string  $extraCss  CSS tambahan (diletakkan sebelum kurung tutup)
     */
    public static function pageCss(string $orientation = 'portrait', string $extraCss = ''): string
    {
        $headerOffset = self::MARGIN_TOP - self::HEADER_FROM_EDGE;
        $footerOffset = self::MARGIN_BOTTOM - self::FOOTER_FROM_EDGE;

        $css = '@page {'
            .' size: A4 '.$orientation.';'
            .' margin-top: '.self::MARGIN_TOP.'cm;'
            .' margin-bottom: '.self::MARGIN_BOTTOM.'cm;'
            .' margin-left: '.self::MARGIN_LEFT.'cm;'
            .' margin-right: '.self::MARGIN_RIGHT.'cm;'
            .' }';

        $css .= 'html, body { font-family: '.self::FONT_FAMILY.'; }';

        $css .= '.pdf-page-header {'
            .' position: fixed;'
            .' top: -'.$headerOffset.'cm;'
            .' left: 0; right: 0;'
            .' text-align: center;'
            .' font-size: 9pt;'
            .' color: #6b7280;'
            .' font-family: '.self::FONT_FAMILY.';'
            .' }';

        $css .= '.pdf-page-footer {'
            .' position: fixed;'
            .' bottom: -'.$footerOffset.'cm;'
            .' left: 0; right: 0;'
            .' text-align: center;'
            .' font-size: 9pt;'
            .' color: #6b7280;'
            .' font-family: '.self::FONT_FAMILY.';'
            .' }';

        // Penomoran halaman. Dompdf hanya bisa mengisi `counter(page)`
        // lewat `content` pada pseudo-element -- bukan lewat HTML biasa.
        //
        // `counter(pages)` (jumlah halaman) TIDAK didukung Dompdf: nilainya
        // selalu 0, jadi hasilnya "Halaman 1 dari 0". Karena itu teksnya
        // cukup "Halaman N" -- kalau jumlah halaman dibutuhkan, PDF harus
        // dirender dua kali: hitung dulu, baru render ulang dengan
        // jumlah yang sudah diketahui.
        $css .= '.pdf-page-number:after { content: counter(page); }';

        if ($extraCss !== '') {
            $css .= $extraCss;
        }

        return $css;
    }

    /**
     * Blok header yang diulang di setiap halaman.
     */
    public static function header(string $text): string
    {
        return '<div class="pdf-page-header">'.e($text).'</div>';
    }

    /**
     * Blok footer yang diulang di setiap halaman.
     *
     * Angkanya ("1", "2", ...) diisi oleh CSS `counter(page)`, jadi teksnya
     * tidak perlu dihitung PHP.
     */
    public static function footer(string $prefix = 'Halaman'): string
    {
        return '<div class="pdf-page-footer">'.e($prefix).' <span class="pdf-page-number"></span></div>';
    }

    /**
     * Set opsi dasar Dompdf: font default Google Sans Flex, dari TTF
     * instansi resmi di assets/fonts/Google_Sans_Flex/static/.
     *
     * Kenapa dari static, bukan dari TTF variable: Dompdf mengurai TTF
     * variable cuma sampai instansi default-nya (Regular, wght 400) -- teks
     * tebal <strong> akan dirender NORMAL. Instansi statis Google membuat
     * Regular dan Bold resmi, dan keduanya sudah dikalibrasi Google di
     * potongan opsz 24 (teks dokumen).
     *
     * Mekanisme registrasi Dompdf HANYA lewat cache keluarga
     * installed-fonts.json di fontDir: nilainya BARIS FILE tanpa folder
     * (nilai yang mengandung "/" dipakai sebagai path mentah, yang tidak
     * mengandung "/" dianggap basename di dalam fontDir). Entrinya
     * ditulis ulang setiap kali layout dipasang supaya sisa pengaturan
     * lama yang menunjuk file lain tidak bisa diam-diam kembali; italic
     * dan bold_italic diarahkan ke instansi yang sudah ada -- tidak ada
     * instansi miring resmi (Dompdf juga tidak bisa memilih sumbu slnt
     * TTF variable), jadi teks miring dirender tegak, bukan ditampilkan
     * font lain.
     */
    public static function apply(Dompdf $pdf): Dompdf
    {
        $fontDir = dirname(__DIR__, 3).'/assets/fonts/Google_Sans_Flex/static';

        $pdf->set_option('fontDir', $fontDir);

        // FontMetrics dibuat saat Dompdf dikonstruksi -- SEBELUM `fontDir`
        // diset -- jadi daftar keluarganya harus dimuat ulang setelah
        // penunjuk folder berganti.
        $cacheFile = $fontDir.'/installed-fonts.json';
        $cache = is_file($cacheFile)
            ? (json_decode((string) file_get_contents($cacheFile), true) ?: [])
            : [];

        $cache = array_merge($cache, [
            'google sans flex' => [
                'normal' => 'GoogleSansFlex_24pt-Regular',
                'bold' => 'GoogleSansFlex_24pt-Bold',
                'italic' => 'GoogleSansFlex_24pt-Regular',
                'bold_italic' => 'GoogleSansFlex_24pt-Bold',
            ],
        ]);

        file_put_contents($cacheFile, json_encode($cache, JSON_PRETTY_PRINT));

        $pdf->getFontMetrics()->loadFontFamilies();

        $pdf->set_option('defaultFont', self::FONT_NAME);
        $pdf->set_option('defaultFontSize', '11pt');
        $pdf->set_option('isRemoteEnabled', false);
        $pdf->set_option('isHtml5ParserEnabled', true);

        return $pdf;
    }
}
