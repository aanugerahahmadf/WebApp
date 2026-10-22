<?php

namespace Tests\Feature\Shared\SwitcherPlacementTest;

use App\Enums\RuntimePlatform\RuntimePlatform;
use App\Models\Admin\Admin;
use App\Support\AppPlatform\AppPlatform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PENJAGA: switcher bahasa + switcher tema di surface yang benar.
 *
 * ATURAN (satu aturan, berlaku untuk ketiga panel):
 *
 *   - Mobile responsive (browser HP Android/iOS) dan APLIKASI MOBILE
 *     (Capacitor Android/iOS)  -> switcher di SIDEBAR (menu geser).
 *   - APLIKASI DESKTOP (Electron) -> switcher di TOPBAR / HEADER.
 *   - Tablet dan website desktop    -> switcher di TOPBAR.
 *
 * Gejala yang pernah muncul dan tidak boleh kembali:
 *
 *   1. APLIKASI DESKTOP tanpa theme switcher di topbar.
 *      Partial topbar-auth-actions membungkus switcher tema dengan
 *      `hidden sm:block`. Jendela aplikasi desktop tidak punya minWidth,
 *      jadi di bawah 640px switcher itu `display:none` -- dan karena
 *      predikat platform menaruhnya di topbar (bukan sidebar), switcher
 *      TIDAK ADA di mana pun. Persis tangkapan layar 400px.
 *
 *   2. Switcher dobel.
 *      Hook topbar dan hook sidebar harus memakai predikat yang sama
 *      (AppPlatform::switchersBelongInTopbar() / switchersBelongInSidebar()).
 *
 *   3. Panel admin menyimpan switcher temanya di user menu (dropdown
 *      avatar), bukan di topbar -- jadi di aplikasi desktop dia tidak
 *      benar-benar "di topbar".
 *
 * CARA MENGUKUR POSISI:
 *
 * Urutan DOM di layout Filament: topbar dulu (dalam fi-main-ctn), lalu
 * <main>, lalu <aside class="fi-sidebar">. Baris switcher di sidebar
 * ditandai class `fi-sidebar-switchers`. Jadi switcher yang posisinya
 * SEBELUM baris itu ada di topbar, dan yang posisinya SESUDAH baris itu
 * ada di sidebar.
 */
final class SwitcherPlacementTest extends TestCase
{
    use RefreshDatabase;

    /** Switcher bahasa: partial `x-ref="langPanel"` (dropdown-nya). */
    private const LANGUAGE_NEEDLE = 'x-ref="langPanel"';

    /** Switcher tema: wrapper dropdown `class="fi-theme-switcher"`. */
    private const THEME_NEEDLE = 'class="fi-theme-switcher"';

    protected function setUp(): void
    {
        parent::setUp();

        seedRoles();
    }

    /**
     * Di surface mana switcher ini berakhir: 'topbar', 'sidebar', atau
     * 'absent'.
     */
    private static function surface(string $html, string $needle): string
    {
        $position = strpos($html, $needle);

        if ($position === false) {
            return 'absent';
        }

        $sidebarRow = strpos($html, 'fi-sidebar-switchers');

        if ($sidebarRow !== false && $position > $sidebarRow) {
            return 'sidebar';
        }

        return 'topbar';
    }

    private function storefrontHtml(RuntimePlatform $platform): string
    {
        AppPlatform::fake($platform);

        return $this->get('/home')->assertOk()->getContent();
    }

    private function adminHtml(RuntimePlatform $platform): string
    {
        AppPlatform::fake($platform);

        $admin = Admin::factory()->create(['active_status' => true]);
        $admin->assignRole('super_admin');

        $response = $this->actingAs($admin, 'admin')->get('/admin');

        return $this->actingAs($admin, 'admin')
            ->get($response->headers->get('Location'))
            ->assertOk()
            ->getContent();
    }

    // ── HP / MOBILE RESPONSIVE ─────────────────────────────────────────────

    #[DataProvider('mobileSurfaces')]
    public function test_mobile_surfaces_put_both_switchers_in_the_sidebar(RuntimePlatform $platform): void
    {
        $html = $this->storefrontHtml($platform);

        $this->assertSame('sidebar', self::surface($html, self::LANGUAGE_NEEDLE));
        $this->assertSame('sidebar', self::surface($html, self::THEME_NEEDLE));

        // Tetap SATU masing-masing, tidak dobel.
        $this->assertSame(1, substr_count($html, self::LANGUAGE_NEEDLE));
        $this->assertSame(1, substr_count($html, self::THEME_NEEDLE));
    }

