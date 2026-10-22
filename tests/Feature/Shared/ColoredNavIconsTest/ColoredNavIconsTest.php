<?php

namespace Tests\Feature\Shared\ColoredNavIconsTest;

use App\Support\AppIcon\AppIcon;
use App\Support\AppIcon\VendorIconAliases;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penjaga: ikon navigation, menu item, dan ikon di dalam halaman harus
 * BERWARNA (aset SVG sendiri, bukan Heroicon monokrom).
 *
 * Ikon diberi warna penuh via file SVG di `public/images/icons/`, jadi
 * warnanya TIDAK mengikuti warna teks. Vektor SETIAP ikon berasal dari
 * fonts.google.com/icons (Material Symbols), diambil oleh script
 * `scripts/fetch-material-symbols.py` lalu diwarnai lewat template
 * gradien + drop shadow + animasi.
 *
 * TIDAK ADA PENGECUALIAN: chevron (pagination, dropdown, breadcrumbs,
 * navigator) juga memakai vektor Google -- keputusan pengguna 2026-10-21
 * ("SEMUA ... HARUS PAKAI fonts.google.com/icons"), mencabut pengecualian
 * lama.
 *
 * Test ini mengunci:
 *
 *   1. Setiap konstanta `AppIcon` menunjuk file yang ADA (ikon hilang cuma
 *      jadi kotak kosong -- tanpa error).
 *   2. Tiap file SVG benar-benar berwarna: tidak ada `currentColor` dan
 *      minimal DUA warna (fill/stroke aktif + stop-color gradien).
 *   3. Registrasi vendor (`VendorIconAliases`) hanya menunjuk file kita.
 *   4. Ikon navigasi tiap Resource dan menu item user memakai aset.
 *   5. Halaman yang dirender berisi `<img>` -- bukan ikon monokrom.
 *   6. Navigation group TIDAK boleh punya ikon kalau itemnya punya ikon --
 *      Filament melempar exception dan halamannya 500.
 *   7. Folder ikon bebas PNG (semua aset SVG).
 */
