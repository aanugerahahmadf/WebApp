<?php

namespace App\Console\Commands\GenerateSeedImages;

use Illuminate\Console\Command;

class GenerateSeedImages extends Command
{
    protected $signature = 'db:generate-seed-images';
    protected $description = 'Generate placeholder images for package and product seeders';

    private const CATEGORY_COLORS = [
        'traditional' => [139, 69, 19],
        'modern'      => [74, 144, 217],
        'rustic'      => [210, 105, 30],
        'minimalist'  => [200, 162, 200],
        'garden'      => [34, 139, 34],
        'royal'       => [255, 215, 0],
        'beach'       => [0, 206, 209],
        'vintage'     => [230, 195, 195],
        'industrial'  => [72, 72, 72],
        'tropical'    => [0, 255, 127],
    ];

    private const CATEGORY_ACCENTS = [
        'traditional' => [178, 58, 38],
        'modern'      => [30, 60, 114],
        'rustic'      => [139, 90, 43],
        'minimalist'  => [180, 130, 170],
        'garden'      => [0, 100, 0],
        'royal'       => [184, 134, 11],
        'beach'       => [0, 150, 160],
        'vintage'     => [160, 120, 140],
        'industrial'  => [105, 105, 105],
        'tropical'    => [50, 205, 50],
    ];

    public function handle()
    {
        $this->info('--- Generating Seed Images ---');

        $pkgIdx = 1;
        foreach (self::CATEGORY_COLORS as $cat => $color) {
            for ($i = 0; $i < 5; $i++) {
                $path = public_path("images/package/package-{$pkgIdx}.png");
                if (!file_exists($path)) {
                    $this->generateImage($path, "Paket {$cat}", $pkgIdx, $color, self::CATEGORY_ACCENTS[$cat], 'package');
                    $this->line("  <info>✓</info> package-{$pkgIdx}.png");
                } else {
                    $this->line("  • package-{$pkgIdx}.png (already exists)");
                }
                $pkgIdx++;
            }
        }

        $prdIdx = 1;
        foreach (self::CATEGORY_COLORS as $cat => $color) {
            for ($i = 0; $i < 5; $i++) {
                $path = public_path("images/product/product-{$prdIdx}.png");
                if (!file_exists($path)) {
                    $this->generateImage($path, "Produk {$cat}", $prdIdx, $color, self::CATEGORY_ACCENTS[$cat], 'product');
                    $this->line("  <info>✓</info> product-{$prdIdx}.png");
                } else {
                    $this->line("  • product-{$prdIdx}.png (already exists)");
                }
                $prdIdx++;
            }
        }

        // ── Vendor Logos ──────────────────────────────────────────────────────
        $vendorColors = [
            [139, 69, 19], [74, 144, 217], [210, 105, 30], [200, 162, 200],
            [34, 139, 34], [255, 215, 0], [0, 206, 209], [230, 195, 195],
            [72, 72, 72], [0, 255, 127],
        ];
        $vendorAccents = [
            [178, 58, 38], [30, 60, 114], [139, 90, 43], [180, 130, 170],
            [0, 100, 0], [184, 134, 11], [0, 150, 160], [160, 120, 140],
            [105, 105, 105], [50, 205, 50],
        ];
        $vendorNames = [
            'Toko Bunga Indah', 'Dekorasi Cinta Abadi', 'Pelaminan Mewah', 'Bridal Bloom Studio',
            'Florist Nusantara', 'Dekorasi Bahagia', 'Taman Cinta Abadi', 'Panggung Istimewa',
            'Bunga Kasih Sayang', 'Pernikahan Idaman',
        ];

        $vendorLogoDir = storage_path("app/public/images/vendor");
        foreach ($vendorNames as $i => $name) {
            $path = "{$vendorLogoDir}/vendor-logo-{$i}.png";
            $short = str_replace('Toko ', '', $name);
            $short = str_replace('Dekorasi ', '', $short);
            $short = str_replace('Bunga ', '', $short);
            $short = str_replace('Florist ', '', $short);
            $short = str_replace('Panggung ', '', $short);
            $short = str_replace('Taman ', '', $short);
            $short = str_replace('Bridal ', '', $short);
            $short = str_replace('Pelaminan ', '', $short);
            $short = str_replace('Pernikahan ', '', $short);
            $short = explode(' ', trim($short))[0] ?? 'Vendor';
            if (!file_exists($path)) {
                $this->generateVendorLogo($path, $short, $vendorColors[$i], $vendorAccents[$i]);
                $this->line("  <info>✓</info> vendor-logo-{$i}.png");
            } else {
                $this->line("  • vendor-logo-{$i}.png (already exists)");
            }
        }

        $this->info('--- Seed Image Generation Complete ---');
    }

