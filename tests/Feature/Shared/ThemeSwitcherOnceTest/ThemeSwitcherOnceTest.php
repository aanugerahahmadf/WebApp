<?php

namespace Tests\Feature\Shared\ThemeSwitcherOnceTest;

use App\Enums\RuntimePlatform\RuntimePlatform;
use App\Models\Admin\Admin;
use App\Models\User\User;
use App\Support\AppPlatform\AppPlatform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penjaga: switcher tema harus muncul SATU KALI per halaman.
 *
 * Dulunya ada dua, dan keduanya kelihatan normal:
 *
 *   1. Topbar -- dropdown satu tombol (monitor), dari partial
 *      `Shared.components.theme-switcher.theme-switcher`.
 *   2. User menu -- strip tiga ikon (sun/moon/monitor), dari switcher bawaan
 *      Filament `<x-filament-panels::theme-switcher />` di override view
 *      `resources/views/{Panel}/vendor/filament-panels/components/user-menu.blade.php`.
 *
 * Yang membuatnya dobel adalah switcher di user menu: user menu cuma dirender
 * untuk yang sudah sign in, tapi switcher topbar dirender untuk SEMUA orang.
 * Jadi begitu user masuk, dia melihat dua switcher untuk satu pengaturan --
 * dan tidak ada yang tahu mana yang benar.
 *
 * Aturan sekarang:
 *
 *   - Switcher tema milik SATU Surface: TOPBAR (desktop, tablet, dan aplikasi
 *     desktop) atau SIDEBAR (HP dan mobile web). Keduanya ditentukan predikat
 *     yang sama, `AppPlatform::switchersBelong*()`, jadi tidak pernah dobel.
 *     (Aplikasi desktop masuk ke topbar, bukan sidebar, karena sidebar-nya
 *     diterjemahkan ke luar layar di layar lebar.)
 *   - Switcher bawaan Filament di user menu DIMATIKAN di panel Welcome dan
 *     panel User, karena kedua panel itu sudah punya switcher sendiri di
 *     topbar.
 *   - Panel ADMIN berbeda: tidak punya switcher tema di topbar, jadi yang di
 *     user menu tetap dipakai. Di sana switcher itu satu-satunya, dan test ini
 *     menjaganya supaya tidak ikut dihapus.
 *
 * Cara menghitung switcher: yang dihitung adalah WRAPPER switcher, bukan
 * tombolnya. Wrapper Filament punya `class="fi-theme-switcher grid ..."`,
 * wrapper dropdown punya `class="fi-theme-switcher"`, dan tombolnya
 * `fi-theme-switcher-btn` -- yang HARUS dikecualikan, kalau tidak tombolnya
 * ikut terhitung dan angka yang dibandingkan jadi tidak berarti.
 */