    #[DataProvider('mobileSurfaces')]
    public function test_mobile_surfaces_put_both_switchers_in_the_sidebar_of_the_admin_panel(RuntimePlatform $platform): void
    {
        $html = $this->adminHtml($platform);

        $this->assertSame('sidebar', self::surface($html, self::LANGUAGE_NEEDLE));
        $this->assertSame('sidebar', self::surface($html, self::THEME_NEEDLE));

        $this->assertSame(1, substr_count($html, self::LANGUAGE_NEEDLE));
        $this->assertSame(1, substr_count($html, self::THEME_NEEDLE));
    }

    public static function mobileSurfaces(): array
    {
        return [
            'browser HP Android' => [RuntimePlatform::WebsiteAndroid],
            'browser HP iOS' => [RuntimePlatform::WebsiteIos],
            'aplikasi mobile Android' => [RuntimePlatform::MobileAppAndroid],
            'aplikasi mobile iOS' => [RuntimePlatform::MobileAppIos],
        ];
    }

    // ── APLIKASI DESKTOP (Electron) ────────────────────────────────────────

    #[DataProvider('desktopAppSurfaces')]
    public function test_the_desktop_app_puts_both_switchers_in_the_topbar(RuntimePlatform $platform): void
    {
        $html = $this->storefrontHtml($platform);

        $this->assertSame('topbar', self::surface($html, self::LANGUAGE_NEEDLE));
        $this->assertSame('topbar', self::surface($html, self::THEME_NEEDLE));

        $this->assertSame(1, substr_count($html, self::LANGUAGE_NEEDLE));
        $this->assertSame(1, substr_count($html, self::THEME_NEEDLE));
    }

    #[DataProvider('desktopAppSurfaces')]
    public function test_the_desktop_app_puts_both_switchers_in_the_topbar_of_the_admin_panel(RuntimePlatform $platform): void
    {
        $html = $this->adminHtml($platform);

        $this->assertSame('topbar', self::surface($html, self::LANGUAGE_NEEDLE));
        $this->assertSame('topbar', self::surface($html, self::THEME_NEEDLE));

        $this->assertSame(1, substr_count($html, self::LANGUAGE_NEEDLE));
        $this->assertSame(1, substr_count($html, self::THEME_NEEDLE));
    }

    public static function desktopAppSurfaces(): array
    {
        return [
            'aplikasi desktop Windows' => [RuntimePlatform::DesktopAppWindows],
            'aplikasi desktop macOS' => [RuntimePlatform::DesktopAppMacOS],
        ];
    }

    /**
     * Switcher tema di topbar tidak boleh disembunyikan oleh aturan lebar
     * layar.
     *
     * Jendela aplikasi desktop tidak punya minWidth, jadi bisa dikecilkan
     * ke bawah 640px. Wrapper `hidden sm:block` membuat switcher hilang
     * total di lebar itu -- bukan pindah ke sidebar, tapi tidak ada di
     * mana pun.
     */
    public function test_the_topbar_theme_switcher_is_not_hidden_by_a_viewport_breakpoint(): void
    {
        $html = $this->storefrontHtml(RuntimePlatform::DesktopAppWindows);

        $position = strpos($html, self::THEME_NEEDLE);

        $this->assertNotFalse($position, 'Switcher tema harus ada di topbar aplikasi desktop.');

        // Wrapper switcher tema ada tepat sebelum markup switcher itu.
        $wrapper = substr($html, max(0, $position - 400), 400);

        $this->assertStringNotContainsString(
            'hidden sm:block',
            $wrapper,
            'Switcher tema di topbar tidak boleh dibungkus `hidden sm:block`: '
            .'aplikasi desktop bisa dikecilkan ke bawah 640px, dan switcher '
            .'nya akan hilang dari seluruh aplikasi.',
        );
    }

    // ── WEBSITE DESKTOP ────────────────────────────────────────────────────

    public function test_the_desktop_website_keeps_both_switchers_in_the_topbar(): void
    {
        $html = $this->storefrontHtml(RuntimePlatform::WebsiteWindows);

        $this->assertSame('topbar', self::surface($html, self::LANGUAGE_NEEDLE));
        $this->assertSame('topbar', self::surface($html, self::THEME_NEEDLE));
    }

    // ── ADMIN: tidak boleh pakai strip tiga ikon milik Filament ─────────

    public function test_the_admin_panel_does_not_use_filaments_three_button_strip(): void
    {
        // Strip tiga ikon (sun/moon/monitor) milik Filament. Di ketiga panel
        // switcher tema adalah dropdown satu tombol, jadi strip itu tidak
        // boleh muncul sama sekali.
        $html = $this->adminHtml(RuntimePlatform::DesktopAppWindows);

        $this->assertStringNotContainsString(
            'fi-theme-switcher grid grid-flow-col',
            $html,
            'Switcher strip tiga ikon milik Filament tidak boleh muncul di panel '
            .'Admin -- panel ini memakai dropdown satu tombol, seperti Welcome '
            .'dan User.',
        );
    }
}