    private function generateVendorLogo(string $path, string $initial, array $base, array $accent): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $w = 200;
        $h = 200;
        $img = imagecreatetruecolor($w, $h);

        [$r1, $g1, $b1] = $base;
        [$r2, $g2, $b2] = [
            min(255, $base[0] + 60),
            min(255, $base[1] + 60),
            min(255, $base[2] + 60),
        ];

        // Gradient circular background
        for ($y = 0; $y < $h; $y++) {
            $ratio = $y / $h;
            $r = (int)($r1 * (1 - $ratio) + $r2 * $ratio);
            $g = (int)($g1 * (1 - $ratio) + $g2 * $ratio);
            $b = (int)($b1 * (1 - $ratio) + $b2 * $ratio);
            imageline($img, 0, $y, $w, $y, imagecolorallocate($img, $r, $g, $b));
        }

        // Outer ring
        $ringColor = imagecolorallocatealpha($img, $accent[0], $accent[1], $accent[2], 30);
        imagefilledellipse($img, 100, 100, 170, 170, $ringColor);
        $ringColor2 = imagecolorallocatealpha($img, $accent[0], $accent[1], $accent[2], 15);
        imagefilledellipse($img, 100, 100, 140, 140, $ringColor2);

        // Center white circle
        $white = imagecolorallocatealpha($img, 255, 255, 255, 20);
        imagefilledellipse($img, 100, 100, 110, 110, $white);

        // Letter
        $textColor = imagecolorallocate($img, 255, 255, 255);
        $fontFile = null;
        foreach (['C:/Windows/Fonts/arial.ttf', 'C:/Windows/Fonts/segoeui.ttf', 'C:/Windows/Fonts/calibri.ttf'] as $p) {
            if (file_exists($p)) { $fontFile = $p; break; }
        }
        $size = 64;
        if ($fontFile) {
            $bbox = imagettfbbox($size, 0, $fontFile, $initial);
            $tx = (int)(($w - ($bbox[2] - $bbox[0])) / 2);
            $ty = (int)(($h + ($bbox[1] - $bbox[7])) / 2);
            imagettftext($img, $size, 0, $tx, $ty, $textColor, $fontFile, $initial);
        } else {
            $fw = imagefontwidth(5) * strlen($initial);
            $fh = imagefontheight(5);
            imagestring($img, 5, (int)(($w - $fw) / 2), (int)(($h - $fh) / 2), $initial, $textColor);
        }

        // Small icon at bottom
        $smallTextColor = imagecolorallocatealpha($img, 255, 255, 255, 60);
        if ($fontFile) {
            $smallSize = 14;
            $bbox = imagettfbbox($smallSize, 0, $fontFile, 'STORE');
            $tx = (int)(($w - ($bbox[2] - $bbox[0])) / 2);
            imagettftext($img, $smallSize, 0, $tx, 180, $smallTextColor, $fontFile, 'STORE');
        }