final class ThemeSwitcherOnceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        seedRoles();
    }

    public function test_a_guest_sees_exactly_one_theme_switcher(): void
    {
        AppPlatform::fake(RuntimePlatform::WebsiteWindows);

        $html = $this->get('/home')->assertOk()->getContent();

        $this->assertSame(
            1,
            self::themeSwitcherCount($html),
            'Tamu harus melihat tepat satu switcher tema di topbar.',
        );
    }

    public function test_a_signed_in_customer_sees_exactly_one_theme_switcher(): void
    {
        // Ini kasus yang dulu dobel: user menu ikut dirender, dan switcher
        // bawaan Filament di dalamnya muncul berdampingan dengan switcher
        // topbar.
        AppPlatform::fake(RuntimePlatform::WebsiteWindows);

        $user = User::factory()->create();
        $user->assignRole('customer');

        $html = $this->actingAs($user, 'web')->get('/home')->assertOk()->getContent();

        $this->assertSame(
            1,
            self::themeSwitcherCount($html),
            'User yang sudah sign in harus melihat TEPAT satu switcher tema. '
            .'Kalau dua, berarti switcher topbar dan switcher di user menu '
            .'sekaligus dirender.',
        );
    }

    public function test_the_storefront_does_not_use_filaments_three_button_strip(): void
    {
        // Strip tiga ikon (sun/moon/monitor) milik Filament. Di panel Welcome
        // dan User switcher topbar adalah dropdown satu tombol, jadi strip
        // itu tidak boleh muncul sama sekali.
        AppPlatform::fake(RuntimePlatform::WebsiteWindows);

        $user = User::factory()->create();
        $user->assignRole('customer');

        $html = $this->actingAs($user, 'web')->get('/home')->assertOk()->getContent();

        $this->assertStringNotContainsString(
            'fi-theme-switcher grid grid-flow-col',
            $html,
            'Switcher strip tiga ikon milik Filament tidak boleh muncul di panel '
            .'Welcome -- panel ini memakai dropdown satu tombol. Strip itu '
            .'hanya untuk panel admin.',
        );
    }

    public function test_a_phone_gets_exactly_one_theme_switcher_in_the_sidebar(): void
    {
        // Di HP topbar menyingkir, jadi switcher pindah ke sidebar (menu
        // geser). Tetap satu.
        AppPlatform::fake(RuntimePlatform::MobileAppAndroid);

        $user = User::factory()->create();
        $user->assignRole('customer');

        $html = $this->actingAs($user, 'web')->get('/home')->assertOk()->getContent();

        $this->assertSame(
            1,
            self::themeSwitcherCount($html),
            'Di HP switcher tema pindah ke sidebar, tapi jumlahnya tetap satu.',
        );
    }

    public function test_the_desktop_app_gets_exactly_one_theme_switcher(): void
    {
        // Aplikasi desktop: switcher-nya di sidebar, bukan topbar.
        AppPlatform::fake(RuntimePlatform::DesktopAppWindows);

        $user = User::factory()->create();
        $user->assignRole('customer');

        $html = $this->actingAs($user, 'web')->get('/home')->assertOk()->getContent();

        $this->assertSame(
            1,
            self::themeSwitcherCount($html),
            'Di aplikasi desktop switcher tema ada di sidebar, dan jumlahnya '
            .'tetap satu.',
        );
    }

    public function test_the_admin_panel_keeps_exactly_one_theme_switcher(): void
    {
        // Panel admin TIDAK punya switcher tema di topbar, jadi yang di user
        // menu tetap dipakai. Test ini menjaganya supaya switcher itu tidak
        // ikut dihapus waktu switcher Filament dimatikan di panel lain.
        AppPlatform::fake(RuntimePlatform::WebsiteWindows);

        $admin = Admin::factory()->create(['active_status' => true]);
        $admin->assignRole('super_admin');

        // `/admin` sendiri yang mengarahkan ke dasbor, jadi request kedua
        // memakai tujuan redirect itu -- sama seperti AdminAccessTest.
        $response = $this->actingAs($admin, 'admin')->get('/admin');
        $html = $this->actingAs($admin, 'admin')
            ->get($response->headers->get('Location'))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            1,
            self::themeSwitcherCount($html),
            'Panel admin harus punya tepat satu switcher tema (yang di user menu).',
        );
    }

    /**
     * Berapa switcher tema yang ada di HTML ini.
     *
     * Yang dihitung adalah wrapper switcher. Wrapper dropdown punya
     * `class="fi-theme-switcher"`, wrapper strip Filament punya
     * `class="fi-theme-switcher grid grid-flow-col gap-x-1"`, dan tombolnya
     * `fi-theme-switcher-btn` -- yang dikecualikan oleh lookahead, kalau tidak
     * satu switcher bisa terhitung sebagai tiga atau empat.
     */
    private static function themeSwitcherCount(string $html): int
    {
        return (int) preg_match_all('/class="[^"]*fi-theme-switcher(?![-\w])/', $html);
    }
}
