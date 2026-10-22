<?php

namespace Tests\Feature\Shared\ScrollbarHiddenTest;

use Tests\TestCase;

/**
 * Scrollbar panel Filament: DISEMBUNYIKAN (transparan), tapi isi TETAP bisa
 * digulir.
 *
 * Aturan main per panel ada di CSS panelnya:
 *
 *   resources/css/Admin/Admin.css    -> .fi-panel-admin
 *   resources/css/User/User.css      -> .fi-panel-user
 *   resources/css/Welcome/Welcome.css -> .fi-panel-welcome
 *
 * plus aturan global html/body di `resources/css/Shared/Shared.css`.
 *
 * YANG DIJAGA di sini:
 *
 *   1. Aturan itu ADA untuk ketiga panel (scrollbar kanan/kiri/atas/bawah).
 *   2. TIDAK ada `overflow: hidden` yang mematikan scroll -- `overflow:
 *      hidden` memblokir touch scroll di Android/iOS WebView dan membuat
 *      tabel lebar tidak bisa digulir sama sekali. Yang hilang hanya
 *      gambarnya.
 */
class ScrollbarHiddenTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function panelProvider(): array
    {
        return [
            'admin' => ['admin'],
            'user' => ['user'],
            'welcome' => ['welcome'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('panelProvider')]
    public function test_panel_has_scrollbar_hidden_rules(string $panel): void
    {
        $css = $this->panelCss($panel);

        $this->assertMatchesRegularExpression(
            '/\.fi-panel-'.$panel.'[^{]*\{[^}]*scrollbar-width:\s*none/',
            $css,
            "Panel {$panel}: aturan scrollbar-width:none tidak ada.",
        );

        $this->assertMatchesRegularExpression(
            '/\.fi-panel-'.$panel.'[^{]*::-webkit-scrollbar\s*\{[^}]*display:\s*none/',
            $css,
            "Panel {$panel}: aturan ::-webkit-scrollbar display:none tidak ada.",
        );
    }

    public function test_global_html_body_scrollbar_is_hidden(): void
    {
        $css = (string) file_get_contents(resource_path('css/Shared/Shared.css'));

        $this->assertMatchesRegularExpression(
            '/html[^{}]*scrollbar-width:\s*none/',
            $css,
            'Scrollbar html/body tidak disembunyikan.',
        );
    }

    /**
     * Scroll TETAP harus bisa jalan: tidak boleh ada `overflow: hidden`
     * yang menimpa elemen scrollable milik panel.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('panelProvider')]
    public function test_panel_never_kills_scrolling_with_overflow_hidden(string $panel): void
    {
        $css = $this->panelCss($panel);

        // `overflow: hidden` TIDAK boleh muncul di dalam blok yang
        // menyangkut elemen scrollable milik panel (selector yang memuat
        // `overflow-` utility atau `fi-` scroll container).
        $this->assertDoesNotMatchRegularExpression(
            '/\.fi-panel-'.$panel.'\s+[^{}]*overflow\s*:\s*hidden/',
            $css,
            "Panel {$panel}: ada overflow:hidden yang bisa mematikan scroll.",
        );
    }

    private function panelCss(string $panel): string
    {
        $path = resource_path('css/'.ucfirst($panel).'/'.ucfirst($panel).'.css');

        $this->assertFileExists($path, "CSS panel {$panel} tidak ada: {$path}");

        return (string) file_get_contents($path);
    }
}