final class ColoredNavIconsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_icon_constant_points_at_an_existing_file(): void
    {
        foreach (AppIcon::all() as $name => $path) {
            $this->assertFileExists(
                public_path(ltrim($path, '/')),
                "Ikon `{$name}` menunjuk file yang tidak ada: {$path}. "
                .'Ikon yang hilang cuma jadi kotak kosong, tanpa error.',
            );
        }
    }

    public function test_every_icon_file_is_really_colored(): void
    {
        foreach (AppIcon::all() as $name => $path) {
            $this->assertSvgIsColored($name, public_path(ltrim($path, '/')));
        }
    }

    public function test_every_vendor_registration_points_at_an_existing_colored_file(): void
    {
        foreach (VendorIconAliases::all() as $key => $path) {
            if (! str_starts_with($path, AppIcon::FOLDER)) {
                continue; // kunci yang bukan path file kita tidak dikunci di sini
            }

            $file = public_path(ltrim($path, '/'));

            $this->assertFileExists(
                $file,
                "Regitrasi ikon vendor `{$key}` menunjuk file yang tidak ada: {$path}.",
            );

            $this->assertSvgIsColored($key, $file);
        }
    }

    public function test_the_icons_folder_has_no_png_left(): void
    {
        // Keputusan pengguna: PAKAI SVG AJA, PNG DIHAPUS SEMUA. Kalau ada
        // generator/ekspor yang menghidupkan kembali berkas PNG, penjaga ini
        // yang meledak dulu.
        $pngs = glob(public_path('images/icons/*.png'));

        $this->assertSame(
            [],
            $pngs ? array_map('basename', $pngs) : [],
            'Folder ikon masih berisi berkas PNG. Semua aset harus SVG.',
        );
    }

    public function test_chevron_icons_are_replaced_with_colored_assets(): void
    {
        // Keputusan pengguna 2026-10-21: TIDAK ADA pengecualian -- chevron
        // pagination/dropdown/breadcrumbs ikut vektor berwarna. Kalau ada
        // yang mengecualikan chevron lagi, penjaga ini yang meledak dulu.
        $chevronKeys = [
            'heroicon-m-chevron-left', 'heroicon-m-chevron-right',
            'heroicon-o-chevron-right', 'heroicon-o-chevron-down',
            'heroicon-s-chevron-double-left',
        ];

        foreach ($chevronKeys as $key) {
            $path = VendorIconAliases::all()[$key]
                ?? 'belum terdaftar';

            $this->assertStringStartsWith(
                AppIcon::FOLDER,
                $path,
                "Chevron `{$key}` belum ditimpa aset berwarna ({$path}). Semua "
                .'ikon harus memakai vektor fonts.google.com/icons.',
            );
        }
    }

    public function test_search_icons_are_replaced_with_colored_assets(): void
    {
        $path = VendorIconAliases::all()['heroicon-o-magnifying-glass']
            ?? 'belum terdaftar';

        $this->assertStringStartsWith(
            AppIcon::FOLDER,
            $path,
            'Ikon search belum ditimpa aset berwarna. Semua ikon harus '
            .'memakai vektor fonts.google.com/icons.',
        );
    }

    public function test_named_translates_icons_to_full_asset_paths(): void
    {
        // JEBAKAN yang pernah terjadi: icon-map.php menyimpan NAMA FILE
        // (mis. 'x'), dan kalau `named()` mengembalikannya mentah-mentah,
        // komponen ikon Filament jatuh ke `@svg('x')` -- mencari ikon
        // yang tidak ada, dan ikonnya hilang tanpa error. Hasil
        // `named()` WAJIB path publik lengkap.
        $this->assertSame(
            AppIcon::FOLDER.'x.svg',
            AppIcon::named('heroicon-m-x-mark'),
            'named() mengembalikan nama polos, bukan path aset. Ikon '
            .'bernama polos jatuh ke @svg() dan hilang.',
        );

        $this->assertSame(
            AppIcon::FOLDER.'chat_dots.svg',
            AppIcon::named('heroicon-o-chat-bubble-left-ellipsis'),
        );

        // Nilai yang bukan nama ikon peta dikembalikan apa adanya.
        $this->assertSame('bukan-ikon-really', AppIcon::named('bukan-ikon-really'));
        $this->assertSame('/images/icons/info.svg', AppIcon::named('/images/icons/info.svg'));
        $this->assertNull(AppIcon::named(null));
    }

    public function test_the_overridden_icon_component_renders_google_assets(): void
    {
        // Ikon internal Filament (panel notifikasi, tombol close modal,
        // dsb.) dirender lewat `<x-filament::icon>` -- yang ditimpa di
        // resources/views/{Panel}/vendor/filament/components/icon.blade.php.
        // Tanpa penimpaan itu, nama literal seperti 'heroicon-m-x-mark'
        // dirender langsung `@svg()` -> VEKTOR Heroicon monokrom, meskipun
        // alias FilamentIcon sudah didaftarkan: alias cuma menyentuh
        // ikon yang PUNYA `icon-alias` dan di-resolve dulu.
        foreach (['User', 'Welcome', 'Admin'] as $panel) {
            $this->assertFileExists(
                resource_path("views/{$panel}/vendor/filament/components/icon.blade.php"),
                "Panel {$panel}: penimpa `<x-filament::icon>` hilang. Ikon "
                .'internal Filament akan balik ke Heroicon monokrom.',
            );
        }

        foreach (['heroicon-m-x-mark', 'heroicon-o-chat-bubble-left-ellipsis'] as $nama) {
            $html = view('filament::components.icon', ['icon' => $nama])->render();

            $this->assertStringContainsString(
                'src="'.AppIcon::FOLDER,
                $html,
                "Ikon `{$nama}` di <x-filament::icon> tidak dirender sebagai "
                .'<img> aset berwarna. Penimpa komponen ikon rusak.',
            );

            // Vektor Heroicon paket blade-icons punya path inlined --
            // tidak boleh muncul lagi untuk nama yang ada di peta.
            $this->assertStringNotContainsString(
                '<svg xmlns="http://www.w3.org/2000/svg"',
                $html,
                "Ikon `{$nama}` masih dirender sebagai SVG Heroicon bawaan.",
            );
        }
    }

    public function test_notification_icons_are_registered_as_colored_assets(): void
    {
        // Panel notifikasi database: ikon status (fallback), tombol close
        // per notifikasi, close modal, dan ikon empty-state juga harus
        // memakai aset berwarna lewat FilamentIcon.
        $wajib = [
            'notifications::notification.danger',
            'notifications::notification.info',
            'notifications::notification.success',
            'notifications::notification.warning',
            'notifications::notification.close-button',
            'notifications::database.modal.empty-state',
            'modal.close-button',
        ];

        foreach ($wajib as $key) {
            $path = VendorIconAliases::all()[$key] ?? 'belum terdaftar';

            $this->assertStringStartsWith(
                AppIcon::FOLDER,
                $path,
                "Alias ikon notifikasi `{$key}` belum ditimpa aset berwarna "
                ."({$path}).",
            );
        }
    }

    public function test_resource_navigation_icons_are_colored_assets(): void
    {
        foreach (['admin', 'welcome'] as $panelId) {
            $panel = Filament::getPanel($panelId);

            foreach ($panel->getResources() as $resource) {
                $icon = $resource::getNavigationIcon();

                $this->assertIsString(
                    $icon,
                    "{$resource} tidak punya ikon navigasi.",
                );

                $this->assertStringStartsWith(
                    AppIcon::FOLDER,
                    $icon,
                    "Ikon navigasi {$resource} masih monokrom: {$icon}. "
                    .'Harus aset berwarna di public/images/icons/.',
                );
            }
        }
    }

    public function test_user_menu_item_icons_are_colored_assets(): void
    {
        foreach (['admin', 'welcome'] as $panelId) {
            $panel = Filament::getPanel($panelId);

            foreach ($panel->getUserMenuItems() as $key => $item) {
                $icon = $item->getIcon();

                if ($icon === null) {
                    continue;
                }

                $this->assertStringStartsWith(
                    AppIcon::FOLDER,
                    $icon,
                    "Ikon menu item '{$key}' di panel {$panelId} masih monokrom: {$icon}.",
                );
            }
        }
    }

    public function test_navigation_groups_have_no_icon_when_their_items_do(): void
    {
        // Filament melempar exception kalau group DAN item-nya sama-sama
        // punya ikon -- dan seluruh halaman ikut 500. Jadi penjaga ini bukan
        // soal gaya, tapi soal halaman yang masih bisa dibuka.
        foreach (['admin', 'welcome'] as $panelId) {
            $panel = Filament::getPanel($panelId);

            foreach ($panel->getNavigationGroups() as $group) {
                $hasGroupIcon = $group->getIcon() !== null;

                $hasItemIcon = false;

                foreach ($group->getItems() as $item) {
                    if ($item->getIcon() !== null) {
                        $hasItemIcon = true;
                        break;
                    }
                }

                $this->assertFalse(
                    $hasGroupIcon && $hasItemIcon,
                    "Navigation group '{$group->getLabel()}' di panel {$panelId} punya ikon "
                    .'DAN item-itemnya punya ikon. Filament akan melempar exception '
                    .'("Navigation group has an icon but one or more of its items also '
                    .'have icons") dan halamannya jadi 500.',
                );
            }
        }
    }

    public function test_the_rendered_navigation_uses_images_not_svg_icons(): void
    {
        $html = $this->get('/home')->assertOk()->getContent();

        $this->assertStringContainsString(
            AppIcon::FOLDER,
            $html,
            'Navigasi yang dirender tidak memuat aset ikon berwarna.',
        );

        // Ikon navigasi harusnya <img>, bukan <svg> Heroicon.
        $this->assertStringContainsString(
            'src="'.AppIcon::FOLDER,
            $html,
            'Ikon navigasi tidak dirender sebagai <img>.',
        );
    }

    /**
     * File ikon diwajibkan: ada, tanpa `currentColor`, dan punya minimal dua
     * warna -- dihitung dari `fill`/`stroke` AKTIF plus `stop-color` gradien
     * (hasilan generator selalu punya gradien terang -> base).
     */
    private function assertSvgIsColored(string $name, string $file): void
    {
        $svg = (string) file_get_contents($file);

        $this->assertStringNotContainsString(
            'currentColor',
            $svg,
            "Ikon `{$name}` masih memakai currentColor -- artinya warnanya "
            .'mengikuti warna teks dan ikonnya monokrom lagi.',
        );

        preg_match_all('/(?:stroke|fill|stop-color)="(#[0-9A-Fa-f]{6})"/', $svg, $matches);

        $colors = array_values(array_unique($matches[1]));

        $first = $colors[0] ?? 'tidak ada';

        $this->assertGreaterThanOrEqual(
            2,
            count($colors),
            "Ikon `{$name}` cuma punya satu warna ({$first}). "
            .'Ikon harus dua warna: bentuk utama + aksen.',
        );
    }
}