        imagepng($img, $path);
        imagedestroy($img);
    }

    private function generateImage(string $path, string $label, int $num, array $base, array $accent, string $type): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $w = 600;
        $h = 600;
        $img = imagecreatetruecolor($w, $h);

        [$r1, $g1, $b1] = $base;
        [$r2, $g2, $b2] = [
            min(255, $base[0] + 40),
            min(255, $base[1] + 40),
            min(255, $base[2] + 40),
        ];

        // Gradient background
        for ($y = 0; $y < $h; $y++) {
            $ratio = $y / $h;
            $r = (int)($r1 * (1 - $ratio) + $r2 * $ratio);
            $g = (int)($g1 * (1 - $ratio) + $g2 * $ratio);
            $b = (int)($b1 * (1 - $ratio) + $b2 * $ratio);
            imageline($img, 0, $y, $w, $y, imagecolorallocate($img, $r, $g, $b));
        }

        // Decorative arch
        $archColor = imagecolorallocatealpha($img, $accent[0], $accent[1], $accent[2], 40);
        $cx = $w / 2;
        $cy = $h * 0.42;
        $rx = $w * 0.38;
        $ry = $h * 0.35;
        imagefilledellipse($img, $cx, $cy, $rx * 2, $ry * 2, $archColor);

        $archColor2 = imagecolorallocatealpha($img, $accent[0], $accent[1], $accent[2], 20);
        imagefilledellipse($img, $cx, $cy, $rx * 1.5, $ry * 1.5, $archColor2);

        // Decorative elements (flowers/bubbles)
        $white = imagecolorallocatealpha($img, 255, 255, 255, 70);
        $accentC = imagecolorallocatealpha($img, $accent[0], $accent[1], $accent[2], 50);

        $positions = [
            [$num % 5 * 80 + 100, 120],
            [($num * 3) % 5 * 70 + 150, 220],
            [($num * 7) % 5 * 60 + 180, 320],
            [($num * 2) % 5 * 90 + 80, 400],
            [($num * 4) % 5 * 70 + 200, 150],
        ];
        foreach ($positions as $i => $pos) {
            $size = 15 + ($i * 2);
            $col = $i % 2 === 0 ? $white : $accentC;
            imagefilledellipse($img, $pos[0], $pos[1], $size, $size, $col);
            imagefilledellipse($img, $pos[0] + 15, $pos[1] + 10, $size - 6, $size - 6, $i % 2 === 0 ? $accentC : $white);
        }

        // Decorative line
        $lineColor = imagecolorallocatealpha($img, 255, 255, 255, 50);
        for ($x = 50; $x < $w - 50; $x += 3) {
            $ly = $h - 90 + (int)(sin($x * 0.06 + $num) * 6);
            imagesetpixel($img, $x, $ly, $lineColor);
        }

        // Text
        $textColor = imagecolorallocate($img, 255, 255, 255);
        $label = $type === 'package' ? "Wedding Package" : "Wedding Product";
        $this->drawCenteredText($img, $label, $w / 2, $h * 0.68, 26, $textColor);

        $numColor = imagecolorallocatealpha($img, 255, 255, 255, 80);
        $this->drawCenteredText($img, "#{$num}", $w / 2, $h * 0.76, 16, $numColor);

        $typeColor = imagecolorallocatealpha($img, 255, 255, 255, 35);
        $typeLabel = $type === 'package' ? 'KATALOG PAKET BUNGA' : 'KATALOG BUNGA';
        $this->drawCenteredText($img, $typeLabel, $w / 2, $h * 0.83, 13, $typeColor);

        imagepng($img, $path);
        imagedestroy($img);
    }

    private function drawCenteredText($img, string $text, int $cx, int $cy, int $size, $color): void
    {
        $fontFile = null;
        foreach (['C:/Windows/Fonts/arial.ttf', 'C:/Windows/Fonts/segoeui.ttf'] as $p) {
            if (file_exists($p)) { $fontFile = $p; break; }
        }

        if ($fontFile) {
            $bbox = imagettfbbox($size, 0, $fontFile, $text);
            $tx = $cx - (int)(($bbox[2] - $bbox[0]) / 2);
            $ty = $cy + (int)(($bbox[1] - $bbox[7]) / 2);
            imagettftext($img, $size, 0, $tx, $ty, $color, $fontFile, $text);
        } else {
            $fw = imagefontwidth(5) * strlen($text);
            $fh = imagefontheight(5);
            imagestring($img, 5, $cx - (int)($fw / 2), $cy - (int)($fh / 2), $text, $color);
        }
    }
}
